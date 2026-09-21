/**
 * 起動時のリンク処理（#9 の最小版。#110 の通知をタップしたときに該当画面を開く）
 *
 * 設計: docs/design/110-web-push.md §6.4 / §7.3.1
 *   投稿：/home?launcher=true&team={team_id}&post={post_id}
 *   予定：/home?launcher=true&team={team_id}&schedule={schedule_id}&date={YYYY-MM-DD}
 *   削除された予定：/home?launcher=true&team={team_id}&date={YYYY-MM-DD}
 *
 * Vue Router は使わず、起動時に URL のパラメータを読んで画面を開く。
 *
 * アプリが開いている（バックグラウンドにいる）状態で通知をタップした場合（installDeepLinkListeners）：
 *   主な経路：sw.js から「この画面を開いて」とアドレスごと届くので、読み込み直さずにその画面を開く
 *   控え：sw.js が同じ内容を Cache Storage に書き置きするので、知らせを取りこぼしても前面に戻ったときに読む
 *   最後の手段（iPhone / iPad だけ）：iPhone ではアプリがバックグラウンドだとタップが sw.js に届かないことがある
 *     （WebKit の既知の不具合 https://bugs.webkit.org/show_bug.cgi?id=268797 。上の2つがどちらも起きない）。
 *     そこで前面に戻ったとき、バックグラウンドにいた間にサーバが送った通知（GET /api/push/recent）と
 *     通知センターに残っている通知を比べ、1件だけ消えていればそれがタップされた通知とみなして開く
 *     （checkVanishedNotification）。控えを端末でなくサーバに置くのは、iOS では sw.js が保存したものを
 *     画面から読めなかったため（2026-09-22 実機で確認）。
 * 同じタップ（tapId。通知ごとの目印）はどの経路で受けても一度しか開かない。
 */
import axios from 'axios';
import Cookies from 'js-cookie';
import Article from './components/Article.vue';

const TAB_TIMELINE = 0;
// sw.js と合わせる
const DEEPLINK_MAILBOX = 'tsubasa-deeplink';
const DEEPLINK_KEY = '/__deeplink__';
// 書き置きが古すぎたら使わない（タップから時間が経って、関係ない場面で開かないように）
const DEEPLINK_MAX_AGE_MS = 5 * 60 * 1000;
// 確認用アドレスでだけ、どの段階まで進んだかをサーバのアクセス記録に残す（sw.js と同じ）
const DIAG = window.location.hostname.startsWith('tsubasa-stg.');
// 開いたタップ。知らせと書き置きの両方で届いても一度だけ開く
const handledTaps = new Set();
// 最後に開いたアドレスと時刻。経路が違っても、同じアドレスをこの間に二度は開かない
let lastOpened = null;
const SAME_OPEN_MS = 10 * 1000;

function diag(step, tapId, extra) {
  if (!DIAG) return;
  const q = new URLSearchParams(Object.assign({ diag: step, tap: tapId || '', t: Date.now() }, extra || {}));
  fetch('/favicon.ico?' + q.toString(), { method: 'HEAD', cache: 'no-store', credentials: 'omit' }).catch(() => {});
}
const TAB_CALENDAR = 1; // ブログのタブは 3 番目なので、カレンダーは常に 1

function params() {
  return new URLSearchParams(window.location.search);
}

function isId(value) {
  return !!value && /^\d+$/.test(value);
}

/**
 * チームの切り替え。Vue を作る前（最初の API 呼び出しより前）に呼ぶ。
 *
 * チームは current_team_id クッキーで決まり、API はすべてこれを見る。
 * API を呼ぶ前に書き換えておけば、画面の読み込み直しは要らない（＝繰り返し読み込む心配が無い）。
 * 所属していないチームが指定された場合は、サーバの EnsureCurrentTeamIsOwn が
 * 所属チームに戻したクッキーを返すので、ここでは検証しない。
 * チーム名のクッキーは /api/me の応答を見て AppNavigator.vue で合わせる。
 */
