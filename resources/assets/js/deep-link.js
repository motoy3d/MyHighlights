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
 * 同じタップ（tapId）はどちらで受けても一度しか開かない。
 */
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

/**
 * アプリが前面に戻ったとき（通知のタップで iOS がアプリを前に出したとき）に、書き置きを読む。
 * AppNavigator.vue の mounted から一度だけ呼ぶ。
 */
export function installDeepLinkListeners(store) {
  const check = () => checkDeepLink(store);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      // 確認用：前面に戻ったとき通知センターに残っている通知(タップした通知が消えているか)
      if (DIAG && navigator.serviceWorker) {
        navigator.serviceWorker.getRegistration('/')
          .then((r) => (r ? r.getNotifications() : []))
          .then((list) => diag('page-visible', '', { n: list.length, tags: list.map((x) => x.tag).join(',').slice(0, 80) }))
          .catch(() => diag('page-visible', '', { n: 'err' }));
      }
      check();
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
