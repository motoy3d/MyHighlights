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
- **数え方は画面の未読件数と完全に同じにする。** バッジ＝各チームの `unreadCount` の合計になり、画面とバッジがずれない

### 3.1 自分の投稿を未読にしない（既存の不具合の修正。#123 に含める）

既読が付くのは投稿の詳細を開いたとき（`GET /api/posts/{id}`）で、投稿した直後はタイムラインに戻るだけなので
詳細を開かない（`Post.vue` の `afterPost`）。そのため**自分で投稿すると自分の未読が1つ増える**。
今の画面の未読件数にも出ている既存の不具合で、バッジにもそのまま出てしまう。

数え方を変えるのではなく、**投稿を作るときに、投稿した人の既読も作る**（`PostController::store`）。

```php
PostResponse::create([
    'user_id' => Auth::id(), 'post_id' => $post->id,
    'read_flg' => true, 'like_flg' => false, 'star_flg' => false,
    'created_id' => Auth::id(), 'updated_id' => Auth::id(),
]);
```

過去の投稿の分は、**一度だけ既読を作るマイグレーション**で直す。

- 対象：`posts.created_id` が自分で、その人の `post_responses` がまだ無い投稿
- 既にある `post_responses`（いいね・スターだけ付けた行など）は触らない
- 1 本の `INSERT ... SELECT` で入れる（旧サーバのデータ量でも一度で終わる）
- 巻き戻し（`down`）では、このマイグレーションで作った行だけを消せないので何もしない（コメントに明記する）

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
  - 投稿の登録：投稿した人の既読が作られる／その人の `unreadCount` が増えない（§3.1）
  - マイグレーション：過去の自分の投稿に既読が入る／既にある `post_responses` を書き換えない
  - `UnreadCount`：複数チームの合計、退会したチームを含めない、参加前の投稿を含めない、既読を含めない
  - `UnreadCount` が各チームの `unreadCount` の合計と一致する
  - `GET /api/unread/total`：認証が要る、合計が正しい
  - 通知の中身：`data.badge` が受け取る人ごとの合計になっている
- Playwright（`tests/browser/specs/11-push.spec.js` に追加）
  - `navigator.setAppBadge` を差し替えて、起動時・前面復帰時・投稿を読んだ後に、正しい数で呼ばれることを確かめる
- 実機（iPhone）
  - 通知が届いたときにバッジが付く／投稿を読むと減る／未読が無くなると消える
  - 通知を許可していない場合に何も起きない（エラーにならない）

## 7. 作業の順番

0. 自分の投稿を未読にしない（§3.1。投稿時の既読と、過去の分のマイグレーション）
1. `UnreadCount` と `GET /api/unread/total`（テスト込み）
2. 通知の `data.badge`
3. Service Worker と画面の更新
4. 確認用サーバに出して実機で確認
