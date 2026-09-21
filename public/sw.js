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
// 表示した通知の控え（deep-link.js が、前面に戻ったときに消えた通知＝タップされた通知を探すのに使う）
const SHOWN_KEY = '/__shown__';
const SHOWN_MAX = 30;
const SHOWN_MAX_AGE_MS = 3 * 24 * 60 * 60 * 1000;
const DIAG = self.location.hostname.startsWith('tsubasa-stg.');

function diag(step, tapId, extra) {
  if (!DIAG) return Promise.resolve();
  const q = new URLSearchParams(Object.assign({ diag: step, tap: tapId || '', t: Date.now() }, extra || {}));
  return fetch('/favicon.ico?' + q.toString(), { method: 'HEAD', cache: 'no-store', credentials: 'omit' })
    .catch(() => {});
}

// 通知を表示する。
//
// サーバは Declarative Web Push の形式 { web_push: 8030, notification: {...}, mutable: true } で送る
// （app/Notifications/PushNotice.php）。mutable なので iOS も push を Service Worker に渡してくる。
// 通知はどの環境でもここで表示する。iOS に表示を任せると、アプリがバックグラウンドのときにタップしても
// 目的の画面に移れず（WebKit の既知の不具合 https://bugs.webkit.org/show_bug.cgi?id=268797 ）、
// 自分で表示した通知でなければ、前面に戻ったときにどれがタップされたかも調べられないため。
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
  // この通知の目印。タップの重複を防ぐ ID（tapId）としても使う
  data.id = Math.random().toString(36).slice(2, 10);
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

  // iOS は通知を表示しない push を続けると購読を取り消すので、必ず表示する
  event.waitUntil(Promise.all([
    self.registration.showNotification(title, options),
    rememberShown({ id: data.id, tag: options.tag, url: data.url || '', at: Date.now() }).catch(() => {}),
    diag('sw-push-show', data.id, { tag: options.tag, declarative: event.notification ? 1 : 0 }),
  ]));
});

// 表示した通知の控えを足す。同じ tag は置き換わった通知なので古い方を消す
async function rememberShown(entry) {
  await updateShown((list) => list.filter((x) => x.tag !== entry.tag).concat(entry));
}

async function forgetShown(id) {
  await updateShown((list) => list.filter((x) => x.id !== id));
}

async function updateShown(fn) {
  const cache = await caches.open(DEEPLINK_MAILBOX);
  const response = await cache.match(SHOWN_KEY);
  let list = [];
  try {
    list = response ? await response.json() : [];
  } catch (e) {
    list = [];
  }
  const now = Date.now();
  list = fn(Array.isArray(list) ? list : []).filter((x) => now - x.at < SHOWN_MAX_AGE_MS).slice(-SHOWN_MAX);
  await cache.put(SHOWN_KEY, new Response(JSON.stringify(list), { headers: { 'Content-Type': 'application/json' } }));
}

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

  const data = event.notification.data || {};
  const target = new URL(data.url || '/home?launcher=true', self.location.origin).href;
  const tapId = data.id || Math.random().toString(36).slice(2, 10);

  event.waitUntil((async () => {
    diag('sw-click', tapId);
    // タップが届いたので、前面に戻ったときの「消えた通知」探しの対象から外す
    if (data.id) {
      await forgetShown(data.id).catch(() => {});
    }
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
