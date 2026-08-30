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
- コントローラのコンストラクタでの `$this->middleware()` は Laravel 11 で
  廃止されたため、`auth` / `guest` の指定を `routes/web.php` に移した
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

PHP 8.4 + MariaDB 10.11 で以下を確認した。

- 全42マイグレーションの実行
- `config:cache` / `route:cache` / `view:cache` / `event:cache` の生成
- ログイン、ログアウト、ログイン失敗時のスロットリング（5回でロック）
- `/home` の表示とチームCookieの発行
- セッションによるAPI認証（`/api/me`、`/api/teams` が200、未認証は401）
- iCal出力（時刻あり／終日／開始のみ の3パターン、VTIMEZONE付き）
- Vite の本番ビルドとBladeからの読み込み
- キューワーカーの起動、artisanコマンドの検出

### 既知の未対応

`tests/Feature` の41テストは通らない。これは移行による退行ではなく、
テストが「ユーザーID 1と2が存在する」「`current_team_id` Cookieがある」
「ブログRSSが10件返る」といった、リポジトリに含まれていない特定の
開発用データに依存しているため。
また `UserControllerTest` の3件は `PUT /api/users/updateName` を叩いているが、
ルートは以前から `POST` のみで、テスト側が古いままになっている。
