# Amazon Linux 2023 / PHP 8.4 / Laravel 13 への移行メモ

## 背景

移行前の構成は Amazon Linux 1 (2018.03) + PHP 7.1.33 + Laravel 5.6.40 だった。
いずれもEOLで、Let's Encrypt証明書の自動更新が止まって失効した件も、
この古い環境に起因している。

AL2023 のリポジトリには PHP 7.x が存在せず（8.1 / 8.2 / 8.3 / 8.4 / 8.5 のみ）、
Laravel 5.6 は PHP 8.0 以降では `ReflectionParameter::getClass()` の非推奨化により
起動時点で fatal になる。そのため OS だけを入れ替えることはできず、
PHP と Laravel を同時に上げる必要があった。

## 対応後の構成

| | 移行前 | 移行後 |
| --- | --- | --- |
| OS | Amazon Linux 1 (2018.03) | Amazon Linux 2023 |
| PHP | 7.1.33 | 8.4 |
| Laravel | 5.6.40 | 13.x |
| DB | MySQL | MariaDB 10.11 |
| APIの認証 | Laravel Passport | Laravel Sanctum |
| フロントのビルド | Laravel Mix 4 (webpack 4) | Vite |
| キューワーカー | supervisord | systemd |

Vue 2 / OnsenUI はそのまま据え置いている（後述）。

## 主な変更点

### スケルトン（Laravel 11以降の構成へ）

- `bootstrap/app.php` を新形式に書き換え、`app/Http/Kernel.php`、
  `app/Console/Kernel.php`、`app/Exceptions/Handler.php`、
  `RouteServiceProvider`、`BroadcastServiceProvider`、`EventServiceProvider` を廃止
- ミドルウェアの登録は `bootstrap/app.php` の `withMiddleware()` に集約。
  `EncryptCookies` / `TrimStrings` / `TrustProxies` / `VerifyCsrfToken` /
  `RedirectIfAuthenticated` は、フレームワーク標準のものへ設定だけ移した
- `app/Exceptions/Handler::report()` の「例外をlogsテーブルに記録する」処理は
  `app/Support/ExceptionLogger` に移設。**DB接続が切れている場合に
  例外ハンドラ自身が落ちて元の例外を握り潰す問題があったため、
  記録失敗時はそのまま通すようにした**
- `config/` は Laravel 13 の標準ファイルに置き換え。
  ただし `MAIL_DRIVER` / `QUEUE_DRIVER` / `CACHE_DRIVER` /
  `BROADCAST_DRIVER` / `FILESYSTEM_DRIVER` といった旧キー名を
  フォールバックとして読むようにしてあるので、**本番の .env はそのまま使える**

### 認証

- `Illuminate\Foundation\Auth\AuthenticatesUsers` などのトレイトは Laravel 6 で
  削除されたため、`LoginController` / `ForgotPasswordController` /
  `ResetPasswordController` を必要な処理だけ自前で実装し直した。
  ログイン試行のスロットリング（5回でロック）も従来どおり動作する
- 認証コントローラを書き直した都合で、`auth` / `guest` の指定を
  `routes/web.php` のルート単位に移した。
  なお `Illuminate\Routing\Controller::middleware()` は Laravel 13 にも残っており、
  `WithdrawalController` などコンストラクタで指定したままの箇所も動作する
  （テストで確認済み）
- Passport は同一オリジンSPA向けの `CreateFreshApiToken` にしか使っていなかったため、
  Sanctum の stateful 認証に置き換えた。JS側の変更は不要
- 登録用のルートもビューも存在せず、削除済みトレイトに依存していた
  `RegisterController` は削除した

### アプリコード

- ルートを文字列記法から `[Controller::class, 'method']` 記法へ変更
  （Laravel 8 で自動的な名前空間付与が廃止されたため）
- `str_random()` → `Str::random()`
- `eluceo/ical` 0.14 → 2.x（API全面変更）。カレンダー名・タイムゾーン・
  終日予定の扱いは従来と同じ出力になることを確認済み
