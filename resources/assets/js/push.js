/**
 * Web プッシュ通知とホーム画面への追加（#110 / #55）の画面側の共通処理。
 *
 * 設計: docs/design/110-web-push.md §6
 * - Service Worker（public/sw.js）は起動時に全員分を登録する。
 *   登録だけなら通知の許可は求めないので、利用者には何も起きない
 * - 通知の許可を求めるのは、設定画面のスイッチを押したときだけ（iOS は操作をきっかけにしないと求められない）
 */
import Vue from 'vue';
import axios from 'axios';

const SW_URL = '/sw.js';
const A2HS_DISMISSED_KEY = 'a2hs_dismissed_at';
const A2HS_HIDE_DAYS = 14;

// Android の Chrome が出す beforeinstallprompt を取っておく（ボタンを押したときに prompt() する）。
// 画面（InstallGuide.vue）が変化に追従できるよう、Vue の observable にする
export const installState = Vue.observable({
  deferredPrompt: null,
  // 案内を閉じたか(設定画面とタイムラインの両方の案内を一緒に消すため、ここで持つ)
  dismissed: false,
  // この利用者に通知を開放しているか(設計書 §8 の段階的な公開)。
  // 案内は「通知も受け取れます」と書いているので、開放していない人には出さない
  pushEnabled: false
});

let pushConfigPromise = null;

/** 通知を開放しているかを一度だけサーバに聞き、installState.pushEnabled に入れる */
export function loadPushEnabled() {
  if (!pushConfigPromise) {
    pushConfigPromise = axios.get('/api/push/config')
      .then((response) => {
        installState.pushEnabled = !!(response.data && response.data.enabled);
      })
      .catch(() => {
        installState.pushEnabled = false;
        pushConfigPromise = null; // 次に呼ばれたときにやり直す
      });
  }
  return pushConfigPromise;
}

let registrationPromise = null;

/** Service Worker を登録する（何度呼んでも 1 回だけ） */
export function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) {
    return Promise.resolve(null);
  }
  if (!registrationPromise) {
    registrationPromise = navigator.serviceWorker.register(SW_URL, { scope: '/' })
      .catch((e) => {
        console.warn('Service Worker の登録に失敗しました', e);
        registrationPromise = null; // 次に呼ばれたときにやり直す
        return null;
      });
  }
  return registrationPromise;
}

/** 起動時に 1 回だけ呼ぶ（app.js） */
export function initPwa() {
  if ('serviceWorker' in navigator) {
    // 画面の読み込みを邪魔しないよう、load の後で登録する
    if (document.readyState === 'complete') {
      registerServiceWorker();
    } else {
      window.addEventListener('load', () => registerServiceWorker());
    }
    // 通知をタップしたときの画面の切り替えは deep-link.js（installDeepLinkListeners）が受け持つ
  }

  installState.dismissed = isInstallGuideDismissed();
  window.addEventListener('beforeinstallprompt', (event) => {
    // ブラウザ任せのバーは出さず、案内バナーのボタンから出す
    event.preventDefault();
    installState.deferredPrompt = event;
  });
  window.addEventListener('appinstalled', () => {
    installState.deferredPrompt = null;
  });
}

/** iPhone / iPad か（iPadOS は Mac と名乗るのでタッチの有無で見分ける） */
export function isIOS() {
  const ua = navigator.userAgent || '';
  return /iPad|iPhone|iPod/.test(ua)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

/**
 * ホーム画面のアイコンから起動しているか。
 * ?launcher=true はメールのリンクからも付き得るので判定に使わない
 */
export function isStandalone() {
  return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
    || window.navigator.standalone === true;
}

/**
 * この端末で通知が使えるか。
 * @returns {'ok'|'ios-needs-home-screen'|'unsupported'|'denied'}
 */
export function pushSupport() {
  // iPhone の Safari では、ホーム画面から開かない限り PushManager が無い
  if (isIOS() && !isStandalone()) {
    return 'ios-needs-home-screen';
  }
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    return 'unsupported';
  }
  if (Notification.permission === 'denied') {
    return 'denied';
  }
  return 'ok';
}

/**
 * 通知の許可を求める。必ず利用者の操作（タップ）の処理の中で、await より前に呼ぶこと。
 * 古い Safari はコールバック式なので両方に対応する
 */
