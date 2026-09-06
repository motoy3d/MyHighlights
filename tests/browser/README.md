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
| `TSUBASA_RESOLVE` | (未設定) | `TSUBASA_URL` のホスト名をこのIPに向ける(Chromium の `--host-resolver-rules`)。本番vhost(`ServerName tsubasa.smartj.mobi`)をSSMポートフォワード越しに叩く時に使う。`/etc/hosts` は触らない。setup と chromium にだけ効く(mobile=WebKit は不可) |
| `TSUBASA_EMAIL` | `test@example.com` | ログインするアカウント |
| `TSUBASA_PASSWORD` | `password` | 同上 |
| `TSUBASA_MULTI_TEAM_EMAIL` | (未設定) | 複数チーム所属のアカウント |
| `TSUBASA_MULTI_TEAM_PASSWORD` | (未設定) | 同上 |

```bash
# フェーズ2（本番データを入れた新サーバ）
TSUBASA_EMAIL=xxx TSUBASA_PASSWORD=yyy npm test

# 当夜のスモークテスト（hostsで tsubasa.smartj.mobi を新IPに向けてから）
TSUBASA_URL=https://tsubasa.smartj.mobi TSUBASA_EMAIL=xxx TSUBASA_PASSWORD=yyy npm test

# 本番vhost(HTTPS)をポートフォワード越しに (443 -> localhost:8443)
aws ssm start-session --target i-0626d85c720708c64 \
  --document-name AWS-StartPortForwardingSession \
  --parameters '{"portNumber":["443"],"localPortNumber":["8443"]}'
TSUBASA_URL=https://tsubasa.smartj.mobi:8443 TSUBASA_RESOLVE=127.0.0.1 \
  TSUBASA_EMAIL=xxx TSUBASA_PASSWORD=yyy npx playwright test --project=chromium
# ※ サーバ側の APP_URL も https://tsubasa.smartj.mobi:8443 にしておく(Sanctum の stateful 判定)
# ※ テスト内の HTTP は page.request ではなく helpers/app.js の fetchInPage を使う
#    (page.request は Node 側なのでホスト名の差し替えが効かない)
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
| `05-security` | `.env` が読めないこと、**添付の `Content-Disposition` と `nosniff`**、CSRFトークン |
| `06-attachments` | **添付のアップロードと表示**、**1000px超の画像がリサイズされること**、非画像の添付、複数添付、既存ファイルの配信 |
| `07-schedules` | **予定の登録・編集・削除**、終日予定 |
| `08-comments-likes` | **コメントの投稿・削除**、**いいねと解除**、添付付きコメント |
| `09-questionnaire` | **アンケートの作成・回答・集計・CSV取得** |

`05-security` は Apache 設定に依存するため PHPUnit では検証できない項目。

`06-attachments` の「1000px超の画像がリサイズされる」は、
バックエンド側の同名テストが `post_attachments` の件数しか見ていないのに対し、
**実際のピクセル数を検証している**。

### テスト用のファイル

`fixtures/` に置いてある。PILに依存せず生成した単色PNG。

| ファイル | 用途 |
| --- | --- |
| `large-image.png` (1600x1200) | リサイズの検証 |
| `small-image.png` (200x150) | 通常の画像添付 |
| `sample-note.txt` | 非画像の添付 |

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

`/api/*` は **60リクエスト/分**（`config/tsubasa.php` の `api_rate_limit`）。
スイートを通しで回すと1つのIPからの合算で超える。

**移行のテスト中は `.env` で緩められる。**

```dotenv
API_RATE_LIMIT=600
```

緩めると 429 を踏まなくなり、スイートの所要時間も **5.4分 → 2.1分** に縮む。

> **切り替え当夜は `.env` から `API_RATE_LIMIT` を消すこと。**
> 本番の既定は60のまま。`ConfigInvariantTest` が60を超えていたら警告を出す。

緩められない環境では、`TSUBASA_PACE_MS`（既定5000）で
`gotoApp()` の最低間隔を空けて流量を抑えられる。
`TSUBASA_PACE_MS=0` で無効。

`gotoApp()` と `openPostByTitle()` は429を踏んだら
`Retry-After` の分だけ待って自動で再試行する。

> アプリ側に429のハンドリングが無く、axios の interceptor も無いため、
> 踏むと画面が「ごめんなさい。エラーになりました」になるだけで
> 原因が分かりにくい。Vue 3 化のタイミングで共通の
> エラーハンドリングを入れるとよい。

### 更新系が遅い場合はキュー設定を疑う

`PostNotificationJob` は**1通ごとに `sleep(1)`**（予定通知は `sleep(2)`）。
SESの送信レートに配慮した意図的な待ち。

**封じ込めで `QUEUE_CONNECTION=sync` にすると、この待ちが
HTTPリクエスト内で走る。** 20人に通知する投稿が1件20秒かかり、
テストがタイムアウトする（実際に踏んだ）。

フェーズ2の封じ込めは **`QUEUE_DRIVER=database` + ワーカー停止 +
`MAIL_MAILER=log`** にすること。通知は `jobs` に積まれるだけで実行されず、
書き込みは速いまま。当夜にどうせ `TRUNCATE jobs` する。

### DOMのid重複に注意### DOMのid重複に注意

`<v-ons-page id="post">` は **7コンポーネント**
（EditPost / AddSchedule / EditSchedule / Member / AddMember / ICal / Post）で、
`<form id="postForm">` は **4コンポーネント**で重複している。
OnsenUIは全ページをDOMに残すため、同時に複数存在しうる。

そのため `#post` や `#postForm` だけでは画面を特定できない。
`#addScheduleForm` のような一意なもの、または
「表示されている要素」（`:visible`）で絞ること。

同じ理由で、FABもタブごとにDOM上に存在する。
`ons-page:has(...)` は入れ子の外側にも一致するので、
`ons-fab:visible` で特定する。

### 更新系テストはデータを作る

`02-timeline` の「投稿を作成して削除できる」は実際に投稿を作り、
最後に削除する。途中で失敗すると投稿が残るので、
本番データに対して流したあとは残骸を確認すること。

```sql
SELECT id, title FROM posts WHERE title LIKE '自動テスト投稿%';
```
