#!/usr/bin/env bash
#
# 添付ファイル(storage/app/public)を旧サーバから新サーバへ同期する。
#
#   旧サーバ --(rsync)--> ローカル --(aws s3 sync)--> S3 --(aws s3 sync)--> 新サーバ
#
# 新サーバはインバウンドを開けていないため直接rsyncできない。S3を中継する。
# 3段すべて差分同期なので、事前のフル同期にも当夜の差分同期にも
# 同じコマンドがそのまま使える。
#
# 添付は追記のみ(既存ファイルは書き換わらない)なので、
# 事前にフル同期しておいても不整合にならない。
#
#   deploy/sync-attachments.sh              # 旧→ローカル→S3 まで
#   deploy/sync-attachments.sh --to-server  # 新サーバへの取り込みも行う
#
set -euo pipefail

OLD_HOST=${OLD_HOST:-ec2-52-199-130-187.ap-northeast-1.compute.amazonaws.com}
OLD_PORT=${OLD_PORT:-36180}
OLD_USER=${OLD_USER:-ec2-user}
OLD_KEY=${OLD_KEY:-$HOME/.ssh/ec2-001.pem}
OLD_PATH=${OLD_PATH:-/var/www/MyHighlights/storage/app/public/}

LOCAL_DIR=${LOCAL_DIR:-$HOME/tsubasa-migration-backup/attachments}
BUCKET=${BUCKET:-tsubasa-migration-796478799102}
S3_PREFIX=${S3_PREFIX:-attachments}
INSTANCE_ID=${INSTANCE_ID:-i-0421f25f72d67e67b}

mkdir -p "$LOCAL_DIR"

echo "===== 1) 旧サーバ → ローカル (rsync) ====="
T0=$(date +%s)
rsync -a --partial \
  -e "ssh -i $OLD_KEY -p $OLD_PORT -o BatchMode=yes" \
  "${OLD_USER}@${OLD_HOST}:${OLD_PATH}" "$LOCAL_DIR/"
T1=$(date +%s)
echo "  所要 $((T1-T0)) 秒 / $(du -sh "$LOCAL_DIR" | cut -f1) / $(find "$LOCAL_DIR" -type f | wc -l | tr -d ' ')ファイル"

echo
echo "===== 2) ローカル → S3 ====="
T2=$(date +%s)
aws s3 sync "$LOCAL_DIR/" "s3://${BUCKET}/${S3_PREFIX}/" --only-show-errors
T3=$(date +%s)
echo "  所要 $((T3-T2)) 秒"

if [ "${1:-}" = "--to-server" ]; then
  echo
  echo "===== 3) S3 → 新サーバ (SSM経由) ====="
  T4=$(date +%s)
  cat > /tmp/pull_attachments.sh <<INNER
#!/usr/bin/env bash
set -euo pipefail
cd /var/www/MyHighlights
aws s3 sync "s3://${BUCKET}/${S3_PREFIX}/" storage/app/public/ --only-show-errors
chown -R apache:apache storage/app/public
echo "  ファイル数: \$(find storage/app/public -type f | wc -l)"
echo "  サイズ:     \$(du -sh storage/app/public | cut -f1)"
df -h / | tail -1
INNER
  "$(dirname "$0")/ssm-run.sh" "$INSTANCE_ID" -f /tmp/pull_attachments.sh
  rm -f /tmp/pull_attachments.sh
  T5=$(date +%s)
  echo "  所要 $((T5-T4)) 秒"
fi

echo
echo "===== 完了 ====="