export function requestPermission() {
  return new Promise((resolve) => {
    const result = Notification.requestPermission(resolve);
    if (result && typeof result.then === 'function') {
      result.then(resolve, () => resolve(Notification.permission));
    }
  });
}

/** VAPID 公開鍵（URL-safe Base64）を PushManager.subscribe() が受け取る形に変える */
export function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = window.atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i++) {
    output[i] = raw.charCodeAt(i);
  }
  return output;
}

function sameKey(buffer, bytes) {
  if (!buffer) return true; // 取れない環境では同じとみなす
  const a = new Uint8Array(buffer);
  if (a.length !== bytes.length) return false;
  for (let i = 0; i < a.length; i++) {
    if (a[i] !== bytes[i]) return false;
  }
  return true;
}

/** この端末の購読（無ければ null） */
export async function currentSubscription() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    return null;
  }
  const registration = await navigator.serviceWorker.getRegistration('/');
  if (!registration || !registration.pushManager) {
    return null;
  }
  return registration.pushManager.getSubscription();
}

/** 購読情報をサーバへ送る（同じ endpoint なら上書きされる） */
export function sendSubscription(subscription) {
  const encodings = (window.PushManager && PushManager.supportedContentEncodings) || ['aes128gcm'];
  return axios.post('/api/push/subscriptions', Object.assign({}, subscription.toJSON(), {
    content_encoding: encodings[0]
  }));
}

/**
 * 購読してサーバへ登録する。通知の許可は済んでいる前提
 * @param {string} vapidPublicKey
 */
export async function subscribe(vapidPublicKey) {
  const registration = await registerServiceWorker();
  if (!registration) {
    throw new Error('Service Worker を登録できませんでした');
  }
  // 登録直後は有効化を待たないと subscribe できない環境がある
  await navigator.serviceWorker.ready;

  const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);
  let subscription = await registration.pushManager.getSubscription();
  // VAPID 鍵が変わっていたら、古い購読のままでは届かないので作り直す
  if (subscription && !sameKey(subscription.options && subscription.options.applicationServerKey,
    applicationServerKey)) {
    await subscription.unsubscribe();
    subscription = null;
  }
  if (!subscription) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey
    });
  }
  await sendSubscription(subscription);
  return subscription;
}

/* ---------- お知らせ(🔔)とアイコンのバッジ(#123 / #125) ---------- */

// 🔔の数(まだ開いていない通知の数)。ホーム画面のアイコンの数も同じにする。
// 一覧を開いただけでは減らず、1 件ずつ開く(タップ・その投稿を開く)と減る
export const noticeState = Vue.observable({ unopened: 0 });
// iPhone でアプリを表示している間に、🔔の数を聞き直す間隔
const UNOPENED_POLL_MS = 30000;

/** アイコンのバッジを消す。対応していない端末では何もしない */
export function clearAppBadge() {
  if ('clearAppBadge' in navigator) {
    navigator.clearAppBadge().catch(() => {});
  }
}

/** 🔔の数を入れ、アイコンの数もそろえる */
export function setUnopened(count) {
  noticeState.unopened = Math.max(0, Number(count) || 0);
  if (noticeState.unopened > 0 && 'setAppBadge' in navigator) {
    navigator.setAppBadge(noticeState.unopened).catch(() => {});
  } else {
    clearAppBadge();
  }
}

/** 🔔の数をサーバから取り直す。裏の問い合わせなので、失敗しても利用者には知らせない */
export function refreshUnopened() {
  return axios.get('/api/notices/unopened', { silentErrors: true })
    .then((response) => setUnopened(response.data && response.data.unopened))
    .catch(() => {});
}

/**
 * 🔔の数を、起動時・前面に戻ったとき・アプリを開いている間に通知が届いたときに取り直す。
 * 通知を開放していない人(段階的な公開の対象外)には🔔を出さないので、何もしない。
 * AppNavigator.vue の mounted から一度だけ呼ぶ
 */
