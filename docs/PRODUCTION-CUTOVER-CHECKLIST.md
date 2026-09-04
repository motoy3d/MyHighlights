# 本番移行チェックリスト

作業の進め方（フェーズ構成・当夜のタイムライン・切り戻し）は
`docs/MIGRATION-PLAN.md` を参照。こちらは項目単位の確認事項。

Amazon Linux 1 / PHP 7.1 / Laravel 5.6 から
Amazon Linux 2023 / PHP 8.4 / Laravel 13 へ切り替える際の手順と確認項目。

---

## ✅ 本番 `.env` との突き合わせ検証（実施済み）

本番の `.env` の実物を受領し、これを配置した状態で設定解決と
自動テスト197件を実行して確認した。**結果として、切り替え前に
手を打つ必要がある項目が4つ見つかった**（次節）。

`.env` そのものはリポジトリに含めない。`APP_KEY` を失うと
暗号化済みデータを復号できなくなるので、退避を最優先で行うこと。

再実行する場合:

```bash
php artisan optimize:clear
php artisan tinker --execute='
foreach ([
  "app.name","app.env","app.url","app.timezone","app.locale","app.debug",
  "session.driver","session.cookie","session.lifetime","session.same_site","session.secure",
  "sanctum.stateful",
  "cache.default","queue.default","queue.failed.driver","broadcasting.default",
  "mail.default","mail.from.address","services.ses",
  "filesystems.default","filesystems.disks.local.root","filesystems.disks.public.url",
  "logging.default","logging.channels.stack.channels","logging.channels.single.level",
  "auth.guards.api.driver","auth.passwords.users.table",
  "tsubasa.schedule_data_loading_months","tsubasa.timeline_load_posts",
] as $k) printf("%-42s %s\n", $k,
  is_scalar(config($k))||is_null(config($k)) ? var_export(config($k), true)
                                             : json_encode(config($k), JSON_UNESCAPED_UNICODE));'
./vendor/bin/phpunit
```

### 問題なかった項目

| 項目 | 解決値 | 補足 |
| --- | --- | --- |
| `app.url` | `https://tsubasa.smartj.mobi` | 実URLと一致。Sanctumの401ループは起きない |
| `sanctum.stateful` | `tsubasa.smartj.mobi` を含む | `SANCTUM_STATEFUL_DOMAINS` 未設定で自動追加された |
| `app.timezone` | `Asia/Tokyo` | **`.env` に `APP_TIMEZONE` が無い**が、移行時に既定値を `Asia/Tokyo` にしてあるため9時間ずれない |
| `app.locale` | `ja` | 同上（既定値を `ja` にしてある） |
| `session.cookie` | `tsubasaup_session` | ASCIIのみ。旧Laravelと同じ生成式なので名前も変わらない |
| `session.same_site` | `lax` | LINE連携削除によりLaravel標準に戻した値 |
| `filesystems.disks.local.root` | `.../storage/app` | `app/private` ではない（回帰なし） |
| `filesystems.disks.public.url` | `https://tsubasa.smartj.mobi/storage` | 既存添付のURLが変わらない |
| `auth.passwords.users.table` | `password_resets` | 旧テーブル名を維持 |
| `mail.default` / `cache.default` / `broadcasting.default` | `ses` / `file` / `log` | 旧キー名（`MAIL_DRIVER` 等）のフォールバックが効いている |
| `queue.failed.driver` | `database-uuids` | 追加した uuid 列のマイグレーションで対応済み |
| `config:cache` の安全性 | — | `config/` の外に `env()` 呼び出しが無いことを確認（キャッシュ後に `null` 化する箇所は無い） |
| 自動テスト | 197件成功 | 本番 `.env` を配置した状態で実行 |

### ⚠️ 切り替え前に対応が必要な項目

#### 1. SESがEC2のIAMロールに依存している（メールが全滅する可能性）

`SES_KEY` と `SES_SECRET` が**空**のまま `MAIL_DRIVER=ses` になっている。
Laravelは資格情報が空の場合、明示的なcredentialsを渡さずSDKに任せるため、
**EC2インスタンスプロファイル（IAMロール）から資格情報を取得している。**

```php
// Illuminate\Mail\MailManager::addSesCredentials()
if (! empty($config['key']) && ! empty($config['secret'])) { ... }  // 空なので通らない
```