- `intervention/image` 2.x → 3.x（`Image::make()` 廃止 → `ImageManager`）
- Faker → `fakerphp/faker`、ファクトリ/シーダーを Laravel 8 以降の形式へ
- `doctrine/dbal` と `fideloper/proxy` は Laravel 本体に取り込まれたため削除

### 移行中に見つかった不具合（併せて修正）

いずれも移行によって生じたものではなく、元から存在していたもの。

1. **`config:cache` を実行すると設定値が壊れる**
   コントローラやメール送信処理から `env()` を直接呼んでいた14箇所は、
   設定キャッシュを有効にすると `null` になる。`config()` 経由に変更し、
   アプリ固有の値は `config/tsubasa.php` に移した
2. **`AppServiceProvider` のSQLログでPHP 8では致命的エラーになる**
   バインド値を `preg_replace()` にそのまま渡していたため、日付(Carbon)や
   nullがバインドされたクエリで TypeError になる。文字列化してから渡すよう修正
3. **`Api\BlogController` の `FeedReader` が解決できない**
   名前空間内で未インポートのクラスを参照していたため、ブログRSSが
   設定されているチームでは常にエラーになっていた。import を追加
4. **`HomeController` が存在しないビュー `login` を返す**
   退会済みユーザがログイン状態で開いた場合に発生する。ログイン画面へ
   リダイレクトするよう修正
5. **マイグレーションのファイル名とクラス名の不一致**
   `create_quetionnaires.php` が `CreateQuestionnaires` を宣言しており、
   Laravel 11以降ではファイルが二重読み込みされて
   "Cannot redeclare" で `migrate` が止まる。無名クラス形式に変更した
   （本番の migrations テーブルに記録済みのためファイル名は変えていない）
6. **`$_SERVER` の未定義キー参照**
   `LogController` / `LogOperations` がUser-Agentなどを直接参照していたため、
   ヘッダのないリクエストで例外になり得た。`??` で保護

### 移行作業で一度混入させた不具合（レビューで検出して修正済み）

Laravel 13の標準 `config/*.php` をそのまま採用したことで既定値が変わり、
本番で実害が出る状態になっていた箇所。いずれも修正のうえ
`tests/Feature/ConfigInvariantTest.php` で値を固定して再発を防いでいる。

1. **セッションCookie名が変わり、しかも非ASCIIになっていた**
   Laravel 11標準は `Str::snake(APP_NAME).'_session'` で、
   `APP_NAME=Tsubasa⬆︎UP` から `tsubasa⬆︎_u_p_session` という
   非ASCII文字を含む名前になっていた。全ユーザーのログアウトに加え、
   Cookie名として不正で動作不定になる。移行前と同じ
   `tsubasaup_session` になるよう `Str::slug()` に戻した

2. **添付ファイルの保存先が変わっていた**
   Laravel 11で `local` ディスクの既定rootが `storage/app` から
   `storage/app/private` に変更されている。添付は
   `storePublicly('public/...')` で保存し `public/storage`
   シンボリックリンク経由で配信しているため、そのままでは
   画像リサイズが例外になり、新規添付が全て404になる。
   rootを `storage/app` に戻した

3. **SameSite=Lax でLINE Notify連携が壊れる**（その後LINE Notifyを廃止したため解消）
   Laravel 11標準の `same_site => 'lax'` では、LINE Notifyの
   `response_mode=form_post` による別サイトからのPOSTコールバックで
   セッションクッキーが送られなかった。一旦は未指定に戻していたが、
   LINE Notifyのサービス終了に伴い連携自体を削除したため、
   現在はLaravel標準の `lax` に戻している

4. **失敗ジョブが記録できない**
   Laravel 8以降の既定ドライバ `database-uuids` は `failed_jobs.uuid` に
   書き込むが、このテーブルはLaravel 5.6世代の定義で `uuid` 列が無い。
   ジョブ失敗時に「Unknown column 'uuid'」になる。
   列を追加するマイグレーションを足した

