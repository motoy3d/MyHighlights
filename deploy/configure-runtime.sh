#!/usr/bin/env bash
#
# 実行環境を旧サーバ(Amazon Linux 1 / PHP 7.1 / MySQL 5.7)と揃える。
# setup-al2023.sh から呼ばれるが、単体で何度実行しても同じ結果になる。
#
# 点検(2026-09-06)で見つかった旧サーバとの差分を潰す:
#   - OSタイムゾーン: 旧 JST / 新 UTC
#       MariaDB の time_zone=SYSTEM なので、DB側の CURRENT_TIMESTAMP や
#       NOW() が9時間ずれ、Laravel(Asia/Tokyo)が書く値と食い違う。
#       実データで logs.log_timestamp と created_at が9時間ずれていた。
#   - php.ini: upload_max_filesize 旧20M/新2M, post_max_size 旧20M/新8M,
#       memory_limit 旧256M/新128M。2MB超の写真投稿が新サーバで失敗する。
#   - MariaDB: character_set_server 旧utf8mb4/新latin1,
#       innodb_buffer_pool_size 旧512M/新128M (logs 440万行がある)
#   - certbot の自動更新が無い(旧は root cron)
#   - tsubasa-queue.service が未インストール
#
set -euo pipefail
APP_DIR=${APP_DIR:-/var/www/MyHighlights}

echo "==> タイムゾーン Asia/Tokyo"
timedatectl set-timezone Asia/Tokyo

echo "==> php.ini 上書き (/etc/php.d/99-tsubasa.ini)"
cat > /etc/php.d/99-tsubasa.ini <<'INI'
; 旧サーバ(PHP 7.1)の値に合わせる。deploy/configure-runtime.sh が管理。
upload_max_filesize = 20M
post_max_size = 20M
memory_limit = 256M
date.timezone = Asia/Tokyo
INI

echo "==> MariaDB 上書き (/etc/my.cnf.d/99-tsubasa.cnf)"
cat > /etc/my.cnf.d/99-tsubasa.cnf <<'CNF'
# 旧サーバ(MySQL 5.7)の値に合わせる。deploy/configure-runtime.sh が管理。
[mysqld]
character-set-server = utf8mb4
collation-server = utf8mb4_general_ci
innodb_buffer_pool_size = 512M
CNF

echo "==> certbot 自動更新 (systemd timer)"
# AL2023 の certbot rpm は timer を同梱しない。旧サーバの root cron と同じ内容。
cat > /etc/systemd/system/certbot-renew.service <<'UNIT'
[Unit]
Description=Renew Let's Encrypt certificates
After=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/bin/certbot renew --quiet --deploy-hook '/usr/bin/systemctl reload httpd'
StandardOutput=append:/var/log/certbot-renew.log
StandardError=append:/var/log/certbot-renew.log
UNIT
cat > /etc/systemd/system/certbot-renew.timer <<'UNIT'
[Unit]
Description=Daily Let's Encrypt renewal check

[Timer]
OnCalendar=*-*-* 04:00:00
RandomizedDelaySec=1h
Persistent=true

[Install]
WantedBy=timers.target
UNIT

echo "==> tsubasa-queue.service"
cp "${APP_DIR}/deploy/tsubasa-queue.service" /etc/systemd/system/tsubasa-queue.service

systemctl daemon-reload
systemctl enable certbot-renew.timer tsubasa-queue >/dev/null 2>&1
systemctl start certbot-renew.timer
# tsubasa-queue はここでは起動しない(本番切替の手順で起動する)
systemctl restart mariadb php-fpm httpd

echo "==> 確認"
date +%Z
php -r 'foreach(["upload_max_filesize","post_max_size","memory_limit","date.timezone"] as $k) echo "  $k=".ini_get($k)."\n";'
mysql -N -e "SELECT CONCAT('  system_time_zone=',@@system_time_zone,' charset=',@@character_set_server,' buffer_pool=',@@innodb_buffer_pool_size/1024/1024,'M')"
systemctl list-timers certbot-renew.timer --no-pager | head -2
echo "  tsubasa-queue: $(systemctl is-enabled tsubasa-queue) / $(systemctl is-active tsubasa-queue)"
