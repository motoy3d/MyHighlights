#!/usr/bin/env bash
#
# 切り替え当夜の DB フル取り込み。旧サーバ(MySQL 5.7)から新サーバ(MariaDB 10.11)へ。
#
#   旧 --(mysqldump | pigz)--> /var/tmp --(署名付きURLで PUT)--> S3 --(aws s3 cp)--> 新 --(mysql)-->
#
# - `logs` は直近2年分だけ。時刻の索引が無いので PK の範囲で抜く(境界 id は毎回取り直す)。
#   全走査を避けるため、前回の境界 id 以降だけを走査する(実測 8 秒)。
# - 新サーバの DB は DROP して作り直す(テストで汚れた状態を確実に捨てる)。
# - 取り込み後に migrate、jobs/failed_jobs を空に、キャッシュ再生成、件数の突き合わせ。
# - 旧サーバの空きは 7.5GB。ダンプは圧縮して 1 本ずつ送って消す。
#
#   deploy/cutover-db.sh                        # 実行(所要を表示)
#   deploy/cutover-db.sh --boundary-from 9456353  # 境界 id の走査開始位置(既定は前回の値)
#
# 旧サーバを php artisan down にしてから実行すること(ダンプ中に書き込みが入らないように)。
#
set -euo pipefail

OLD_HOST=${OLD_HOST:-ec2-52-199-130-187.ap-northeast-1.compute.amazonaws.com}
OLD_PORT=${OLD_PORT:-36180}
OLD_USER=${OLD_USER:-ec2-user}
OLD_KEY=${OLD_KEY:-$HOME/.ssh/ec2-001.pem}
OLD_APP=${OLD_APP:-/var/www/MyHighlights}
BUCKET=${BUCKET:-tsubasa-migration-796478799102}
INSTANCE_ID=${INSTANCE_ID:-i-0626d85c720708c64}
REGION=${AWS_DEFAULT_REGION:-ap-northeast-1}
NEW_DB=${NEW_DB:-tsubasa}
BOUNDARY_FROM=9456353   # 2026-09-06 時点の境界。ここ以降だけ走査する

while [ $# -gt 0 ]; do
  case "$1" in
    --boundary-from) BOUNDARY_FROM="$2"; shift 2;;
    *) echo "unknown arg: $1" >&2; exit 2;;
  esac
done

ACCT=$(aws sts get-caller-identity --query Account --output text)
[ "$ACCT" = "796478799102" ] || { echo "本番アカウントではない: $ACCT" >&2; exit 1; }

STAMP=$(date -u +%Y%m%dT%H%M%SZ)
PREFIX="cutover/${STAMP}"
SSH=(ssh -i "$OLD_KEY" -p "$OLD_PORT" -o BatchMode=yes -o ConnectTimeout=10 "${OLD_USER}@${OLD_HOST}")
T0=$(date +%s)
lap() { echo "  所要 $(( $(date +%s) - $1 )) 秒"; }

presign() {
  python3 - "$BUCKET" "$REGION" "$1" <<'PY'
import boto3, sys
b, r, k = sys.argv[1:4]
print(boto3.client('s3', region_name=r).generate_presigned_url(
    'put_object', Params={'Bucket': b, 'Key': k}, ExpiresIn=7200, HttpMethod='PUT'))
PY
}