5. **セッションドライバの既定が database になっていた**
   `sessions` テーブルが無いため、`.env` に `SESSION_DRIVER` が
   無い環境では起動しない。既定を `file` に戻した

6. **php-fpm を起動していなかった**
   AL2023の `/etc/httpd/conf.d/php.conf` は `.php` を php-fpm の
   ソケットへ渡す設定になっているため、php-fpm が動いていないと
   PHPが実行されない。`setup-al2023.sh` に起動処理を追加した

### 移行作業で一度混入させた不具合（テストで検出して修正済み）

`config/app.php` を Laravel 13 の標準ファイルに差し替えた際、末尾にあった
アプリ独自のキーを取りこぼしていた。`line_notify_client_id` /
`line_notify_client_secret` / `line_notify_callback_uri` が失われ、
LINE Notify連携のclient_idがnullになる状態だった。
`config/tsubasa.php` の `line_notify` に移して復旧した。
（その後LINE Notifyのサービス終了に伴い連携ごと削除。
　未使用だった `test_ip` も復旧していない）

### フロントエンド

Laravel Mix は 6.0.49（2022年）で開発が止まっており、現在の webpack 5 では
内部APIの削除により動作しない。Laravel 13 標準の Vite に移行した。

- `resources/assets/js/app.js` の `require()` を `import` に変更（ViteはESMのみ）
- `app.scss` の webpack 固有の `~` プレフィックスを除去
- Blade の `mix()` を `@vite()` に変更
- 依存パッケージ数は 801 → 78 に減少

Vue 2 は据え置いた。`vue-onsenui` の Vue 3 対応版は存在するが、Vue 3 は
フィルタ機能を廃止しており、`app.js` の `Vue.filter('truncate')` をはじめ
`Vue.prototype` / `new Vue()` / Vuex 3→4 など SPA 全体（コンポーネント17個）の
書き換えが必要になるため、別作業とするのが妥当。

**Vite は ES モジュールを出力するため、非常に古い端末では動作しない。**
移行前の `webpack.mix.js` には「npm run prod だとiOS10でjsエラーが起きた」という
コメントがあった。古い端末の利用者がいる場合は `@vitejs/plugin-legacy` の
導入を検討すること。

## 検証内容

### 再現方法

動作確認用のデータは `DevelopmentSeeder` で投入できる。

```bash
php artisan migrate:fresh --seed
php artisan serve
# ログイン: test@example.com / password
# iCal:     /ical/ical-dev-0001
```

投入されるのは、チーム2件・ユーザ5件・メンバー5件・カテゴリ4件・
予定8件（時刻あり／終日／開始のみ の3パターンを含む）・投稿12件・コメント24件。
`app()->isProduction()` の場合は例外を投げて中断するため、本番では実行できない。

### 確認した項目

PHP 8.4 + MariaDB 10.11 で、`config:cache` などを有効にした本番同等の構成で確認した。

- 全42マイグレーションの実行
- `config:cache` / `route:cache` / `view:cache` / `event:cache` の生成
- ログイン、ログアウト、ログイン失敗時のスロットリング（5回でロック）
- `/home` の表示とチームCookieの発行
- セッションによるAPI認証（`/api/me`、`/api/teams` が200、未認証は401）
- iCal出力（時刻あり／終日／開始のみ の3パターン、VTIMEZONE付き）
- Vite の本番ビルドとBladeからの読み込み
- キューワーカーの起動、artisanコマンドの検出
- API一覧: `/api/me` `/api/posts`（ページング10件）`/api/schedules`
  `/api/members` `/api/teams` `/api/ical/config` が200、未認証は401

### 移行とは無関係の既存の挙動

`GET /api/schedules` は `month` パラメータが必須で、無しで呼ぶと
`Carbon::createFromFormat()` が例外を投げて500になる
（コード上も `//TODO validate` と書かれている）。SPAは常に `month` を
送るため実害は出ていないが、バリデーションを入れる余地はある。

### 自動テスト

