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
 *   iPhone / iPad：アプリがバックグラウンドだとタップが sw.js に届かない
 *     （WebKit の既知の不具合 https://bugs.webkit.org/show_bug.cgi?id=268797 。上の2つがどちらも起きない）。
 *     そこで前面に戻ったとき、バックグラウンドにいた間に届いてまだ開いていない通知（POST /api/push/recent）を
 *     帯で知らせる（checkArrivedWhileAway）。iOS では sw.js が保存したものも通知センターの中身も
 *     画面から見えない（2026-09-22 / 09-26 実機で確認）ので、控えはサーバに置く。
 * 同じタップ（tapId。通知ごとの目印）はどの経路で受けても一度しか開かない。
 */
import axios from 'axios';
import Cookies from 'js-cookie';
import Article from './components/Article.vue';
import { markNoticeOpened } from './push.js';
import { showNoticeBanner } from './notice-banner.js';

const TAB_TIMELINE = 0;
// sw.js と合わせる
const DEEPLINK_MAILBOX = 'tsubasa-deeplink';
const DEEPLINK_KEY = '/__deeplink__';
// 書き置きが古すぎたら使わない（タップから時間が経って、関係ない場面で開かないように）
const DEEPLINK_MAX_AGE_MS = 5 * 60 * 1000;
// 開いたタップ。知らせ・書き置き・消えた通知のどれで届いても一度だけ開く。
// 目印はサーバが通知ごとに付ける nid で、どの経路でも同じ値になる（sw.js の data.id も nid）
const handledTaps = new Set();

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
 * 投稿を開くリンクで起動したときは、タブバーの上に投稿の画面を最初から積んでおく。
 * AppNavigator.vue の beforeCreate で、タブバーを積んだ直後に呼ぶ。
 *
 * 描画後(openFromUrl)に積むと、タイムラインが一瞬見えてから投稿の画面に変わる(2026-09-26 実機)。
 * 投稿はタイムラインのタブのまま開くのでタブの切り替えは要らず、タブバーの初期化を待つ必要が無い。
 */
let articleOpenedOnStart = false;
export function pushArticleOnStart(store) {
  if (!isId(params().get('post'))) {
    return;
  }
  store.commit('navigator/push', {
    extends: Article,
    onsNavigatorOptions: { animation: 'none' }
  });
  articleOpenedOnStart = true;
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

  // 通知から起動した場合(リンクに nid がある)は、お知らせ一覧でも「開いた」にする(#125)
  markNoticeOpened(p.get('nid'));

  // 再読み込みで同じ画面が開き直さないように、パラメータはすぐに消す。
  // launcher=true はホーム画面からの起動の目印として既存の処理が見ているので残す
  const rest = p.get('launcher') === 'true' ? '?launcher=true' : '';
  window.history.replaceState(null, '', window.location.pathname + rest);

  // この起動で開くので、書き置きが残っていれば消す（前面に戻ったときに二重に開かないように）。
  // 消し終わる前に読み込み完了の pageshow などで書き置きを読んでしまわないよう、少しの間は読まない
  ignoreDeepLinkUntil = Date.now() + 3000;
  clearDeepLink();
  if (articleOpenedOnStart) {
    return; // 投稿の画面は pushArticleOnStart で積んである
  }
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

/** お知らせ一覧(🔔。#125)からタップしたとき。通知をタップしたときと同じ処理で開く */
export function openNoticeTarget(store, urlString) {
  // 一覧からは同じお知らせを何度でも開けるよう、タップの重複の確認(目印)は使わない
  openDeepLinkInApp(store, urlString, null);
}

/** 通知の画面を、読み込み直さずに開く。チームが違うときだけ、そのアドレスで読み込み直す */
function openDeepLinkInApp(store, urlString, tapId) {
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
  // 通知から開いたので、お知らせ一覧でも「開いた」にする(#125)。目印はどの経路でも nid
  markNoticeOpened(tapId || url.searchParams.get('nid'));
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
        openDeepLinkInApp(store, found.url, found.tapId);
        return;
      }
    }
  } finally {
    checking = false;
  }
}

