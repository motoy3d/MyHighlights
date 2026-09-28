# つばさ用グループウェア Tsubasa⬆UP

サイボウズLiveに代わるグループウェアとして作成。
Webアプリケーション。PWA対応していく。基本的にスマホ向け。PCでも使用は可能。

## 機能
- 投稿、投稿一覧
- 予定登録、カレンダー表示
- ブログ表示
- メンバー登録、メンバー一覧

## 技術スタック
- AWS
- EC2 (Amazon Linux 2023)
- Apache 2.4
- PHP 8.4
- Laravel 13
- MariaDB 10.11
- OnsenUI
- Vue.js 2
- Playwright（ブラウザ自動テスト）
- PWA
- Vite
- composer
- npm
- Amazon SES
- Amazon S3

## 開発

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

# 動作確認用データつきでDBを作る (test@example.com / password でログインできる)
php artisan migrate:fresh --seed

# 別ターミナルで
npm run dev        # Vite開発サーバ
php artisan serve
```

## テスト

MySQL/MariaDB が必要（マイグレーションが `ALTER TABLE ... COMMENT` を使うため
SQLiteでは動かない）。テスト用DBを一度だけ作っておく。

```bash
mysql -e 'CREATE DATABASE tsubasa_phpunit'
./vendor/bin/phpunit
```

199件。DB名が `tsubasa_test` でないのは、旧サーバのデモ環境（廃止予定）が
その名前を使い続けるため（`phpunit.xml` で指定）。

画面を実際に動かすブラウザテストは `tests/browser/`（Playwright、33件）にある。

## デプロイ

`deploy/` 配下に Amazon Linux 2023 用の資材がある。

| ファイル | 用途 |
| --- | --- |
| `deploy/setup-al2023.sh` | 新しいEC2インスタンスの初期構築（一度だけ） |
| `deploy/deploy.sh` | 通常のデプロイ（`git pull` 後に実行） |
| `deploy/configure-runtime.sh` | 実行環境を旧サーバに揃える（タイムゾーン・php.ini・MariaDB・スワップ・certbotタイマー・キューワーカー登録）。`setup-al2023.sh` から呼ばれる |
| `deploy/tsubasa.conf` | Apache vhost。ACMEチャレンジをリダイレクト除外済み |
| `deploy/tsubasa-queue.service` | キューワーカーのsystemdユニット（旧supervisordの置き換え） |
| `deploy/ssm-run.sh` | SSM経由でコマンド／スクリプトをインスタンス上で実行する（SSHを使わない） |
| `deploy/fix-permissions.sh` | `storage` を root 所有にしてしまった場合の復旧 |
| `deploy/sync-attachments.sh` | 添付ファイルの同期（旧サーバ→S3→新サーバ。`--since` で差分） |
| `deploy/cutover-db.sh` | 切り替え当夜のDBフル取り込み（本番移行専用） |

TLS証明書は旧サーバの `/etc/letsencrypt` をそのまま持ち込む。
**`certbot --apache` は使わない**（vhostを書き換えてしまう）。
**AL2023 の certbot パッケージは systemd タイマーを同梱しない**ため、
`configure-runtime.sh` が `certbot-renew.timer`（毎日04時）を入れる。

```bash
systemctl list-timers certbot-renew.timer
sudo certbot renew --dry-run
```

## 本番移行

切り替え手順と確認項目は
[docs/PRODUCTION-CUTOVER-CHECKLIST.md](docs/PRODUCTION-CUTOVER-CHECKLIST.md)、
調査と判断の経緯は [docs/MIGRATION-PLAN.md](docs/MIGRATION-PLAN.md)（正本）にまとめてある。
本番 `.env` との突き合わせ検証、本番データでの自動テスト、当夜手順のリハーサルは実施済み。

## 移行に関するメモ

Amazon Linux 1 / PHP 7.1 / Laravel 5.6 からの移行内容は
[docs/MIGRATION-al2023.md](docs/MIGRATION-al2023.md) を参照。

## 連絡先
motoy3d@gmail.com
Twitter: @motoy3d
