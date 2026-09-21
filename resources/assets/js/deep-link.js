/**
 * 起動時のリンク処理（#9 の最小版。#110 の通知をタップしたときに該当画面を開く）
 *
 * 設計: docs/design/110-web-push.md §6.4 / §7.3.1
 *   投稿：/home?launcher=true&team={team_id}&post={post_id}
 *   予定：/home?launcher=true&team={team_id}&schedule={schedule_id}&date={YYYY-MM-DD}
 *   削除された予定：/home?launcher=true&team={team_id}&date={YYYY-MM-DD}
 *
 * Vue Router は使わず、起動時に URL のパラメータを読んで画面を開く。
 * アプリが開いている状態で通知をタップした場合も、sw.js がそのウィンドウを
 * このリンクへ遷移させる（読み込み直す）ので、同じ処理で開ける。
 */
import Cookies from 'js-cookie';
import Article from './components/Article.vue';

const TAB_TIMELINE = 0;
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
    return;
  }

  // 再読み込みで同じ画面が開き直さないように、パラメータはすぐに消す。
  // launcher=true はホーム画面からの起動の目印として既存の処理が見ているので残す
  const rest = p.get('launcher') === 'true' ? '?launcher=true' : '';
  window.history.replaceState(null, '', window.location.pathname + rest);

  const post = p.get('post');
  const date = p.get('date');
  const open = () => {
    if (isId(post)) {
      // タイムラインで投稿を開くのと同じ処理（Timeline.vue の openArticle）
      store.commit('tabbar/setIndex', TAB_TIMELINE);
      store.commit('navigator/push', {
        extends: Article,
        onsNavigatorOptions: { animation: 'none' }
      });
    } else if (date && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
      store.commit('tabbar/setIndex', TAB_CALENDAR);
    }
  };
  const later = () => setTimeout(open, 100);
  if (document.readyState === 'complete') {
    later();
  } else {
    window.addEventListener('load', later, { once: true });
  }
}
