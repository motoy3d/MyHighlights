#!/usr/bin/env bash
#
# storage / bootstrap/cache の所有者を apache に揃える。
#
# SSM(AWS-RunShellScript)はコマンドをrootで実行するため、
# php artisan を素で流すと storage 配下に root 所有のディレクトリが
# 作られる。するとApache(apacheユーザー)がそこへ書けなくなり、
# レートリミッタがファイルキャッシュに書けずに /api/* が全て500になる。
# 画面上は「ログインはできるがデータが出ない」という出方をするので
# 原因が分かりにくい。
#
# artisan を root で流してしまった後は、これを実行して直す。
#
#   deploy/ssm-run.sh <インスタンスID> -f deploy/fix-permissions.sh
#
set -euo pipefail

APP_DIR=${APP_DIR:-/var/www/MyHighlights}
cd "${APP_DIR}"

# gitは空ディレクトリを追跡しないので、クローン直後に無いものがある
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache

chown -R apache:apache storage bootstrap/cache

echo "==> 確認"
for d in storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache; do
  if sudo -u apache test -w "$d"; then
    printf "  OK   %s\n" "$d"
  else
    printf "  NG   %s (apacheが書けない)\n" "$d"
    exit 1
  fi
done
echo "==> 完了"
