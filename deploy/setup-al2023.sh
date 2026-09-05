#!/usr/bin/env bash
#
# Amazon Linux 2023 に Tsubasa⬆UP の実行環境を用意する。
# 新しいEC2インスタンスで一度だけ実行する。
#
# SSH接続は使わない。ローカルから SSM 経由で流す:
#   deploy/ssm-run.sh <インスタンスID> -f deploy/setup-al2023.sh
# (SSMのsend-commandはrootで実行されるため sudo はそのまま通る)
#
# 収録パッケージのバージョンは 2026-08 時点の AL2023 リポジトリで確認済み:
#   php8.4 / mariadb1011 (10.11) / httpd 2.4.68 / nodejs24 / certbot 2.6 / composer 2.10
#
# Node は Vite のビルドにしか使わない(実行時には登場しない)が、
# AL2023 が提供する nodejs20 は 2026-04 にEOLを迎えているため nodejs24 を使う。
# nodejs24 は 2028-04 まで、nodejs22 は 2027-04 まで。
# Vite 7 の要求は Node 20.19+ / 22.12+ / 24+ なのでいずれでも動く。
#
set -euo pipefail

APP_DIR=${APP_DIR:-/var/www/MyHighlights}
DOMAIN=${DOMAIN:-tsubasa.smartj.mobi}

# --- IAMロールの確認 -------------------------------------------------------
# 本番の .env は SES_KEY / SES_SECRET が空で、AWS SDK が
# インスタンスプロファイル(IAMロール)から資格情報を取得している。
# ロールが付いていないと、通知メールがエラーも出さずに全て失敗する
# (キュー経由のため failed_jobs に積まれるだけで画面には出ない)。
# インスタンス作成時にロールを付ける運用としたので、ここで検算する。
echo "==> IAMロールの確認"
IMDS_TOKEN=$(curl -sS -X PUT "http://169.254.169.254/latest/api/token" \
  -H "X-aws-ec2-metadata-token-ttl-seconds: 60" --max-time 5 || true)
IAM_ROLE=$(curl -sS --max-time 5 \
  -H "X-aws-ec2-metadata-token: ${IMDS_TOKEN}" \
  "http://169.254.169.254/latest/meta-data/iam/security-credentials/" || true)

if [ -z "${IAM_ROLE}" ]; then
  echo "!!! このインスタンスにIAMロールが付いていません。" >&2
  echo "!!! 次の2つを含むロールをアタッチしてから再実行してください。" >&2
  echo "!!!   - SES送信権限 (ses:SendRawEmail)" >&2
  echo "!!!     付けないまま進めると、通知メールが無言で全滅します" >&2
  echo "!!!   - AmazonSSMManagedInstanceCore" >&2
  echo "!!!     SSH無しで運用するため、これが無いと以後の操作手段が無くなります" >&2
  echo "!!! 承知のうえ進める場合は SKIP_IAM_CHECK=1 を付けて実行。" >&2
  [ "${SKIP_IAM_CHECK:-0}" = "1" ] || exit 1
else
  echo "    アタッチ済みロール: ${IAM_ROLE}"
  echo "    ※ ses:SendRawEmail が実際に許可されているかは、"
  echo "      アタッチの有無では分からない。フェーズ1でメールを実送信して確認すること"
fi

echo "==> パッケージのインストール"
sudo dnf -y update
sudo dnf -y install \
  php8.4 php8.4-cli php8.4-fpm php8.4-mysqlnd php8.4-mbstring php8.4-xml \
  php8.4-gd php8.4-bcmath php8.4-intl php8.4-opcache php8.4-zip \
  mariadb1011-server mariadb1011 \
  httpd mod_ssl \
  nodejs24 nodejs24-npm \
  composer git certbot python3-certbot-apache

php -v
node -v

echo "==> MariaDB の起動"
sudo systemctl enable --now mariadb
echo "    初期設定がまだなら sudo mariadb-secure-installation を実行すること"

echo "==> PHP-FPM の起動"
# AL2023の /etc/httpd/conf.d/php.conf は .php を php-fpm のソケットへ渡す設定に
# なっているため、php-fpm が動いていないとPHPが実行されない
sudo systemctl enable --now php-fpm

echo "==> Apache の起動"
sudo systemctl enable --now httpd
sudo cp "${APP_DIR}/deploy/tsubasa.conf" /etc/httpd/conf.d/tsubasa.conf 2>/dev/null || \
  echo "    ${APP_DIR}/deploy/tsubasa.conf を /etc/httpd/conf.d/ に配置すること"

echo "==> ディレクトリ権限"
sudo mkdir -p "${APP_DIR}"
sudo chown -R apache:apache "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true

echo "==> キューワーカーの登録"
if [ -f "${APP_DIR}/deploy/tsubasa-queue.service" ]; then
  sudo cp "${APP_DIR}/deploy/tsubasa-queue.service" /etc/systemd/system/
  sudo systemctl daemon-reload
  sudo systemctl enable tsubasa-queue
fi

echo "==> スケジューラ(cron)"
echo "    以下を \`sudo crontab -u apache -e\` に追加する:"
echo "    * * * * * cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"

echo "==> TLS証明書"
echo "    sudo certbot --apache -d ${DOMAIN}"
echo "    certbot のパッケージは systemd タイマーで自動更新される:"
echo "    systemctl list-timers | grep certbot"

echo "==> 完了。次に deploy/deploy.sh を実行する"