移行前の `tests/Feature` は「ユーザーID 1と2が存在する」「`current_team_id`
Cookieがある」「ブログRSSが10件返る」といった、リポジトリに含まれていない
特定の開発用データに依存しており実行できなかった。
ファクトリで自己完結する形に全面的に書き直してある。

```bash
# テスト用DBを一度だけ作る
mysql -e 'CREATE DATABASE tsubasa_test'
./vendor/bin/phpunit
```

マイグレーションが `ALTER TABLE ... COMMENT` を使うためSQLiteは利用できず、
MySQL/MariaDBが必要。接続先は `phpunit.xml` の `DB_DATABASE` で
`tsubasa_test` に切り替えている（`RefreshDatabase` で毎回巻き戻す）。

アプリのエンドポイント50件を全てテストで叩いている（180テスト / 467アサーション）。

| 対象 | 内容 |
| --- | --- |
| 認証 | ログイン、退会済みユーザの拒否、5回でロック、ログアウト |
| パスワード再設定 | メール送信、トークン検証、再設定後の自動ログイン |
| 投稿 | 一覧/検索/カテゴリ/未読絞り込み、登録(アンケート・添付込み)、詳細、更新、削除 |
| コメント | 投稿、件数の増減、添付、通知ジョブ、削除権限 |
| いいね/既読 | 投稿・コメントへの反応とカウント |
| 予定 | 一覧、登録、終日予定、更新、削除 |
| 予定コメント | 一覧、投稿、通知ジョブ、削除権限 |
| メンバー | 一覧、招待あり/なしの登録、更新、管理者のみ退会可 |
| ユーザー | 自分の情報、氏名/カナ/メール/パスワード/通知フラグの更新 |
| アンケート | 回答、上書き、削除、集計、CSVダウンロード |
| iCal | カレンダー名、時刻あり/終日/開始のみ、VTIMEZONE、購読URL |
| その他 | チーム取得、ブログ、ホーム画面のCookie発行 |
| 複数チーム | 所属一覧、管理者フラグ、切り替えによるデータの入れ替わり、未読件数の独立、退会 |
| 運用ケース | 既存ユーザーの別チーム招待、退会メンバーの投稿と回答の扱い、ページング、重複いいね |
| ルート健全性 | 全ルートが実在するメソッドを指すこと、SPAが使う35エンドポイントの存在 |
| 設定不変条件 | セッションCookie名、ディスクroot、SameSite、失敗ジョブのuuid列など |

いずれのテストも「他チームのデータには触れない」ことを確認している。

### 移行にあたっての確認事項

- **キューワーカーは `QUEUE_CONNECTION` 次第で無意味になる**
  本番の `.env` が `QUEUE_DRIVER=sync` の場合、通知ジョブはリクエスト内で
  同期実行され、`tsubasa-queue` サービスは何もしない（移行前のsupervisordも
  同じ状態だった）。ワーカーを活かすなら `QUEUE_CONNECTION=database` にする
- **セッションCookieがSecureになる可能性がある**
  `session.secure` の既定が `false` から未指定に変わり、未指定の場合は
  リクエストがHTTPSかどうかで自動判定される。HTTPS運用なので問題ないが、
  明示するなら `.env` に `SESSION_SECURE_COOKIE=true` を入れる
- **`/api/*` にCORSヘッダが付くようになる**
  Laravel 11以降は `HandleCors` が標準のグローバルミドルウェアに入っている。
  既定は `Access-Control-Allow-Origin: *` かつ `supports_credentials: false` で、
  資格情報付きの読み取りはブラウザが拒否するため実害はない。
  絞りたい場合は `config/cors.php` を用意する
- **SQLログの量**
  `AppServiceProvider` が全クエリを `Log::info` で出力する設定は移行前からの
  ものをそのまま残している。`logging` の既定は単一ファイルなので、
  ログが肥大化する場合は `daily` への変更を検討する
- **APIはCSRFトークンを要求する**
  移行前はPassportの `TokenGuard` がJWTクッキー内のCSRF値を検証しており、
  移行後はSanctumの stateful 判定が `X-CSRF-TOKEN` を検証する。
  どちらもSPAが送っているヘッダで通るため挙動は同じ。
  実サーバでヘッダあり=成功／なし=419 を確認済み

