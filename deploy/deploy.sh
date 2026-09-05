#!/usr/bin/env bash
#
# アプリのデプロイ。git pull 後に実行する。
#
set -euo pipefail

APP_DIR=${APP_DIR:-/var/www/MyHighlights}
cd "${APP_DIR}"

# SSM経由(AWS-RunShellScript)で流すとrootかつHOME未設定で動くため、
# composer が "The HOME or COMPOSER_HOME environment variable must be set"
# で落ちる。rootで動かすこと自体は承知のうえなので明示的に許可する。
export HOME="${HOME:-/root}"
export COMPOSER_HOME="${COMPOSER_HOME:-${HOME}/.composer}"
export COMPOSER_ALLOW_SUPERUSER=1

# gitは空ディレクトリを追跡しないため、クローン直後には
# storage/framework/cache/data が存在しない。無いままだとレートリミッタが
# ファイルキャッシュに書けず、/api/* が全て500になる。
echo "==> storage ディレクトリの用意"
mkdir -p storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R apache:apache storage bootstrap/cache

echo "==> composer"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> フロントエンドのビルド"
# 本番サーバでビルドしたくない場合は、CI等でビルドした public/build を配布してもよい
npm ci
npm run build

# artisan は apache ユーザーで実行する。
# SSM経由だとrootで動くため、素で流すと storage 配下に root 所有の
# ディレクトリ(特に framework/cache/data)ができ、以後 Apache が
# そこへ書けなくなって /api/* が全て500になる。
# 「ログインはできるがデータが出ない」という出方をするので気づきにくい。
ARTISAN="php artisan"
if [ "$(id -u)" = "0" ]; then
  ARTISAN="sudo -u apache php artisan"
fi

echo "==> マイグレーション"
${ARTISAN} migrate --force

echo "==> キャッシュの再生成"
${ARTISAN} optimize:clear
${ARTISAN} config:cache
${ARTISAN} route:cache
${ARTISAN} view:cache
${ARTISAN} event:cache

echo "==> storage シンボリックリンク"
${ARTISAN} storage:link || true

echo "==> 権限"
# composer/npm はrootで動かしているので、最後にもう一度揃える
chown -R apache:apache storage bootstrap/cache

echo "==> キューワーカーの再起動"
# 現在のジョブを終えてからワーカーが終了し、systemdが新しいコードで起動し直す
${ARTISAN} queue:restart

echo "==> 完了"
