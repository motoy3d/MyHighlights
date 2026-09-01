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
mysql -e 'CREATE DATABASE tsubasa_test'
./vendor/bin/phpunit
```

## デプロイ

`deploy/` 配下に Amazon Linux 2023 用の資材がある。

| ファイル | 用途 |
| --- | --- |
| `deploy/setup-al2023.sh` | 新しいEC2インスタンスの初期構築（一度だけ） |
| `deploy/deploy.sh` | 通常のデプロイ（`git pull` 後に実行） |
| `deploy/tsubasa.conf` | Apache vhost。ACMEチャレンジをリダイレクト除外済み |
| `deploy/tsubasa-queue.service` | キューワーカーのsystemdユニット（旧supervisordの置き換え） |

TLS証明書は certbot で取得する。AL2023 の certbot パッケージは systemd タイマーで
自動更新されるため、`systemctl list-timers | grep certbot` で有効なことを確認しておく。

```bash
sudo certbot --apache -d tsubasa.smartj.mobi
```

## 本番移行

切り替え手順と確認項目は
[docs/PRODUCTION-CUTOVER-CHECKLIST.md](docs/PRODUCTION-CUTOVER-CHECKLIST.md) にまとめてある。
**本番 `.env` との突き合わせ検証が未実施**なので、切り替え前に必ず実施すること。

## 移行に関するメモ

Amazon Linux 1 / PHP 7.1 / Laravel 5.6 からの移行内容は
[docs/MIGRATION-al2023.md](docs/MIGRATION-al2023.md) を参照。

## 連絡先
motoy3d@gmail.com
Twitter: @motoy3d
