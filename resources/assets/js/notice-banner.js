/**
 * 前面に戻ったときに出す「この通知を開きますか」の帯(#125)。
 *
 * iPhone でアプリがバックグラウンドのとき、通知をタップしても削除しても、アプリには同じように見える
 * (通知センターから消えて前面に出るだけ。deep-link.js の checkVanishedNotification)。
 * 勝手に画面を切り替えると、削除しただけの人には不自然なので、切り替えずにこの帯で知らせ、
 * タップしたら開く。✕か数秒で消え、その通知は「まだ開いていない」のまま🔔に残る。
 * iPhone でアプリを表示している間に届いた通知も、この帯で知らせる(deep-link.js の startArrivalWatch)。
 *
 * 画面(NoticeBanner.vue)はアプリ全体の最前面に 1 つだけ置き、ここで状態を持つ。
 */
import Vue from 'vue';

const AUTO_HIDE_MS = 8000;

// notice: { nid, url, title, body, team_id, at }。null なら出さない。top: 帯の上端(ツールバーの真下)
export const bannerState = Vue.observable({ notice: null, top: 0 });

let hideTimer = null;

/** 今見えているツールバーの下端。見つからなければ一般的な高さ */
function toolbarBottom() {
  let bottom = 0;
  document.querySelectorAll('ons-toolbar').forEach((bar) => {
    const rect = bar.getBoundingClientRect();
    if (rect.width > 0 && rect.height > 0 && rect.top <= 1 && rect.bottom > bottom) {
      bottom = rect.bottom;
    }
  });
  return bottom || 44;
}

export function showNoticeBanner(notice) {
  bannerState.top = toolbarBottom();
  bannerState.notice = notice;
  clearTimeout(hideTimer);
  hideTimer = setTimeout(hideNoticeBanner, AUTO_HIDE_MS);
}

export function hideNoticeBanner() {
  clearTimeout(hideTimer);
  bannerState.notice = null;
}
