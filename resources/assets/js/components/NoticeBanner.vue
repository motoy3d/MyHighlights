<template>
  <!-- 前面に戻ったときの「届いたお知らせ」の帯(#125。notice-banner.js)。
       帯のどこをタップしても開く(2 件以上なら🔔の一覧を開く)。✕か数秒で消える -->
  <transition name="notice-banner">
    <div v-if="notice" class="notice-banner" :style="{ top: top + 'px' }" role="alert" @click="open()">
      <div class="nb-icon"><v-ons-icon icon="fa-bell"></v-ons-icon></div>
      <div class="nb-text">
        <!-- チーム名は、複数のチームに所属している人にだけ出す(お知らせ一覧と同じ) -->
        <div class="nb-label">{{ multiTeam && notice.title && !notice.count ? notice.title : 'お知らせ' }}・{{ notice.at | moment('from') }}</div>
        <div class="nb-body">{{ notice.count ? 'お知らせが' + notice.count + '件届いています' : notice.body }}</div>
      </div>
      <button class="nb-open" type="button">開く</button>
      <div class="nb-close" aria-label="閉じる" @click.stop="close()">
        <v-ons-icon icon="fa-times"></v-ons-icon>
      </div>
    </div>
  </transition>
</template>

<script>
  import { bannerState, hideNoticeBanner } from '../notice-banner.js';
  import { markNoticeOpened } from '../push.js';
  import { openNoticeTarget } from '../deep-link.js';
  import Notifications from './Notifications.vue';

  export default {
    computed: {
      notice() { return bannerState.notice; },
      top() { return bannerState.top; },
      multiTeam() {
        const teams = this.$store.state.navigator.user.myTeams;
        return !!teams && teams.length > 1;
      }
    },
    methods: {
      open() {
        const notice = this.notice;
        hideNoticeBanner();
        if (!notice) {
          return;
        }
        if (notice.count) {
          // 2 件以上届いていたら、どれを開くかは🔔の一覧で選んでもらう
          this.$store.commit('navigator/push', {
            extends: Notifications,
            // 横から開く(slide)と、左端から右へのスワイプで戻れる(lift では戻れない)
            onsNavigatorOptions: { animation: 'slide' }
          });
          return;
        }
        markNoticeOpened(notice.nid);
        openNoticeTarget(this.$store, notice.url);
      },
      close() {
        hideNoticeBanner();
      }
    }
  };
</script>

<style scoped>
  .notice-banner {
    position: fixed;
    left: 0;
    right: 0;
    z-index: 10001;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 10px 12px 14px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, .18);
    /* 帯は画面(ons-page)の外、body の直下に置くので、アプリと同じ書体をここで指定する
       (指定しないと iPhone では明朝体になる。Onsen UI のページと同じ指定) */
    font-family: -apple-system, 'Helvetica Neue', 'Helvetica', 'Arial', 'Lucida Grande', sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  .nb-icon {
    flex: none;
    width: 34px;
    height: 34px;
    border-radius: 17px;
    background: #2c74e8;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
  }
  .nb-text {
    flex: 1;
    min-width: 0;
    text-align: left;
  }
  .nb-label {
    font-size: 12px;
    color: grey;
    margin-bottom: 2px;
  }
  .nb-body {
    font-size: 15px;
    line-height: 1.4;
    color: #222;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
  .nb-open {
    flex: none;
    background: #2c74e8;
    color: #fff;
    border: 0;
    border-radius: 16px;
    padding: 7px 14px;
    font-size: 14px;
    font-weight: bold;
  }
  .nb-close {
    flex: none;
    color: #999;
    font-size: 18px;
    padding: 4px 2px 4px 4px;
  }
  .notice-banner-enter-active, .notice-banner-leave-active {
    transition: transform .25s ease, opacity .25s ease;
  }
  .notice-banner-enter, .notice-banner-leave-to {
    transform: translateY(-20px);
    opacity: 0;
  }
</style>