export function installNoticeCount() {
  loadPushEnabled().then(() => {
    if (!installState.pushEnabled) {
      return;
    }
    refreshUnopened();
    // iPhone では、アプリを表示している間に届いた通知を sw.js から画面に知らせられない
    // (sw.js から開いている画面が見えない。2026-09-26 実機で確認)。表示している間は一定の間隔で数を聞く
    let poll = null;
    const startPoll = () => {
      if (isIOS() && !poll) {
        poll = setInterval(refreshUnopened, UNOPENED_POLL_MS);
      }
    };
    const stopPoll = () => {
      clearInterval(poll);
      poll = null;
    };
    if (document.visibilityState === 'visible') {
      startPoll();
    }
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        refreshUnopened();
        startPoll();
      } else {
        stopPoll();
      }
    });
    if ('serviceWorker' in navigator) {
      // sw.js が、通知を表示したときに「届いた」と知らせてくる
      navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data && event.data.type === 'notice-arrived') {
          refreshUnopened();
        }
      });
    }
  });
}

/** スマホの通知センターに残っている、このアプリの通知のうち match に合うものを消す */
export async function closeShownNotifications(match) {
  try {
    if (!('serviceWorker' in navigator)) {
      return;
    }
    const registration = await navigator.serviceWorker.getRegistration('/');
    const list = registration ? await registration.getNotifications() : [];
    list.filter(match).forEach((notification) => notification.close());
  } catch (e) {
    // 消せなくても困らない
  }
}

/**
 * その通知を「開いた」にする(サーバに伝え、通知センターに残っていれば消す)。
 * 一覧からタップしたとき・スマホの通知から開いたときに呼ぶ
 */
export function markNoticeOpened(nid) {
  if (!nid) {
    return Promise.resolve();
  }
  closeShownNotifications((n) => n.data && n.data.nid === nid);
  // 開いた分だけ🔔とアイコンの数が減る
  return axios.post('/api/notices/open', { nid }, { silentErrors: true })
    .then((response) => setUnopened(response.data && response.data.unopened))
    .catch(() => {});
}

/** 購読をやめる。サーバへの通知に失敗しても、端末側の購読は必ず取り消す */
export async function unsubscribe() {
  // ログアウト・通知をオフにしたときは、残っているバッジも消す
  clearAppBadge();
  const subscription = await currentSubscription();
  if (!subscription) {
    return;
  }
  try {
    await axios.delete('/api/push/subscriptions', { data: { endpoint: subscription.endpoint } });
  } finally {
    // サーバに残った購読は、次の送信で 404/410 が返ったときに削除される
    await subscription.unsubscribe();
  }
}

/* ---------- ホーム画面への追加の案内（#55） ---------- */

/** 案内を閉じてから 14 日以内か */
export function isInstallGuideDismissed() {
  try {
    const at = Number(window.localStorage.getItem(A2HS_DISMISSED_KEY));
    return !!at && (Date.now() - at) < A2HS_HIDE_DAYS * 24 * 60 * 60 * 1000;
  } catch (e) {
    return false; // プライベートブラウズ等で localStorage が使えないときは毎回出す
  }
}

export function dismissInstallGuide() {
  installState.dismissed = true;
  try {
    window.localStorage.setItem(A2HS_DISMISSED_KEY, String(Date.now()));
  } catch (e) {
    // 保存できなくても、この画面の間は閉じたままにする
  }
}

/** iPhone でのホーム画面への追加手順を表示する */
export function showIOSInstallGuide(ons) {
  return ons.notification.alert({
    title: 'ホーム画面に追加する',
    messageHTML:
      '<div style="text-align:left; line-height:1.8;">'
      + '1. Safari の<b>共有ボタン</b>（<ons-icon icon="fa-share-square"></ons-icon> □に↑。見当たらなければ「…」の中）をタップ<br>'
      + '2. <b>「ホーム画面に追加」</b>（<ons-icon icon="fa-plus-square"></ons-icon>）をタップ<br>'
      + '3. 右上の「追加」をタップ<br>'
      + '4. ホーム画面に追加された<b>アイコンから開く</b><br>'
      + '<span style="color:gray; font-size:13px;">アイコンから開くと、アプリのように全画面で使え、通知も受け取れます。</span>'
      + '</div>',
    buttonLabels: ['OK']
  });
}

/** Android の Chrome で、取っておいた beforeinstallprompt を出す */
export async function promptInstall() {
  const event = installState.deferredPrompt;
  if (!event) {
    return false;
  }
  installState.deferredPrompt = null; // prompt() は 1 回しか使えない
  event.prompt();
  try {
    const choice = await event.userChoice;
    return choice && choice.outcome === 'accepted';
  } catch (e) {
    return false;
  }
}