export function applyTeamFromUrl() {
  const team = params().get('team');
  if (!isId(team) || String(Cookies.get('current_team_id')) === team) {
    return;
  }
  Cookies.set('current_team_id', team);
}

/**
 * パラメータに応じて、開く画面の情報を store に入れる。
 * 画面を作る前（AppNavigator.vue の beforeCreate）に呼ぶ。
 * タブの切り替えと投稿の画面を積むのは、描画後の openFromUrl で行う。
 */
export function applyUrlToStore(store) {
  const p = params();
  const post = p.get('post');
  const date = p.get('date');
  const schedule = p.get('schedule');

  if (isId(post)) {
    store.commit('article/setPostId', Number(post));
  } else if (date && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
    // 予定を ID で取る API は無いので、カレンダーでその日を開く（Calendar.vue が見ている）
    store.commit('calendar/requestDate', {
      date,
      scheduleId: isId(schedule) ? Number(schedule) : null
    });
  }
}

/**
 * タブを切り替えて投稿を開き、パラメータを URL から消す。
 * AppNavigator.vue の mounted から呼ぶ。
 *
 * OnsenUI のタブバーは、読み込み直後に初期位置へ戻す処理を非同期で行う。
 * それより前にタブを切り替えると切り替えが取り消され、エラー（Canceled）になる。
 * （最初から目的のタブで作らせる方法は、vue-onsenui が初期値の属性を読まないため使えない）
 * そのため、画面の読み込みが終わってから少し待って切り替える。
 */
export function openFromUrl(store) {
  const p = params();
  const handled = ['team', 'post', 'schedule', 'date'].some((k) => p.has(k));
  if (!handled) {
    // アドレスにパラメータが無くても、通知のタップで開始 URL のまま起動した場合は書き置きがある
    afterLoad(() => checkDeepLink(store));
    return;
  }

  // 再読み込みで同じ画面が開き直さないように、パラメータはすぐに消す。
  // launcher=true はホーム画面からの起動の目印として既存の処理が見ているので残す
  const rest = p.get('launcher') === 'true' ? '?launcher=true' : '';
  window.history.replaceState(null, '', window.location.pathname + rest);

  // この起動で開くので、書き置きが残っていれば消す（前面に戻ったときに二重に開かないように）。
  // 消し終わる前に読み込み完了の pageshow などで書き置きを読んでしまわないよう、少しの間は読まない
  ignoreDeepLinkUntil = Date.now() + 3000;
  clearDeepLink();
  const open = () => openTarget(store, p);
  afterLoad(open);
}

/** 画面の読み込みが終わってから少し待って実行する（OnsenUI のタブバーの初期化を待つ。openFromUrl の説明参照） */
function afterLoad(fn) {
  const later = () => setTimeout(fn, 100);
  if (document.readyState === 'complete') {
    later();
  } else {
    window.addEventListener('load', later, { once: true });
  }
}

/** パラメータが指す画面を開く（投稿なら投稿の画面、日付ならカレンダー） */
function openTarget(store, p) {
  const post = p.get('post');
  const date = p.get('date');
  const schedule = p.get('schedule');
  if (isId(post)) {
    // タイムラインで投稿を開くのと同じ処理（Timeline.vue の openArticle）
    store.commit('article/setPostId', Number(post));
    store.commit('tabbar/setIndex', TAB_TIMELINE);
    store.commit('navigator/push', {
      extends: Article,
      onsNavigatorOptions: { animation: 'none' }
    });
  } else if (date && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
    store.commit('calendar/requestDate', {
      date,
      scheduleId: isId(schedule) ? Number(schedule) : null
    });
    store.commit('tabbar/setIndex', TAB_CALENDAR);
  }
}