→ **EC2インスタンス作成時に、次の2つを含むIAMロールを必ずアタッチする**
（付け忘れ防止のため、`deploy/setup-al2023.sh` は
IAMロールが付いていない場合に停止するようにしてある）。

- SES送信権限（`ses:SendRawEmail`）
- `AmazonSSMManagedInstanceCore` — SSH無しでSSM経由の操作を行うため
移行先は本番と同じAWSアカウントなので、SESの検証済みドメインは
そのまま使える。
付け忘れると、招待メール・パスワード再設定・投稿通知が
**画面上はエラーにならないまま全て失敗する**（キュー経由のため
`failed_jobs` に積まれるだけで、利用者にも管理者にも見えない）。

確認方法（新サーバで、切り替え前に）:

```bash
php artisan tinker --execute='
  Mail::raw("SES疎通確認", fn($m) => $m->to("自分のアドレス")->subject("test"));
  echo "送信呼び出し完了\n";'
```

`SES_REGION=us-east-1` は現行で稼働している値なので**変えないこと**
（`.env` 内にコメントアウトされたSMTP設定は `ap-northeast-1` だが、
稼働しているのはSES APIの `us-east-1` 側）。

#### 2. `QUEUE_DRIVER=database` — キューワーカーが必須

本番は `sync` ではなく `database`。つまり通知メールは
すべて `jobs` テーブル経由で送られる。

→ **`tsubasa-queue` が起動していないと、通知メールが一切飛ばない**
（エラーにもならず `jobs` に溜まり続ける）。
切り替え後の必須確認項目にすること。

```bash
systemctl status tsubasa-queue
mysql -e "SELECT COUNT(*) FROM tsubasa.jobs;"   # 増え続けていないこと
```

#### 3. `DB_HOST=localhost` — UNIXソケット接続になる

MySQL/MariaDBのクライアントは `localhost` を指定するとTCPではなく
UNIXソケットで接続する。そのため新サーバでは次の2つが一致している必要がある。

- MariaDBのソケットパスと、PHPの `pdo_mysql.default_socket`
- DBユーザーが **`'tsubasa'@'localhost'`** で作られていること

`'tsubasa'@'%'` だけでは接続できない。
（検証環境でも `@'%'` のみでは `Access denied for user 'tsubasa'@'localhost'`
になることを実際に確認した。）

`DB_HOST=127.0.0.1` に変更する手もあるが、その場合はDBユーザーも
`'tsubasa'@'127.0.0.1'` で作り直す必要がある。**現行と揃えるなら
`localhost` のまま、`@'localhost'` のユーザーを作るのが安全。**

#### 4. ログが1ファイルに無限に増える

`LOG_CHANNEL=stack`（→ `single`）で `LOG_LEVEL` は未設定（＝`debug`）。
加えて `AppServiceProvider` が**全SQLを `Log::info` で出力している**
（移行前からの実装）。ローテーションもされないため、
`storage/logs/laravel.log` が単調に増え続ける。

新サーバのディスクを圧迫するので、次のいずれかを入れること。

```dotenv
LOG_CHANNEL=daily      # 日次ローテーション（既定14日保持）
# または
LOG_LEVEL=warning      # SQLログ(info)ごと抑止する
```

### 整理してよい項目（動作には影響しない）

| キー | 理由 |
| --- | --- |
| `LINE_NOTIFY_CLIENT_ID` / `_SECRET` / `_CALLBACK_URI` | サービス終了。コードから削除済みで未参照 |
| `TEST_IP` | コード上どこからも参照されていない |
| `MIX_PUSHER_*` | Vite移行により `MIX_` プレフィックスは無効（`VITE_` が新しい接頭辞）。`BROADCAST_DRIVER=log` なので実害なし |
| `APP_FAKER_LOCALE` | テストデータ生成用。本番では不要 |

### 推奨（任意）

| キー | 現状 | 推奨 |
| --- | --- | --- |
| `SESSION_SECURE_COOKIE` | 未設定（`null`） | `true`。HTTPS運用なので明示すべき。現状でもリクエストがHTTPSなら実質secureになるが、暗黙に依存しない方がよい |

