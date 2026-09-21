/*
 * Service Worker（#110 Web プッシュ通知）
 *
 * 設計: docs/design/110-web-push.md §6.1
 * - Vite のビルド対象にしない。URL を /sw.js に固定し、スコープを / にするため
 * - 扱うのは push と notificationclick だけ。
 *   fetch は扱わない（キャッシュしない）。キャッシュを入れると古い画面が残る問題が起きやすいため
 */

// 新しい sw.js を配ったら、待たずに入れ替える（キャッシュを持たないので入れ替えても困らない）
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// 通知を表示する。
// サーバ（laravel-notification-channels/webpush の WebPushMessage::toArray()）から
// { title, body, icon, badge, tag, renotify, data: { url } } の形で届く
self.addEventListener('push', (event) => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (e) {
    // JSON でなければ本文として扱う
    payload = { body: event.data ? event.data.text() : '' };
  }

  const title = payload.title || 'Tsubasa⬆︎UP';
  const options = {
    body: payload.body || '新しいお知らせがあります',
    icon: payload.icon || '/appicon.png',
    badge: payload.badge,
    // 同じ投稿へのコメントが続いても積み上がらないよう、tag で置き換える
    tag: payload.tag,
    renotify: !!(payload.tag && payload.renotify),
    data: payload.data || {},
  };

  // iOS は通知を表示しない push を続けると購読を取り消すので、必ず表示する
  event.waitUntil(self.registration.showNotification(title, options));
});

// 通知をタップしたら該当画面を開く。
//
// 開いたアプリに「このアドレスを読み込み直せ」と指示する方法（WindowClient.navigate / postMessage）は、
// iPhone のホーム画面アプリがバックグラウンドにいると効かなかった（2026-09-21 の実機確認。
// タップしてもアプリが前面に出るだけで、サーバへの読み込みが1件も来なかった）。
// そこで、開きたい画面を端末内に書き置きし（Cache Storage を郵便受けとして使う。取得のキャッシュには使わない）、
// アプリ側が前面に戻ったとき・起動したときにそれを読んで開く（deep-link.js）。
// 書き置きさえ残せば、iOS がアプリを前面に出すだけで目的の画面に移れる。
const DEEPLINK_MAILBOX = 'tsubasa-deeplink';
const DEEPLINK_KEY = '/__deeplink__';

async function leaveDeepLink(url) {
  const cache = await caches.open(DEEPLINK_MAILBOX);
  await cache.put(DEEPLINK_KEY, new Response(JSON.stringify({ url, at: Date.now() }), {
    headers: { 'Content-Type': 'application/json' }
  }));
}

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const data = event.notification.data || {};
  const target = new URL(data.url || '/home?launcher=true', self.location.origin).href;

  event.waitUntil((async () => {
    // アプリが前面に出る前に書き置きを済ませる（前面に出た瞬間にアプリが読みに来る）
    try {
      await leaveDeepLink(target);
    } catch (e) {
      // 書き置きできなくても、アプリを前面に出すことは続ける
    }

    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const client = windows.find((c) => new URL(c.url).origin === self.location.origin);

    if (!client) {
      // アプリが起動していなければ、そのアドレスで開く（起動時のリンク処理が開く。
      // 開始 URL で開かれても、起動時に書き置きを読む）
      await self.clients.openWindow(target);
      return;
    }
    try {
      await client.focus();
    } catch (e) {
      // 前面に出せない環境でも、書き置きは次にアプリが前面に出たときに読まれる
    }
    // 既に前面にいて visibilitychange が起きない場合に備えて、読みに来るよう知らせる
    client.postMessage({ type: 'deeplink' });
  })());
});