### 複数チーム所属時の認可の穴（移行前からの問題・修正済み）

1人が複数チームに所属し、画面のプルダウンで切り替える機能を調査したところ、
チームをまたいだ認可に穴があった。**移行によって生じたものではなく
移行前からの挙動**だが、本番稼働前に修正した。

対象チームは `current_team_id` クッキーで決まる。このクッキーは
jsから読み書きするため暗号化対象外にしてあり、利用者がブラウザの
開発者ツールで任意の値に書き換えられる。サーバ側で「そのチームに
所属しているか」を検証していなかったため、以下が成立していた。

| 事象 | 修正前 | 修正後 |
| --- | --- | --- |
| 所属していないチームの投稿一覧が読める | 200 が返り他チームの投稿が全件見える | 所属チームに是正され、他チームの投稿は返らない |
| あるチームの管理者が、所属していない別チームのメンバーを退会させられる | 200 が返り退会日が入る | 404 |
| 所属していないチームのクッキーで `/api/me` | 500（null参照） | 200（所属チームの情報） |

修正内容:

- `App\Http\Middleware\EnsureCurrentTeamIsOwn` を追加し、
  `current_team_id` が所属チームを指しているか検証する。
  不正な値や未設定の場合は所属チームの1件目へ是正して処理を続ける
  （HomeControllerが以前から行っていた是正と同じ挙動。チームから外された
  利用者がタブを開いたままでもアプリが壊れないようにするため、エラーにしない）。
  api グループ全体と、クッキーを使う `questionnaire_download` に適用
- `MemberController::destroy` の管理者判定に `team_id` の条件を追加。
  コメントには「このチームの管理者でない場合は404」とあったが
  クエリが絞られていなかった
- `UserController::getMe` のメンバー未取得時の null 参照を回避

`tests/Feature/CrossTeamAuthorizationTest.php` で14件検証している。


### LINE Notify連携の削除

LINE Notify のサービス終了に伴い、関連コードを全て削除した。

- `LineNotifyController` とルート3本（`goto_line_auth`、`line_auth` のGET/POST）
- `PostNotificationJob` / `ScheduleNotificationJob` の `sendLINE()` / `postLINE()`
  （通知はメールのみになった）
- `UserController::updateLINENotificationFlg` とそのルート、
  `/api/me` の `line_notification_flg`
- 設定 `config/tsubasa.php` の `line_notify`、`.env.example` の `LINE_NOTIFY_*`
- SPA(`Settings.vue`)のLINE通知トグルと `public/img/LINE_APP.png`

これに伴い、`line_auth` がCSRF検証の唯一の除外だったため除外設定も不要になり、
別サイトからのPOSTを受けるエンドポイントが無くなった。
そのため Sanctum の `SameSite` 上書きを回避していたサブクラスを廃止し、
`statefulApi()` と `same_site => 'lax'` という Laravel/Sanctum 標準に戻している。

`users.line_notification_flg` と `users.line_access_token` の2カラムは
残してある（削除は不可逆なため）。不要であれば削除するマイグレーションを追加する。

### 実装の無いリソースルートを塞いだ

`Route::resource()` は実装が無いアクションのルートも生成するため、
呼ぶと500になるルートが5件あった（移行前からの状態）。
SPAからは呼ばれていないことを確認したうえで `->except()` で塞いだ。

- `GET /api/posts/{post}/edit`
- `GET /api/schedules/{schedule}` … ScheduleControllerに `show()` が無い
- `GET /api/schedules/{schedule}/edit`
- `GET /api/members/create` … コントローラ側がコメントアウト
- `GET /api/members/{member}/edit` … 同上

`tests/Feature/RouteIntegrityTest.php` で、
「全ルートが実在するコントローラメソッドを指していること」
「塞いだルートが復活していないこと」
「SPAが使う35エンドポイントが揃っていること」を検査している。