/**
 * iPhone / iPad か。前面に戻ったときの帯は iOS の不具合への対策なので、iOS でだけ使う。
 * Android などはタップが sw.js に届くので要らない。
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
let awayChecking = false;
// 調べている間にもう一度バックグラウンドから戻った場合の、次に調べる起点（いちばん古いもの）
let pendingSince = null;

/**
 * この端末に通知が届いているはずか。
 * ホーム画面のアプリで、通知が許可され、この端末が購読しているときだけ。
 * そうでない端末（Safari のタブで開いている、通知を切っている、PC でだけ受け取っている等）には
 * 通知が届いていないので、帯も出さない（🔔の数で分かる）。
 */
async function thisDeviceSubscription() {
  const standalone = window.navigator.standalone === true
    || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
  if (!standalone || typeof Notification === 'undefined' || Notification.permission !== 'granted') {
    return null;
  }
  const registration = await navigator.serviceWorker.getRegistration('/');
  if (!registration || !registration.pushManager) {
    return null;
  }
  return registration.pushManager.getSubscription();
}

/**
 * バックグラウンドにいた間に届いて、まだ開いていない通知を、前面に戻ったときに帯で知らせる(#125 §3.2)。
 * 1 件ならその通知(帯のタップで開く)、2 件以上なら件数(帯のタップで🔔の一覧を開く)。
 *
 * iPhone でアプリがバックグラウンドのときに通知をタップしても、どの通知かがアプリに伝わらない
 * (WebKit bug 268797)。画面からは通知センターの中身も見えない(2026-09-26 実機で確認)ので、
 * タップしたのか、触らずに戻ったのか、削除したのかは見分けられない。そこで画面は切り替えず、
 * どの場合も「届いている」ことを帯で知らせる。
 */
async function checkArrivedWhileAway(since) {
  if (awayChecking) {
    pendingSince = pendingSince === null ? since : Math.min(pendingSince, since);
    return;
  }
  awayChecking = true;
  try {
    const subscription = await thisDeviceSubscription();
    if (!subscription) {
      return;
    }
    // この端末の購読がサーバに登録されているときだけ、まだ開いていない最近の通知を返してもらう。
    // 問い合わせの失敗は利用者に知らせない（前面に戻っただけで何もしていないので）
    const { data } = await axios.post('/api/push/recent', { endpoint: subscription.endpoint }, { silentErrors: true });
    // サーバの時計に直す
    const from = since + (data.now - Date.now()) - SENT_MARGIN_MS;
    // すでに別の経路で開いた通知は除く
    const arrived = (data.notices || []).filter((x) => x.at >= from && !handledTaps.has(x.nid));
    if (arrived.length === 1) {
      showNoticeBanner(arrived[0]);
    } else if (arrived.length > 1) {
      const latest = arrived[arrived.length - 1];
      showNoticeBanner({ count: arrived.length, at: latest.at });
    }
  } catch (e) {
    // 調べられなければ何も出さない（ふだんどおり前面に戻るだけ）
  } finally {
    awayChecking = false;
    if (pendingSince !== null) {
      const next = pendingSince;
      pendingSince = null;
      checkArrivedWhileAway(next);
    }
  }
}

/**
 * アプリが前面に戻ったとき（通知のタップで iOS がアプリを前に出したとき）に、書き置きを読む。
 * AppNavigator.vue の mounted から一度だけ呼ぶ。
 */
export function installDeepLinkListeners(store) {
  const check = () => checkDeepLink(store);
  const tellArrived = isAppleMobile();
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      hiddenAt = Date.now();
    } else if (document.visibilityState === 'visible') {
      check();
      if (tellArrived && hiddenAt !== null) {
        const since = hiddenAt;
        hiddenAt = null;
        checkArrivedWhileAway(since);
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
        clearDeepLink(); // 控えの書き置きは要らなくなった
        openDeepLinkInApp(store, data.url, data.tapId);
      }
    });
  }
}
