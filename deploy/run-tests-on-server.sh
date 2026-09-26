#!/usr/bin/env bash
#
# 新サーバの中でサーバ側のテスト(PHPUnit)を流す。
#
#   deploy/ssm-run.sh i-0626d85c720708c64 -f deploy/run-tests-on-server.sh
#
# テストするのは GitHub のブランチ(既定は、動いているアプリが今いるブランチ)。
# 別のブランチを流すときは、スクリプトの先頭で REF を指定する。
#
# 動いているアプリ(/var/www/MyHighlights)には触らない。
# - コードは /root/tsubasa-phpunit に GitHub から取り出す
# - テスト用の道具(composer の require-dev)は、この取り出した先にだけ入れる
# - データベースは専用の tsubasa_phpunit。そこにしか触れない利用者 phpunit を作る
#   （phpunit.xml の注意書きの通り、デモ環境の tsubasa_test とは必ず分ける）
# - 設定は .env.example から作り、鍵も新しく作る。本番の .env は使わない
# - 画面を返すテストが読む Vite のビルド結果は、動いているアプリからコピーする
#
# どの段階で失敗しても、次に流したときにその段階からやり直す(段階ごとに済んでいるかを確かめる)。
set -euo pipefail

export HOME=/root COMPOSER_ALLOW_SUPERUSER=1
APP=/var/www/MyHighlights
DIR=/root/tsubasa-phpunit
REPO=https://github.com/motoy3d/MyHighlights.git
REF=${REF:-$(git -C "$APP" rev-parse --abbrev-ref HEAD)}

# コード
if [ ! -d "$DIR/.git" ]; then
  git clone -q "$REPO" "$DIR"
fi
cd "$DIR"
git remote set-url origin "$REPO"
git fetch -q origin "$REF"
git checkout -q -B phpunit-run FETCH_HEAD
echo "テストするコード: $REF $(git log --oneline -1)"

composer install -q --no-interaction --no-progress

# データベースと設定(.env が無いときだけ作る。パスワードはコマンドの引数に出さない)
if [ ! -f .env ]; then
  PW=$(openssl rand -hex 16)
  mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS tsubasa_phpunit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'phpunit'@'localhost' IDENTIFIED BY '$PW';
ALTER USER 'phpunit'@'localhost' IDENTIFIED BY '$PW';
GRANT ALL PRIVILEGES ON tsubasa_phpunit.* TO 'phpunit'@'localhost';
FLUSH PRIVILEGES;
SQL
  grep -v -E '^(DB_HOST|DB_USERNAME|DB_PASSWORD)=' .env.example > .env.tmp
  {
    echo 'DB_HOST=localhost'
    echo 'DB_USERNAME=phpunit'
    printf 'DB_PASSWORD=%s\n' "$PW"
  } >> .env.tmp
  mv .env.tmp .env
  chmod 600 .env
  php artisan key:generate -q
fi

# 画面を返すテストは Vite のビルド結果(manifest.json)を読む
rm -rf public/build
cp -r "$APP/public/build" public/build

php artisan optimize:clear -q
php vendor/bin/phpunit --colors=never
