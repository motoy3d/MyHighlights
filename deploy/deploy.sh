#!/usr/bin/env bash
#
# アプリのデプロイ。git pull 後に実行する。
#
set -euo pipefail

APP_DIR=${APP_DIR:-/var/www/MyHighlights}
cd "${APP_DIR}"

echo "==> composer"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> フロントエンドのビルド"
# 本番サーバでビルドしたくない場合は、CI等でビルドした public/build を配布してもよい
npm ci
npm run build

echo "==> マイグレーション"
php artisan migrate --force

echo "==> キャッシュの再生成"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> storage シンボリックリンク"
php artisan storage:link || true

echo "==> 権限"
sudo chown -R apache:apache storage bootstrap/cache

echo "==> キューワーカーの再起動"
# 現在のジョブを終えてからワーカーが終了し、systemdが新しいコードで起動し直す
php artisan queue:restart

echo "==> 完了"
