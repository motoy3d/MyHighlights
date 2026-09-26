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

const DEEPLINK_MAILBOX = 'tsubasa-deeplink';
const DEEPLINK_KEY = '/__deeplink__';

// 通知を表示する。
//
// サーバは Declarative Web Push の形式 { web_push: 8030, notification: {...}, mutable: true } で送る
// （app/Notifications/PushNotice.php）。mutable なので iOS も push を Service Worker に渡してくる。
// 通知はどの環境でもここで表示する。iOS に表示を任せると、アプリがバックグラウンドのときにタップしても
// 目的の画面に移れず（WebKit の既知の不具合 https://bugs.webkit.org/show_bug.cgi?id=268797 ）、
// 自分で表示した通知でなければ、前面に戻ったときにどれがタップされたかを getNotifications() で調べられないため
// （resources/assets/js/deep-link.js の checkVanishedNotification）。
self.addEventListener('push', (event) => {
  let payload = null;
  try {
    payload = event.data ? event.data.json() : null;
  } catch (e) {
    // JSON でなければ本文として扱う
    payload = { body: event.data ? event.data.text() : '' };
  }
  // Declarative Web Push の形式なら中身は notification にある（古い形式にも対応しておく）
  let n = (payload && payload.web_push === 8030 && payload.notification) ? payload.notification : (payload || {});
  if (!payload && event.notification) {
    n = { title: event.notification.title, body: event.notification.body, tag: event.notification.tag,
      navigate: event.notification.navigate, data: event.notification.data };
  }

  const title = n.title || 'Tsubasa⬆︎UP';
  const data = Object.assign({}, n.data || {});
  if (!data.url && n.navigate) {
    data.url = n.navigate;
  }
  // この通知の目印。タップの重複を防ぐ ID（tapId）としても使う。
  // サーバが付けた nid があればそれを使い、画面側の「消えた通知」の判定と同じ目印にそろえる
  data.id = data.nid || Math.random().toString(36).slice(2, 10);
  const options = {
    body: n.body || '新しいお知らせがあります',
    icon: n.icon || '/appicon.png',
    badge: n.badge,
    // 同じ投稿へのコメントが続いても積み上がらないよう、tag で置き換える
    tag: n.tag || data.id,
    renotify: !!(n.tag && n.renotify),
    lang: n.lang,
    data,
  };

  // iOS は通知を表示しない push を続けると購読を取り消すので、必ず表示する。
  // アイコンのバッジ(まだ見ていないお知らせの数。#123)も、アプリを開いていなくてもここで更新する
  event.waitUntil(Promise.all([
    self.registration.showNotification(title, options),
    setBadge(data.badge),
    tellNoticeArrived(),
  ]));
});

// 開いている画面に「通知が届いた」と知らせる(🔔の数を取り直させる。#125)
async function tellNoticeArrived() {
  const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  windows.forEach((client) => client.postMessage({ type: 'notice-arrived' }));
}

// アイコンのバッジを更新する。対応していない端末や失敗しても、通知の表示は止めない
function setBadge(count) {
  if (typeof count !== 'number' || !('setAppBadge' in self.navigator)) {
    return Promise.resolve();
  }
  return self.navigator.setAppBadge(count).catch(() => {});
}

// 通知をタップしたら該当画面を開く。
//
// 主な経路：開いているアプリに「この画面を開いて」とアドレスごと伝え（postMessage）、
//   アプリが読み込み直さずにその画面を開く（deep-link.js）。起動していなければそのアドレスで開く（openWindow）。
// 控え：iPhone ではバックグラウンドのアプリに送った知らせを取りこぼすことがあるので、
//   開きたい画面を端末内にも書き置きし（Cache Storage を郵便受けとして使う。取得のキャッシュには使わない）、
//   アプリが前面に戻ったときに読む。どちらで開いても、同じタップ（tapId）は一度しか開かない。
// iPhone でアプリがバックグラウンドのときはタップ自体がここに届かない（WebKit bug 268797）。
// その場合は deep-link.js が、前面に戻ったときに通知センターから消えた通知を探して開く。
async function leaveDeepLink(url, tapId) {
  const cache = await caches.open(DEEPLINK_MAILBOX);
  await cache.put(DEEPLINK_KEY, new Response(JSON.stringify({ url, tapId, at: Date.now() }), {
    headers: { 'Content-Type': 'application/json' }
  }));
}

function withTimeout(promise, ms) {
  return Promise.race([promise, new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), ms))]);
}

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const data = event.notification.data || {};
  const target = new URL(data.url || '/home?launcher=true', self.location.origin).href;
  const tapId = data.id || Math.random().toString(36).slice(2, 10);

  event.waitUntil((async () => {
    // 控えの書き置きは最初に済ませる（アプリが前面に出た瞬間に読みに来ても間に合うように）
    try {
      await leaveDeepLink(target, tapId);
    } catch (e) {
      // 書き置きできなくても、知らせと openWindow で開ける
    }

    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const client = windows.find((c) => new URL(c.url).origin === self.location.origin);

    if (!client) {
      await self.clients.openWindow(target);
      return;
    }

    const message = { type: 'open-url', url: target, tapId };
    // 前面に出す処理が終わらない環境でも指示は届くよう、先に一度伝える
    client.postMessage(message);
    try {
      await withTimeout(client.focus(), 3000);
    } catch (e) {
      // 前面に出せなくても、次の知らせか書き置きで開く
    }
    // 前面に出た後にもう一度伝える（バックグラウンド中に送った分を取りこぼしていても開けるように）
    client.postMessage(message);
  })());
});
