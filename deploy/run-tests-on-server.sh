#!/usr/bin/env bash
#
# 新サーバの中でサーバ側のテスト(PHPUnit)を流す。
#
#   deploy/ssm-run.sh i-0626d85c720708c64 -f deploy/run-tests-on-server.sh
#
# 動いているアプリ(/var/www/MyHighlights)には触らない。
# コミット済みのコードを /root/tsubasa-phpunit に取り出し、そこで実行する。
# - テスト用の道具(composer の require-dev)は、この取り出した先にだけ入れる
# - データベースは専用の tsubasa_phpunit。そこにしか触れない利用者 phpunit を作る
#   （phpunit.xml の注意書きの通り、デモ環境の tsubasa_test とは必ず分ける）
# - 設定は .env.example から作り、鍵も新しく作る。本番の .env は使わない
# - 画面を返すテストが読む Vite のビルド結果は、動いているアプリからコピーする
#
# 2 回目からは中身を作り直さずに済む:
#   cd /root/tsubasa-phpunit && git pull && php vendor/bin/phpunit
set -euo pipefail

export HOME=/root COMPOSER_ALLOW_SUPERUSER=1
APP=/var/www/MyHighlights
DIR=/root/tsubasa-phpunit

if [ ! -d "$DIR/.git" ]; then
  git clone -q "$APP" "$DIR"
  cd "$DIR"
  composer install -q --no-interaction --no-progress

  PW=$(openssl rand -hex 16)
  mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS tsubasa_phpunit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'phpunit'@'localhost' IDENTIFIED BY '$PW';
ALTER USER 'phpunit'@'localhost' IDENTIFIED BY '$PW';
GRANT ALL PRIVILEGES ON tsubasa_phpunit.* TO 'phpunit'@'localhost';
FLUSH PRIVILEGES;
SQL

  cp .env.example .env
  sed -i "s/^DB_HOST=.*/DB_HOST=localhost/; s/^DB_USERNAME=.*/DB_USERNAME=phpunit/; s/^DB_PASSWORD=.*/DB_PASSWORD=$PW/" .env
  php artisan key:generate -q
else
  cd "$DIR"
  git pull -q
  composer install -q --no-interaction --no-progress
fi

# 画面を返すテストは Vite のビルド結果(manifest.json)を読む
rm -rf public/build
cp -r "$APP/public/build" public/build

php artisan optimize:clear -q
php vendor/bin/phpunit --colors=never
