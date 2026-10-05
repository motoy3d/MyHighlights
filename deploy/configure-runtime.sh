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
#       innodb_buffer_pool_size 旧512M/新128M (logs 440万行がある)、
#       sql_mode に ONLY_FULL_GROUP_BY / NO_ZERO_IN_DATE / NO_ZERO_DATE が無い
#       (旧は7年これで運用。無いと '0000-00-00' の挿入が通ってしまう)
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
# 旧サーバと同じ sql_mode。既定では ONLY_FULL_GROUP_BY / NO_ZERO_IN_DATE /
# NO_ZERO_DATE が抜けており、旧なら弾かれるゼロ日付が通ってしまう
sql_mode = ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION
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

echo "==> スワップ 2GB (/swapfile)"
# 旧 t3.medium は実負荷で 3.7GB を使い切りスワップしていた。新 t4g.medium も 4GB で、
# スワップ無しだと瞬間的な増加で OOM killer が mariadb を落とす。保険として 2GB 置く
if [ ! -f /swapfile ]; then
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile >/dev/null
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
swapon -a 2>/dev/null || true
grep -q '^vm.swappiness' /etc/sysctl.d/99-tsubasa.conf 2>/dev/null || echo 'vm.swappiness = 10' > /etc/sysctl.d/99-tsubasa.conf
sysctl -q -p /etc/sysctl.d/99-tsubasa.conf

echo "==> tsubasa-queue.service"
cp "${APP_DIR}/deploy/tsubasa-queue.service" /etc/systemd/system/tsubasa-queue.service

systemctl daemon-reload
systemctl enable certbot-renew.timer tsubasa-queue >/dev/null 2>&1
systemctl start certbot-renew.timer
# tsubasa-queue はここでは起動しない(本番切替の手順で起動する)
systemctl restart mariadb php-fpm httpd

echo "==> 確認（旧サーバと一致しない場合はここで止める）"
fail=0
check() {  # check <名前> <実際> <期待>
  if [ "$2" = "$3" ]; then printf "  OK   %-22s %s\n" "$1" "$2"
  else printf "  NG   %-22s %s (期待: %s)\n" "$1" "$2" "$3"; fail=1; fi
}
check タイムゾーン "$(date +%Z)" "JST"
check upload_max_filesize "$(php -r 'echo ini_get("upload_max_filesize");')" "20M"
check post_max_size       "$(php -r 'echo ini_get("post_max_size");')" "20M"
check memory_limit        "$(php -r 'echo ini_get("memory_limit");')" "256M"
check date.timezone       "$(php -r 'echo ini_get("date.timezone");')" "Asia/Tokyo"
check character_set_server "$(mysql -N -e 'SELECT @@character_set_server')" "utf8mb4"
check system_time_zone     "$(mysql -N -e 'SELECT @@system_time_zone')" "JST"
check innodb_buffer_pool  "$(mysql -N -e 'SELECT @@innodb_buffer_pool_size/1048576')" "512.0000"
check sql_mode "$(mysql -N -e 'SELECT @@sql_mode')" \
  "ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION"
check swap "$(swapon --show=SIZE --noheadings | head -1 | tr -d ' ')" "2G"
check certbot_timer "$(systemctl is-active certbot-renew.timer)" "active"
check queue_unit "$(systemctl is-enabled tsubasa-queue)" "enabled"

# DBとアプリの時刻が一致すること(TIMESTAMP列が9時間ずれた原因の再発防止)
DB_T=$(mysql -N -e "SELECT UNIX_TIMESTAMP()")
OS_T=$(date +%s)
if [ "$(( DB_T > OS_T ? DB_T - OS_T : OS_T - DB_T ))" -le 2 ]; then
  printf "  OK   %-22s DB と OS の時刻が一致\n" "時刻の整合"
else
  printf "  NG   %-22s DB=%s OS=%s\n" "時刻の整合" "$DB_T" "$OS_T"; fail=1
fi

[ "$fail" = "0" ] || { echo "==> 旧サーバと一致しない項目がある。上記 NG を直すこと"; exit 1; }
echo "==> 完了"
