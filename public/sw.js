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
  event.waitUntil(Promise.all([
    self.clients.claim(),
    // 確認用アドレスでだけ、この端末が Declarative Web Push に関わる機能を持つかを記録する
    diag('sw-caps', '', {
      pe_notification: (typeof PushEvent !== 'undefined' && 'notification' in PushEvent.prototype) ? 1 : 0,
      n_navigate: (typeof Notification !== 'undefined' && 'navigate' in Notification.prototype) ? 1 : 0,
      pushnotification: ('onpushnotification' in self) ? 1 : 0,
    }),
  ]));
});

const DEEPLINK_MAILBOX = 'tsubasa-deeplink';
const DEEPLINK_KEY = '/__deeplink__';
const DIAG = self.location.hostname.startsWith('tsubasa-stg.');

function diag(step, tapId, extra) {
  if (!DIAG) return Promise.resolve();
  const q = new URLSearchParams(Object.assign({ diag: step, tap: tapId || '', t: Date.now() }, extra || {}));
  return fetch('/favicon.ico?' + q.toString(), { method: 'HEAD', cache: 'no-store', credentials: 'omit' })
    .catch(() => {});
}

// 通知を表示する。
//
// サーバは Declarative Web Push の形式 { web_push: 8030, notification: { title, body, navigate, tag, data: { url } ... } }
// で送る（app/Notifications/PushNotice.php）。
// - iOS 18.4 以降のホーム画面アプリ：iOS がこの形式を読んで通知を用意し、event.notification に入れて渡してくる。
//   ここで自分で showNotification すると iOS の通知が捨てられ、タップしたときに iOS が navigate へ移る仕組みも
//   効かなくなる（アプリがバックグラウンドだとタップの知らせが Service Worker に届かないため、移れなくなる）。
//   だから何もせず iOS に任せる。
// - それ以外（Android の Chrome など）：通常の push として届くので、notification を読んでここで表示する。
self.addEventListener('push', (event) => {
  if (event.notification) {
    // 通知を出さずに終わる(iOS が用意した通知がそのまま出る)。記録は waitUntil で確実に送る
    event.waitUntil(diag('sw-push-declarative', '', { nav: event.notification.navigate ? 1 : 0 }));
    return;
  }
  diag('sw-push-show', '', { keys: Object.keys(Object.getPrototypeOf(event)).join('.').slice(0, 60) });

  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (e) {
    // JSON でなければ本文として扱う
    payload = { body: event.data ? event.data.text() : '' };
  }
  // Declarative Web Push の形式なら中身は notification にある（古い形式にも対応しておく）
  const n = (payload.web_push === 8030 && payload.notification) ? payload.notification : payload;

  const title = n.title || 'Tsubasa⬆︎UP';
  const data = Object.assign({}, n.data || {});
  if (!data.url && n.navigate) {
    data.url = n.navigate;
  }
  const options = {
    body: n.body || '新しいお知らせがあります',
    icon: n.icon || '/appicon.png',
    badge: n.badge,
    // 同じ投稿へのコメントが続いても積み上がらないよう、tag で置き換える
    tag: n.tag,
    renotify: !!(n.tag && n.renotify),
    lang: n.lang,
    data,
  };

  // iOS は通知を表示しない push を続けると購読を取り消すので、必ず表示する
  event.waitUntil(self.registration.showNotification(title, options));
});

// 通知をタップしたら該当画面を開く。
//
// 主な経路：開いているアプリに「この画面を開いて」とアドレスごと伝え（postMessage）、
//   アプリが読み込み直さずにその画面を開く（deep-link.js）。起動していなければそのアドレスで開く（openWindow）。
// 控え：iPhone ではバックグラウンドのアプリに送った知らせを取りこぼすことがあるので、
//   開きたい画面を端末内にも書き置きし（Cache Storage を郵便受けとして使う。取得のキャッシュには使わない）、
//   アプリが前面に戻ったときに読む。どちらで開いても、同じタップ（tapId）は一度しか開かない。
//
// 2026-09-21 の実機確認で、タップ後に iPhone からの読み込みが1件も来なかった。
// どの段階で止まるかを確かめるため、確認用アドレスでだけ各段階をサーバのアクセス記録に残す（diag）。
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

  // iOS が navigate へ移る通知（Declarative Web Push）では、移動は iOS に任せる（ここでも開くと二重になる）
  if (event.notification.navigate) {
    event.waitUntil(diag('sw-click-declarative'));
    return;
  }

  const data = event.notification.data || {};
  const target = new URL(data.url || '/home?launcher=true', self.location.origin).href;
  const tapId = Math.random().toString(36).slice(2, 10);

  event.waitUntil((async () => {
    diag('sw-click', tapId);
    // 控えの書き置きは最初に済ませる（アプリが前面に出た瞬間に読みに来ても間に合うように）
    try {
      await leaveDeepLink(target, tapId);
    } catch (e) {
      diag('sw-mailbox-fail', tapId);
    }

    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const client = windows.find((c) => new URL(c.url).origin === self.location.origin);
    diag('sw-clients', tapId, { n: windows.length });

    if (!client) {
      diag('sw-openwindow', tapId);
      await self.clients.openWindow(target);
      return;
    }

    const message = { type: 'open-url', url: target, tapId };
    // 前面に出す処理が終わらない環境でも指示は届くよう、先に一度伝える
    client.postMessage(message);
    diag('sw-posted', tapId);
    try {
      await withTimeout(client.focus(), 3000);
      diag('sw-focused', tapId);
    } catch (e) {
      diag('sw-focus-fail', tapId, { e: String(e && e.message || e).slice(0, 40) });
    }
    // 前面に出た後にもう一度伝える（バックグラウンド中に送った分を取りこぼしていても開けるように）
    client.postMessage(message);
    diag('sw-posted2', tapId);
  })());
});
