# 本番移行チェックリスト

Amazon Linux 1 / PHP 7.1 / Laravel 5.6 から
Amazon Linux 2023 / PHP 8.4 / Laravel 13 へ切り替える際の手順と確認項目。

---

## ⚠️ 未実施の重要タスク

### [ ] 本番 `.env` との突き合わせ検証

**移行作業中、本番の `.env` の実物を確認できていない。** 想定した内容で
動くことは確認済みだが、実物との差異による影響は未検証のため、
切り替え前に必ず実施すること。

やること:

1. 現行サーバから `.env` を取得する（**`APP_KEY` を失うと暗号化済みデータを
   復号できなくなるので必ず退避する**）
2. 新サーバに配置し、以下で設定が意図通りに解決されるか確認する

   ```bash
   php artisan tinker --execute='
   foreach ([
     "app.name","app.env","app.url","app.timezone","app.debug",
     "session.driver","session.cookie","session.lifetime","session.same_site","session.secure",
     "cache.default","queue.default","queue.failed.driver","broadcasting.default",
     "mail.default","mail.mailers.smtp.host","mail.mailers.smtp.port","mail.from.address",
     "filesystems.default","filesystems.disks.local.root","filesystems.disks.public.url",
     "auth.guards.api.driver","auth.passwords.users.table",
     "tsubasa.line_notify.client_id","tsubasa.line_notify.callback_uri",
     "tsubasa.schedule_data_loading_months","tsubasa.timeline_load_posts",
   ] as $k) printf("%-38s %s\n", $k, var_export(config($k), true));'
   ```

3. 特に注意して見る項目

   | 項目 | 期待値・注意点 |
   | --- | --- |
   | `session.cookie` | `tsubasaup_session`。ASCIIのみであること |
   | `filesystems.disks.local.root` | `.../storage/app`（`app/private` ではない） |
   | `session.same_site` | `NULL`（`lax`だとLINE連携が壊れる） |
   | `auth.passwords.users.table` | `password_resets` |
   | `tsubasa.line_notify.*` | 3つとも値が入っていること |
   | `queue.default` | `sync` ならワーカーは動かない（後述） |
   | `mail.mailers.smtp.*` | host/port/username/password が入っていること |
   | `app.url` | `https://tsubasa.smartj.mobi`。Sanctumのstateful判定に使われる |

4. `MAIL_ENCRYPTION` は Laravel 13 では参照されなくなった。
   ポート587ならSTARTTLS、465なら暗黙TLSが自動選択される。
   現行の設定と実際の接続方式が合うか確認する

5. 旧キー名（`MAIL_DRIVER` / `QUEUE_DRIVER` / `CACHE_DRIVER` /
   `BROADCAST_DRIVER` / `FILESYSTEM_DRIVER`）はconfig側でフォールバック
   しているため、そのままでも動く。新キー名へ移すかは任意

---

## 事前準備

- [ ] 現行サーバの `.env` を退避（`APP_KEY` 必須）
- [ ] 現行DBのダンプを取得
- [ ] `storage/app/public` 配下のアップロード済みファイルを退避
      （投稿添付・コメント添付・プロフィール画像）
- [ ] DNSのTTLを短くしておく

## 新サーバ構築

- [ ] EC2 に Amazon Linux 2023 を用意
- [ ] `deploy/setup-al2023.sh` を実行
- [ ] MariaDB の初期設定（`mariadb-secure-installation`）とDB/ユーザー作成
- [ ] DBダンプをリストア
- [ ] `storage/app/public` のファイルをリストア
- [ ] `.env` を配置（上記の検証を実施）
- [ ] `deploy/tsubasa.conf` を `/etc/httpd/conf.d/` に配置
- [ ] `deploy/deploy.sh` を実行
- [ ] `sudo certbot --apache -d tsubasa.smartj.mobi`
- [ ] `systemctl list-timers | grep certbot` で自動更新を確認

## 切り替え前の動作確認

- [ ] ログイン / ログアウト
- [ ] タイムライン表示、投稿の作成・編集・削除
- [ ] **添付ファイルのアップロードと表示**（保存先が変わる回帰があった箇所）
- [ ] **画像添付のリサイズ**（1000px超の画像で確認）
- [ ] コメント投稿、いいね
- [ ] 予定の登録・編集・削除、カレンダー表示
- [ ] アンケートの作成・回答・CSVダウンロード
- [ ] メンバー招待（招待メールが届くこと）
- [ ] パスワード再設定メール
- [ ] **LINE Notify連携**（`same_site` の回帰があった箇所）
- [ ] iCal購読URLをカレンダーアプリに登録して表示
- [ ] 既存のアップロード済みファイルが表示できること

## 切り替え後

- [ ] **全ユーザーが一度ログアウトされる**ことを周知する
      （Laravel 7以降、暗号化Cookieの形式が変わったため、
      移行前に発行されたセッションCookieは復号検証に失敗する。回避不能）
- [ ] `storage/logs/laravel.log` にエラーが出ていないか確認
- [ ] `logs` テーブルにエラーが記録されていないか確認
      `SELECT * FROM logs WHERE level='error' ORDER BY id DESC LIMIT 20;`
- [ ] キューワーカーの状態確認 `systemctl status tsubasa-queue`
      ※ `.env` が `QUEUE_DRIVER=sync` の場合、通知はリクエスト内で
      同期実行されワーカーは何もしない。ワーカーを使うなら
      `QUEUE_CONNECTION=database` にする
- [ ] 旧サーバは証明書が失効しているため、切り替え完了まで
      新サーバ側で先に証明書を取得しておく

## 移行後に検討したいこと

- 古いiOS向けに `@vitejs/plugin-legacy` の導入を検討する
  （ViteはESモジュールを出力するため）
- Vue 2 はEOL。`vue-onsenui` 3.x でVue 3に上げる作業は別途
- `AppServiceProvider` が全SQLをログ出力する設定はそのまま残してある。
  ログ肥大化が問題なら `logging` を `daily` に変更する
- `/api/*` に付くCORSヘッダを絞るなら `config/cors.php` を用意する
