/**
 * API通信エラーの共通処理(#111)。
 *
 * 各画面の catch は console.log と 401 のリダイレクトしかしておらず、
 * 429 / 5xx / 通信断では画面が黙って更新されないだけだった。
 * (投稿やコメントが送れていないことに利用者が気付けない)
 * ここで利用者への通知をまとめて行い、エラーは必ず投げ直して
 * 各画面の catch(loading / errored の後始末)はそのまま動かす。
 */

const MSG_NETWORK = '通信できませんでした。電波の良いところでもう一度お試しください。';
const MSG_SESSION = 'ログインの有効期限が切れました。画面を読み込み直します。';
const MSG_RATE_LIMIT = 'アクセスが集中しています。少し待ってからもう一度お試しください。';
const MSG_SERVER = 'サーバーでエラーが発生しました。時間をおいてもう一度お試しください。';

// 429 の自動再試行。Retry-After がこれより長い場合は待たずに諦める
// (利用者を何十秒も無反応のまま待たせるより、知らせた方が良い)
const MAX_429_RETRIES = 2;
const MAX_RETRY_WAIT_SEC = 10;

const TOAST_TIMEOUT_MS = 4000;
// 画面を開くと複数のAPIを同時に呼ぶので、同じ文言が何度も並ばないようにする
const DEDUP_MS = 5000;

export function installHttpErrorHandling(axios, getOns) {
  let lastMessage = null;
  let lastShownAt = 0;
  // /login への遷移やリロードが始まったら、中断された通信の分は知らせない
  let leaving = false;

  function toast(message) {
    const now = Date.now();
    if (leaving || (message === lastMessage && now - lastShownAt < DEDUP_MS)) {
      return;
    }
    lastMessage = message;
    lastShownAt = now;
    const ons = getOns();
    if (ons) {
      ons.notification.toast(message, { timeout: TOAST_TIMEOUT_MS });
    }
  }

  function retryAfterSec(response, attempt) {
    const header = Number(response.headers && response.headers['retry-after']);
    if (header >= 0) {
      return header;
    }
    return attempt; // 1秒 → 2秒
  }

  axios.interceptors.response.use(response => response, error => {
    // 明示的に取り消した通信は利用者に知らせる必要がない
    if (axios.isCancel(error)) {
      return Promise.reject(error);
    }
    const config = error.config || {};
    // 利用者の操作によらない裏の問い合わせ(前面に戻ったときの通知の確認など)は知らせない
    if (config.silentErrors) {
      return Promise.reject(error);
    }

    // 通信断・タイムアウト。response が無いと既存の catch が
    // error.response.status で TypeError になるので、ダミーを入れておく
    if (!error.response) {
      toast(MSG_NETWORK);
      error.response = { status: 0, data: {}, headers: {} };
      return Promise.reject(error);
    }

    const status = error.response.status;

    if (status === 401) {
      if (window.location.pathname !== '/login') {
        leaving = true;
        window.location.href = '/login';
      }
    } else if (status === 419) {
      // CSRFトークン切れ。読み込み直せば新しいトークンが入る
      // (セッション自体が切れていればサーバ側で /login へ飛ぶ)
      if (!leaving) {
        toast(MSG_SESSION);
        leaving = true;
        setTimeout(() => window.location.reload(), 1500);
      }
    } else if (status === 429) {
      const attempt = (config.__retry429 || 0) + 1;
      const wait = retryAfterSec(error.response, attempt);
      if (attempt <= MAX_429_RETRIES && wait <= MAX_RETRY_WAIT_SEC && error.config) {
        // レート制限はコントローラより手前で弾かれるので、POSTでも再送して安全
        config.__retry429 = attempt;
        return new Promise(resolve => setTimeout(resolve, wait * 1000))
          .then(() => axios(config));
      }
      toast(MSG_RATE_LIMIT);
    } else if (status >= 500) {
      toast(MSG_SERVER);
    }
    // 422(入力の検証エラー)はここでは知らせない。ファイルを送る画面(投稿・投稿編集・コメント)と
    // メンバー登録は、それぞれの画面でサーバのメッセージを表示する(#45 #79)。
    // ここでも出すと同じ内容が二重に表示される
    return Promise.reject(error);
  });
}
