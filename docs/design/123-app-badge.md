# #123 アプリのアイコンに未読件数のバッジを出す 設計

関連：#110（Web プッシュ通知）、設計書 `docs/design/110-web-push.md`

## 1. 目的

ホーム画面に追加したアプリのアイコンに未読の件数を出し、アプリを開かなくても気づけるようにする。
読めば減り、未読が無くなれば消える。

## 2. 使う仕組み

Badging API（`navigator.setAppBadge(n)` / `navigator.clearAppBadge()`）。

- iOS・iPadOS **16.4 以降**と Android（Chrome）で使える
- iOS の条件：**ホーム画面に追加していること**、**通知を許可していること**
  （[WebKit の解説](https://webkit.org/blog/14112/badging-for-home-screen-web-apps/)）。#110 で通知を使う人は満たす
- **Service Worker からも呼べる**ので、アプリを開いていなくても通知の受信時に更新できる
- `setAppBadge(0)` は `clearAppBadge()` と同じ（バッジが消える）
- 対応していない端末では、呼んでも何も起きない（`'setAppBadge' in navigator` で確認してから呼ぶ）

`app_badge`（Declarative Web Push の項目）は使わない。#110 で通知は Service Worker が表示する形にしており
（`mutable: true`）、そこで `setAppBadge` を呼ぶほうが Android とも同じ扱いにできるため。
使っているライブラリ（`laravel-notification-channels/webpush` 13.x）にも `app_badge` を組み立てる仕組みは無い。

## 3. 何を数えるか

**在籍している全チームの、未読の投稿の合計。** アイコンは1つなので、チームごとに分けない。

未読の定義は、今の投稿一覧（`GET /api/posts` の `unreadCount`）と同じにする。

- そのチームの投稿のうち、`post_responses` に自分の既読（`read_flg = 1`）が無いもの
- 自分がそのチームに参加した後（`members.created_at` より後）に作られた投稿
- 対象のチームは、在籍中（`members.withdrawal_date` が NULL、論理削除されていない）のもの（`PushRecipients` と同じ判定）

決めたこと：

- **予定・コメントは数えない。** 既読という状態を持っていないため。バッジは投稿の未読だけを表す
- **自分の投稿は数えない。** 投稿の詳細を開くと既読が付く作りなので、自分の投稿は投稿した時点では未読のまま残る。
  数えると自分の投稿でバッジが増えて紛らわしいので、作成者が自分の投稿は除く（今の `unreadCount` との差分）

## 4. サーバ側

### 4.1 合計を出す

`app/Support/UnreadCount.php`（新設）

```php
UnreadCount::totalFor(User $user): int   // 在籍中の全チームの未読投稿の合計
```

- クエリは1本にまとめる（チームごとに投げない）
- 上限は設けない。表示は端末に任せる（iOS は大きい数字を丸めて出す）

### 4.2 通知に載せる

`PushNotice::toWebPush()` の `data` に `badge` を足す（受け取る人ごとに違うので `$notifiable` から出す）。

```json
"data": { "url": "...", "nid": "...", "badge": 3 }
```

- 通知の宛先は `PushNotificationJob` が1人ずつ送るので、人ごとの値を入れられる
- 予定やコメントの通知でも、そのときの未読投稿の合計を載せる（バッジは常に今の合計を表す）

### 4.3 画面から取る

| メソッド | パス | 返すもの |
| --- | --- | --- |
| GET | `/api/unread/total` | `{ total: 3 }` |

投稿一覧の `unreadCount` は今見ているチームの分なので、合計は別に返す。

## 5. クライアント側

### 5.1 Service Worker（`public/sw.js`）

`push` で通知を表示するときに、`data.badge` があればバッジを更新する。

```js
if ('setAppBadge' in navigator) { navigator.setAppBadge(data.badge); }
```

### 5.2 画面（`resources/assets/js`）

次のときに `GET /api/unread/total` を読んでバッジを合わせる。

- アプリの起動時
- 前面に戻ったとき（`visibilitychange`。#110 の通知のタップの処理と同じ場所）
- 投稿を読んだとき（既読を付けた直後。今の `unreadCount` を減らす処理に合わせる）
- ログアウト時は消す（`clearAppBadge`。#110 で購読を消しているのと同じ場所）

合計が 0 なら消す。通知を許可していない人にも呼んでよい（iOS では何も起きない）。

## 6. テスト

- PHPUnit
  - `UnreadCount`：複数チームの合計、退会したチームを含めない、参加前の投稿を含めない、自分の投稿を含めない、既読を含めない
  - `GET /api/unread/total`：認証が要る、合計が正しい
  - 通知の中身：`data.badge` が受け取る人ごとの合計になっている
- Playwright（`tests/browser/specs/11-push.spec.js` に追加）
  - `navigator.setAppBadge` を差し替えて、起動時・前面復帰時・投稿を読んだ後に、正しい数で呼ばれることを確かめる
- 実機（iPhone）
  - 通知が届いたときにバッジが付く／投稿を読むと減る／未読が無くなると消える
  - 通知を許可していない場合に何も起きない（エラーにならない）

## 7. 作業の順番

1. `UnreadCount` と `GET /api/unread/total`（テスト込み）
2. 通知の `data.badge`
3. Service Worker と画面の更新
4. 確認用サーバに出して実機で確認