/** sw.js の書き置きを取り出す（取り出したら消す）。無い・古いときは null */
async function takeDeepLink() {
  if (!('caches' in window)) {
    return null;
  }
  try {
    const cache = await caches.open(DEEPLINK_MAILBOX);
    const response = await cache.match(DEEPLINK_KEY);
    if (!response) {
      return null;
    }
    await cache.delete(DEEPLINK_KEY);
    const { url, at, tapId } = await response.json();
    if (!url || !at || Date.now() - at > DEEPLINK_MAX_AGE_MS) {
      return null;
    }
    return { url, tapId };
  } catch (e) {
    return null;
  }
}

function clearDeepLink() {
  if ('caches' in window) {
    caches.open(DEEPLINK_MAILBOX).then((cache) => cache.delete(DEEPLINK_KEY)).catch(() => {});
  }
}

/** 通知の画面を、読み込み直さずに開く。チームが違うときだけ、そのアドレスで読み込み直す */
function openDeepLinkInApp(store, urlString, tapId, via) {
  if (tapId) {
    if (handledTaps.has(tapId)) {
      return;
    }
    handledTaps.add(tapId);
  }
  const url = new URL(urlString, window.location.origin);
  if (url.origin !== window.location.origin) {
    return;
  }
  // タップが sw.js から届いた環境（Chrome など）では、前面に戻ったときの「消えた通知」でも同じ画面が見つかる。
  // 経路ごとに目印が違うので、同じアドレスを続けて開かないようにする
  if (lastOpened && lastOpened.href === url.href && Date.now() - lastOpened.at < SAME_OPEN_MS) {
    return;
  }
  lastOpened = { href: url.href, at: Date.now() };
  diag('page-open', tapId, { via });
  const team = url.searchParams.get('team');
  if (isId(team) && String(Cookies.get('current_team_id')) !== team) {
    // 表示中のデータは今のチームのものなので、読み込み直す（起動時の処理が開く）
    window.location.replace(url.href);
    return;
  }
  openTarget(store, url.searchParams);
}

let checking = false;
let ignoreDeepLinkUntil = 0;
/**
 * 書き置きがあれば開く。前面に戻った直後は sw.js の書き込みが終わっていないことがあるので、少し待って読み直す。
 * 前面に戻ったとき・フォーカス・sw.js からの知らせが同時に来ても、一度だけ開く（取り出したら消えるので）
 */
async function checkDeepLink(store) {
  if (checking) {
    return;
  }
  if (Date.now() < ignoreDeepLinkUntil) {
    clearDeepLink();
    return;
  }
  checking = true;
  try {
    for (const wait of [0, 400, 1200]) {
      if (wait) {
        await new Promise((resolve) => setTimeout(resolve, wait));
      }
      const found = await takeDeepLink();
      if (found) {
        diag('page-mailbox', found.tapId);
        openDeepLinkInApp(store, found.url, found.tapId, 'mailbox');
        return;
      }
    }
  } finally {
    checking = false;
  }
}

/** 通知センターに残っている、このアプリの通知（[{ nid, tag }]。nid はサーバが通知ごとに付ける目印） */
async function displayedNotifications() {
  const registration = await navigator.serviceWorker.getRegistration('/');
  const list = registration ? await registration.getNotifications() : [];
  return list.map((x) => ({ nid: (x.data && x.data.nid) || null, tag: x.tag }));
}

/**
 * 送った通知のうち、通知センターから消えたもの。
 * iOS は同じ tag の通知を置き換えずに並べるので、tag ではなく通知ごとの目印（nid）で比べる。
 * ただし同じ tag の新しい通知に置き換わって消えた場合（Chrome など）は、タップされたのではないので除く。
 */
function findVanished(arrived, notices, displayed) {
  const sentAt = new Map(notices.map((x) => [x.nid, x.at]));
  const nids = new Set(displayed.map((d) => d.nid));
  return arrived.filter((x) => {
    if (!x.nid) {
      return !displayed.some((d) => d.tag === x.tag);
    }
    if (nids.has(x.nid)) {
      return false;
    }
    const replaced = displayed.some((d) => d.tag === x.tag && (sentAt.get(d.nid) || 0) > x.at);
    return !replaced;
  });
}

