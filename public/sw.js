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
// アプリが開いていればそのウィンドウを使う（ウィンドウを増やさない）
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const data = event.notification.data || {};
  const target = new URL(data.url || '/home?launcher=true', self.location.origin).href;

  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const client = windows.find((c) => new URL(c.url).origin === self.location.origin);

    if (!client) {
      await self.clients.openWindow(target);
      return;
    }

    // 前面に出すのは先に行う（タップ直後でないと focus が許されない環境がある）
    try {
      await client.focus();
    } catch (e) {
      // 前面に出せなくても遷移は続ける
    }

    // 画面を読み込み直せば、起動時のリンク処理（deep-link.js）がパラメータを読んで該当画面を開く
    if ('navigate' in client) {
      try {
        await client.navigate(target);
        return;
      } catch (e) {
        // navigate できない（制御下にないウィンドウなど）ときは下の方法に回す
      }
    }
    // navigate が使えない環境では、画面側に遷移を頼む（push.js で受ける）
    client.postMessage({ type: 'open-url', url: target });
  })());
});