### `APP_URL` と Sanctum の関係（検証中に実際に踏んだ）

   SanctumはリクエストのReferer/Originが `sanctum.stateful` の一覧に
   一致する場合だけ、セッションCookieでの認証を有効にする。
   一覧の既定値は `localhost` などに加えて **`APP_URL` のホスト:ポート**
   が自動で追加されたものになっている。

   そのため `APP_URL` が実際のURLとずれていると、ログインは成功するのに
   直後の `/api/me` などが全て401を返し、SPAが `/login` へ飛ばす →
   ログイン済みなので `/home` へ戻される、というリダイレクトループになる。
   画面上は「ログインできないアプリ」に見えるので原因が分かりにくい。

   `tests/Feature/ConfigInvariantTest.php` で
   「`APP_URL` のオリジンが `sanctum.stateful` に含まれること」を
   検査しているので、本番の `.env` を新サーバに置いた状態で
   `./vendor/bin/phpunit --filter ConfigInvariantTest` を実行すると
   このずれを事前に検出できる。

### 旧キー名について

旧キー名（`MAIL_DRIVER` / `QUEUE_DRIVER` / `CACHE_DRIVER` /
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
- [ ] **チーム切り替え**（複数チーム所属者で、切り替え後に
      投稿・予定・メンバーが入れ替わること）
- [ ] iCal購読URLをカレンダーアプリに登録して表示
- [ ] 既存のアップロード済みファイルが表示できること
- [ ] **添付ファイルのレスポンスヘッダ**（自動テスト不可 / Apache設定のため）
      ```
      curl -sI https://tsubasa.smartj.mobi/storage/<既存の添付ファイル> \
        | grep -i 'content-disposition\|x-content-type-options'
      ```
      → `Content-Disposition: attachment` と
        `X-Content-Type-Options: nosniff` の両方が返ること。
      返らない場合は `mod_headers` が有効か確認する
      (`httpd -M | grep headers`)。
      これが無いと、HTMLやSVGを添付された際に同一オリジンで
      スクリプトが実行される（Stored XSS）
- [ ] **添付が壊れていないこと**（上記ヘッダ追加による副作用の確認）
      画像添付がタイムライン上でサムネイル表示される /
      非画像添付がGoogle Docs Viewerで開ける /
      ダウンロードリンクで保存できる
- [ ] **ログイン / パスワード再設定 / 退会画面の表示崩れが無いこと**
      （OnsenUIをCDNからバンドルに移したため。ヘッダが青く、
      ログインボタンが青いボタンとして描画されていればOK。
      枠線の無い素のテキストとリンクに見える場合はOnsenUIが
      効いていない）
- [ ] **OnsenUIのCDN廃止の確認**（DevToolsのNetworkタブで
      `cdnjs.cloudflare.com` へのリクエストが1件も無いこと）

## 切り替え後

- [ ] **全ユーザーが一度ログアウトされる**ことを周知する
      （Laravel 7以降、暗号化Cookieの形式が変わったため、
      移行前に発行されたセッションCookieは復号検証に失敗する。回避不能）
- [ ] `storage/logs/laravel.log` にエラーが出ていないか確認
- [ ] `logs` テーブルにエラーが記録されていないか確認
      `SELECT * FROM logs WHERE level='error' ORDER BY id DESC LIMIT 20;`
- [ ] **キューワーカーの状態確認** `systemctl status tsubasa-queue`
      本番は `QUEUE_DRIVER=database` なので**ワーカーは必須**。
      止まっていると通知メールがエラーにもならず `jobs` に溜まり続ける
      `SELECT COUNT(*) FROM jobs;` が増え続けていないことも見る
- [ ] **SES送信の疎通確認**（IAMロールが付いていないと全て失敗する）
      テスト用アドレス宛にパスワード再設定メールを送り、実際に届くこと。
      `SELECT COUNT(*) FROM failed_jobs;` が0のままであること
- [ ] `certbot renew --dry-run` が成功すること
      （EIP付け替え後は新サーバがドメインのIPを持つため、
      HTTP-01のまま更新できる。ここを確認しないと今回の失効を繰り返す）

## 移行後に検討したいこと

- LINE Notify はサービス終了に伴いコードから削除済み。
  `users.line_notification_flg` と `users.line_access_token` の
  2カラムは残してあるので、不要なら削除するマイグレーションを追加する

- 古いiOS向けに `@vitejs/plugin-legacy` の導入を検討する
  （ViteはESモジュールを出力するため）
- Vue 2 はEOL。`vue-onsenui` 3.x でVue 3に上げる作業は別途
- `AppServiceProvider` が全SQLをログ出力する設定はそのまま残してある。
  ログ肥大化が問題なら `logging` を `daily` に変更する
- `/api/*` に付くCORSヘッダを絞るなら `config/cors.php` を用意する