echo "===== 1) 旧サーバでダンプして S3 へ PUT ====="
U_MAIN=$(presign "${PREFIX}/main.sql.gz"); U_LOGS=$(presign "${PREFIX}/logs.sql.gz")
ARGS=$(printf '%q ' "$OLD_APP" "$BOUNDARY_FROM" "$U_MAIN" "$U_LOGS")
T1=$(date +%s)
"${SSH[@]}" "bash -s -- $ARGS" <<'REMOTE'
set -euo pipefail
APP="$1"; FROM="$2"; U_MAIN="$3"; U_LOGS="$4"
U=$(grep "^DB_USERNAME=" "$APP/.env" | cut -d= -f2); export MYSQL_PWD=$(grep "^DB_PASSWORD=" "$APP/.env" | cut -d= -f2)
D=$(grep "^DB_DATABASE=" "$APP/.env" | cut -d= -f2)
B=$(mysql -N -u "$U" "$D" -e "SELECT MIN(id) FROM logs WHERE id >= $FROM AND log_timestamp >= DATE_SUB(NOW(), INTERVAL 2 YEAR)")
echo "  DB=$D  logs 境界 id=$B (それ以降 $(mysql -N -u "$U" "$D" -e "SELECT COUNT(*) FROM logs WHERE id >= $B") 行)"
mysqldump --single-transaction --quick --default-character-set=utf8mb4 -u "$U" --ignore-table="$D.logs" "$D" | pigz > /var/tmp/main.sql.gz
echo "  main.sql.gz $(du -h /var/tmp/main.sql.gz | cut -f1) -> HTTP $(curl -s -o /dev/null -w '%{http_code}' -X PUT --upload-file /var/tmp/main.sql.gz "$U_MAIN")"
rm -f /var/tmp/main.sql.gz
mysqldump --single-transaction --quick --default-character-set=utf8mb4 -u "$U" "$D" logs --where="id >= $B" | pigz > /var/tmp/logs.sql.gz
echo "  logs.sql.gz $(du -h /var/tmp/logs.sql.gz | cut -f1) -> HTTP $(curl -s -o /dev/null -w '%{http_code}' -X PUT --upload-file /var/tmp/logs.sql.gz "$U_LOGS")"
rm -f /var/tmp/logs.sql.gz
echo "  旧の件数: $(mysql -N -u "$U" "$D" -e "SELECT CONCAT('posts=',(SELECT COUNT(*) FROM posts),' post_comments=',(SELECT COUNT(*) FROM post_comments),' schedules=',(SELECT COUNT(*) FROM schedules),' users=',(SELECT COUNT(*) FROM users),' logs(境界以降)=',(SELECT COUNT(*) FROM logs WHERE id >= $B))")"
REMOTE
lap $T1

echo
echo "===== 2) 新サーバで DB を作り直して取り込み (SSM) ====="
T2=$(date +%s)
PULL=$(mktemp)
cat > "$PULL" <<INNER
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/MyHighlights
aws s3 cp "s3://${BUCKET}/${PREFIX}/main.sql.gz" /var/tmp/ --only-show-errors
aws s3 cp "s3://${BUCKET}/${PREFIX}/logs.sql.gz" /var/tmp/ --only-show-errors
mysql -e "DROP DATABASE IF EXISTS \\\`${NEW_DB}\\\`; CREATE DATABASE \\\`${NEW_DB}\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
S=\$(date +%s); gzip -dc /var/tmp/main.sql.gz | mysql "${NEW_DB}"; echo "  main 取り込み \$(( \$(date +%s) - S )) 秒"
S=\$(date +%s); gzip -dc /var/tmp/logs.sql.gz | mysql "${NEW_DB}"; echo "  logs 取り込み \$(( \$(date +%s) - S )) 秒"
rm -f /var/tmp/main.sql.gz /var/tmp/logs.sql.gz
sudo -u apache php artisan migrate --force --no-interaction 2>&1 | tail -3
mysql "${NEW_DB}" -e "TRUNCATE jobs; TRUNCATE failed_jobs;"
sudo -u apache php artisan optimize:clear -q && sudo -u apache php artisan config:cache -q && sudo -u apache php artisan route:cache -q && sudo -u apache php artisan view:cache -q && sudo -u apache php artisan event:cache -q
echo "  新の件数: \$(mysql -N "${NEW_DB}" -e "SELECT CONCAT('posts=',(SELECT COUNT(*) FROM posts),' post_comments=',(SELECT COUNT(*) FROM post_comments),' schedules=',(SELECT COUNT(*) FROM schedules),' users=',(SELECT COUNT(*) FROM users),' logs=',(SELECT COUNT(*) FROM logs),' jobs=',(SELECT COUNT(*) FROM jobs))")"
echo "  時刻の整合: \$(mysql -N "${NEW_DB}" -e "SELECT CONCAT(@@system_time_zone,' ', (SELECT CONCAT(log_timestamp,' / ',created_at) FROM logs ORDER BY id DESC LIMIT 1))")"
INNER
"$(dirname "$0")/ssm-run.sh" "$INSTANCE_ID" -f "$PULL"
rm -f "$PULL"
lap $T2

aws s3 rm "s3://${BUCKET}/${PREFIX}/" --recursive --quiet
echo
echo "===== 完了: 合計 $(( $(date +%s) - T0 )) 秒 ====="
echo "次: 検証アカウントを作る  sudo -u apache php artisan smoke:account create --password='…'"
