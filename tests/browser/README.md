# ブラウザ自動テスト

画面を実際に動かして確認する回帰テスト。PHPUnit の197件は
**すべてバックエンド(HTTP/API)のテスト**で、Vueコンポーネントを
1つも検証していない。そこを埋めるためのもの。

用途は2つ。

1. **フェーズ2の参照系・更新系テスト**（本番データに対する回帰確認）
2. **切り替え後の Vue 3 化の安全網**

アプリ本体のビルドとは切り離してあり、独立した `package.json` を持つ。
`deploy.sh` の `npm ci` には巻き込まれない。

## 準備

```bash
cd tests/browser
npm install
npm run install-browsers      # Chromium と WebKit
```

## 実行

新サーバはインバウンドを開けていないので、SSMのポートフォワード越しに叩く。

```bash
# 別ターミナルでトンネルを張っておく
aws ssm start-session --target <インスタンスID> \
  --document-name AWS-StartPortForwardingSession \
  --parameters '{"portNumber":["80"],"localPortNumber":["8080"]}'

# テスト実行
cd tests/browser
npm test                      # PC + モバイル(iPhone Safari相当)
npx playwright test --project=chromium    # PCだけ
npm run report                # 結果をブラウザで見る
```

### 接続先と認証情報

すべて環境変数。リポジトリには何も置かない。

| 変数 | 既定値 | 用途 |
| --- | --- | --- |
| `TSUBASA_URL` | `http://localhost:8080` | 接続先 |
| `TSUBASA_EMAIL` | `test@example.com` | ログインするアカウント |
| `TSUBASA_PASSWORD` | `password` | 同上 |
| `TSUBASA_MULTI_TEAM_EMAIL` | (未設定) | 複数チーム所属のアカウント |
| `TSUBASA_MULTI_TEAM_PASSWORD` | (未設定) | 同上 |

```bash
# フェーズ2（本番データを入れた新サーバ）
TSUBASA_EMAIL=xxx TSUBASA_PASSWORD=yyy npm test

# 当夜のスモークテスト（hostsで tsubasa.smartj.mobi を新IPに向けてから）
TSUBASA_URL=https://tsubasa.smartj.mobi TSUBASA_EMAIL=xxx TSUBASA_PASSWORD=yyy npm test
```

**チーム切り替えのテストは `TSUBASA_MULTI_TEAM_EMAIL` を設定しないと skip される。**
移行で認可の穴を塞いだ箇所なので、フェーズ2では必ず設定して流すこと。

## 何を見ているか

| ファイル | 内容 |
| --- | --- |
| `01-auth` | ログイン画面の描画、OnsenUIがCDNでなくバンドルから効いているか、誤パスワード、ログイン、ログアウト、未認証リダイレクト |
| `02-timeline` | 投稿一覧、投稿を開く、**投稿の作成と削除**、タブ移動でJSエラーや5xxが出ないこと |
| `03-calendar` | 当月表示（年月がAsia/Tokyoとずれていないこと）、前月・翌月移動、カレンダー描画 |
| `04-members-teams` | メンバー一覧、設定画面、**チーム切り替えで表示が入れ替わること** |
| `05-security` | `.env` が読めないこと、**添付の `Content-Disposition: attachment` と `nosniff`**、CSRFトークン |

`05-security` は Apache 設定に依存するため PHPUnit では検証できない項目。

## セレクタの方針

Vue 2 → Vue 3 の移行後もそのまま通ることを狙って、
**利用者に見える文字列**と**テンプレートに書かれた `id`** だけを使う。
OnsenUI が内部生成するクラス名（`tabbar__item` など）には依存しない。
依存すると、UIライブラリを入れ替えた瞬間に全部落ちて、
本当の不具合と区別が付かなくなる。

## 既知の注意点

### SSMポートフォワード経由は遅い

トンネルが詰まって単発で落ちることがある。アプリの問題ではないので
`retries: 1` を設定してある。**2回続けて落ちるなら本物として扱ってよい。**

各テストが毎回ログインするとトンネルを叩きすぎるため、
`auth.setup.js` で1回だけログインして Cookie を使い回している。

### レート制限(429)

`bootstrap/app.php` の `throttleApi('60,1')` で **60リクエスト/分**。
テストを連続で回すと当たることがある。利用者1人では当たらない水準なので
アプリの不具合ではないが、**アプリ側に429のハンドリングが無い**ため
未処理のPromise拒否になり、画面が黙って更新されないだけになる。

`watchPageErrors()` は429由来を `errors.rateLimited` に分離している。

> axios の interceptor が無く、429も500もネットワーク断も
> すべて同じ「黙って失敗」になる。移行中に踏んだ
> 「`/api/*` が全て500だがログインはできる」が読みにくかったのも同じ理由。
> Vue 3 化のタイミングで共通のエラーハンドリングを入れるとよい。

### 更新系テストはデータを作る

`02-timeline` の「投稿を作成して削除できる」は実際に投稿を作り、
最後に削除する。途中で失敗すると投稿が残るので、
本番データに対して流したあとは残骸を確認すること。

```sql
SELECT id, title FROM posts WHERE title LIKE '自動テスト投稿%';
```