/**
 * iPhone / iPad か。消えた通知から判断する方法は iOS の不具合への対策なので、iOS でだけ使う。
 * Android などはタップが sw.js に届くので要らず、使うとスワイプで消した通知を開いてしまう弱点だけが残る。
 * iPadOS の Safari は Mac と同じ UA を名乗るので、タッチ対応かどうかで見分ける。
 */
export function isAppleMobile() {
  const ua = navigator.userAgent || '';
  return /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
}

// バックグラウンドに回った時刻（端末の時計）。前面に戻ったら、この後に送られた通知だけを調べる
let hiddenAt = null;
// サーバの送った時刻がこれだけ前でも「バックグラウンドの間に届いた」とみなす（送ってから表示までの遅れと時計の誤差）
const SENT_MARGIN_MS = 5000;
let vanishChecking = false;
/**
 * バックグラウンドにいた間に届いた通知のうち、通知センターから消えたものを探し、1件だけならそれを開く。
 *
 * iOS は、タップされた通知を通知センターから消してからアプリを前面に出す。
 * 前面に戻った直後はまだ消えていないことがあるので、少し待って読み直す。
 * 2件以上消えていたら「すべて消去」などで消されたとみなし、開かない。
 * スワイプで1件消した後、アプリ切り替えから前面に戻したときも「消えた」と見分けがつかず開いてしまう
 * （タップで移れないよりはよいとして受け入れている）。
 */
async function checkVanishedNotification(store, since) {
  if (vanishChecking || !('serviceWorker' in navigator)) {
    return;
  }
  vanishChecking = true;
  try {
    const { data } = await axios.get('/api/push/recent');
    // サーバの時計に直す
    const from = since + (data.now - Date.now()) - SENT_MARGIN_MS;
    const notices = data.notices || [];
    const arrived = notices.filter((x) => x.at >= from);
    if (!arrived.length) {
      diag('page-vanished-none', '', { arrived: 0, total: notices.length });
      return;
    }
    for (const wait of [0, 400, 1200]) {
      if (wait) {
        await new Promise((resolve) => setTimeout(resolve, wait));
      }
      const displayed = await displayedNotifications();
      const vanished = findVanished(arrived, notices, displayed);
      if (!vanished.length) {
        diag('page-vanished-none', '', { wait, arrived: arrived.length, displayed: displayed.length });
        continue;
      }
      diag('page-vanished', '', { n: vanished.length, tag: vanished[0].tag });
      if (vanished.length === 1) {
        const x = vanished[0];
        openDeepLinkInApp(store, x.url, x.nid || (x.tag + '@' + x.at), 'vanished');
      }
      return;
    }
  } catch (e) {
    diag('page-vanished-fail', '', { e: String(e && e.message || e).slice(0, 40) });
  } finally {
    vanishChecking = false;
  }
}

/**
 * アプリが前面に戻ったとき（通知のタップで iOS がアプリを前に出したとき）に、書き置きを読む。
 * AppNavigator.vue の mounted から一度だけ呼ぶ。
 */
export function installDeepLinkListeners(store) {
  const check = () => checkDeepLink(store);
  const detectVanished = isAppleMobile();
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      hiddenAt = Date.now();
    } else if (document.visibilityState === 'visible') {
      diag('page-visible');
      check();
      if (detectVanished && hiddenAt !== null) {
        const since = hiddenAt;
        hiddenAt = null;
        checkVanishedNotification(store, since);
      }
    }
  });
  window.addEventListener('focus', check);
  window.addEventListener('pageshow', check);
  if ('serviceWorker' in navigator) {
    // 主な経路：sw.js からの「この画面を開いて」
    navigator.serviceWorker.addEventListener('message', (event) => {
      const data = event.data || {};
      if (data.type === 'open-url' && typeof data.url === 'string') {
        diag('page-message', data.tapId);
        clearDeepLink(); // 控えの書き置きは要らなくなった
        openDeepLinkInApp(store, data.url, data.tapId, 'message');
      }
    });
  }
}
