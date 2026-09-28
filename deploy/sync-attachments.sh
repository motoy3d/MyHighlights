#!/usr/bin/env bash
#
# 添付ファイル(storage/app/public)を旧サーバから新サーバへ同期する。
#
#   旧サーバ --(署名付きURLでPUT)--> S3 --(aws s3 cp)--> 新サーバ
#
# 新サーバはインバウンドを開けていないため直接rsyncできない。S3を中継する。
# ローカル経由(旧→ローカル→S3)は5.2GBを53分かけて失敗したので使わない。
# 旧サーバから直接S3へ送るとAWS内で完結し、2.2GBが20秒で済む。
#
# 旧サーバにはS3の権限が無いので、手元で署名付きPUT URLを発行して渡す。
# IAMは一切変更しない(本番稼働中の SESFromEC2 に触らない)。
# 単一PUTの上限が5GBなので、ディレクトリ単位に分ける。
#
# 添付は追記のみ(既存ファイルは書き換わらない)なので、
# 事前にフル同期しておき、当夜は差分だけにする。
# 当夜の差分は --since で「この日時以降に更新されたファイル」だけを送る。
#
#   deploy/sync-attachments.sh                      # 全件
#   deploy/sync-attachments.sh --since '2026-09-06' # 差分(この日以降の更新分)
#   deploy/sync-attachments.sh --to-server          # 新サーバへの取り込みも行う
#
# 前提: 手元に boto3 (python3 -m pip install --user boto3)
#
set -euo pipefail

OLD_HOST=${OLD_HOST:-ec2-52-199-130-187.ap-northeast-1.compute.amazonaws.com}
OLD_PORT=${OLD_PORT:-36180}
OLD_USER=${OLD_USER:-ec2-user}
OLD_KEY=${OLD_KEY:-$HOME/.ssh/ec2-001.pem}
OLD_PATH=${OLD_PATH:-/var/www/MyHighlights/storage/app/public}

BUCKET=${BUCKET:-tsubasa-migration-796478799102}
INSTANCE_ID=${INSTANCE_ID:-i-0626d85c720708c64}
REGION=${AWS_DEFAULT_REGION:-ap-northeast-1}
DIRS=${DIRS:-"prof post_attachment comment_attachment"}

SINCE=""; TO_SERVER=0
while [ $# -gt 0 ]; do
  case "$1" in
    --since) SINCE="$2"; shift 2;;
    --to-server) TO_SERVER=1; shift;;
    *) echo "unknown arg: $1" >&2; exit 2;;
  esac
done

STAMP=$(date -u +%Y%m%dT%H%M%SZ)
PREFIX="sync/${STAMP}"
SSH=(ssh -i "$OLD_KEY" -p "$OLD_PORT" -o BatchMode=yes -o ConnectTimeout=10 "${OLD_USER}@${OLD_HOST}")

presign() {  # 署名付きPUT URL (12時間有効)。手元の資格情報で発行し旧サーバに渡す
  python3 - "$BUCKET" "$REGION" "$1" <<'PY'
import boto3, sys
b, r, k = sys.argv[1:4]
print(boto3.client('s3', region_name=r).generate_presigned_url(
    'put_object', Params={'Bucket': b, 'Key': k}, ExpiresIn=43200, HttpMethod='PUT'))
PY
}

echo "===== 旧サーバで tar して S3 へ直接PUT (${SINCE:-全件}) ====="
T0=$(date +%s)
for d in $DIRS; do
  U=$(presign "${PREFIX}/${d}.tar")
  # 空きが7.7GBしかないので、1つずつ作って送って消す
  # sshは引数を1本の文字列に連結して遠隔シェルに渡す。URLに & が含まれるので %q で引用する
  ARGS=$(printf '%q ' "$OLD_PATH" "$d" "$U" "$SINCE")
  "${SSH[@]}" "bash -s -- $ARGS" <<'REMOTE'
set -euo pipefail
BASE="$1"; D="$2"; U="$3"; SINCE="$4"
cd "$BASE"
TAR="/var/tmp/${D}.tar"
if [ -n "$SINCE" ]; then
  # 差分: SINCE 以降に更新されたファイルだけ(0件なら空のtar)
  find "$D" -type f -newermt "$SINCE" -print0 | tar cf "$TAR" --null -T -
  N=$(tar tf "$TAR" | wc -l | tr -d ' ')
else
  tar cf "$TAR" "$D"
  N=$(find "$D" -type f | wc -l | tr -d ' ')
fi
SZ=$(du -h "$TAR" | cut -f1)
CODE=$(curl -s -o /dev/null -w '%{http_code}' -X PUT --upload-file "$TAR" "$U")
rm -f "$TAR"
echo "  ${D}: ${N}ファイル / ${SZ} -> HTTP ${CODE}"
[ "$CODE" = "200" ]
REMOTE
done
T1=$(date +%s)
echo "  所要 $((T1-T0)) 秒"

if [ "$TO_SERVER" = "1" ]; then
  echo
  echo "===== 3) 新サーバへ取り込み (SSM経由) ====="
  T2=$(date +%s)
  PULL=$(mktemp)
  cat > "$PULL" <<INNER
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/MyHighlights/storage/app/public
for d in $DIRS; do
  aws s3 cp "s3://${BUCKET}/${PREFIX}/\${d}.tar" /var/tmp/ --only-show-errors
  # 差分でも全件でも、tarの中身を上書き展開する(既存ファイルは書き換わらない前提)
  tar xf "/var/tmp/\${d}.tar" -C .
  rm -f "/var/tmp/\${d}.tar"
  echo "  \${d}: \$(find "./\${d}" -type f | wc -l | tr -d ' ')ファイル / \$(du -sh "./\${d}" | cut -f1)"
done
chown -R apache:apache /var/www/MyHighlights/storage/app/public
df -h / | tail -1
INNER
  "$(dirname "$0")/ssm-run.sh" "$INSTANCE_ID" -f "$PULL"
  rm -f "$PULL"
  T3=$(date +%s)
  echo "  所要 $((T3-T2)) 秒"
fi

echo
echo "===== 完了 (S3: s3://${BUCKET}/${PREFIX}/) ====="
