<template>
  <!-- アプリ内のお知らせ一覧(🔔。#125)。最近 30 日の通知を新しい順に出し、タップで目的の画面に移る -->
  <v-ons-page id="notices_page">
    <v-ons-toolbar class="navbar">
      <!-- 戻るは投稿の詳細(Article.vue)と同じ形にそろえる -->
      <div class="left ml-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');" aria-label="戻る">
          <v-ons-icon icon="fa-angle-left" class="white" size="32px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
      <div class="center navbartitle">
        <v-ons-icon icon="fa-bell" size="20px"></v-ons-icon>
        <span>お知らせ</span>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button v-if="hasUnopened" @click="openAll()" class="notices-open-all">
          <small class="white">すべて確認済みにする</small>
        </v-ons-toolbar-button>
      </div>
    </v-ons-toolbar>

    <!-- ons-page は中身を page__content に移すので、表示を切り替える部分は常にある 1 つの枠に入れる
         (枠が無いと、読み込み後に切り替えた部分が描画されない) -->
    <div class="notices-content">
    <div class="center mt-20" v-if="loading">
      <v-ons-progress-circular indeterminate></v-ons-progress-circular>
    </div>
    <div class="center mt-20 gray" v-else-if="errored">
      お知らせを読み込めませんでした。
    </div>
    <div class="center mt-20 gray notices-empty" v-else-if="!items.length">
      お知らせはありません
    </div>
    <v-ons-list v-else>
      <v-ons-list-item v-for="item in items" :key="item.id" tappable modifier="chevron"
                       :class="['notice-item', { 'notice-unopened': !item.opened }]"
                       @click="open(item)">
        <div class="left">
          <!-- まだ開いていない印の丸。開いた通知も場所だけ取っておき、文の始まりをそろえる -->
          <span class="notice-dot" :class="{ 'notice-dot-hidden': item.opened }"></span>
        </div>
        <div class="center notice-center">
          <!-- 文字の大きさはタイムラインの一覧(Timeline.vue)と同じ基準：本文は一覧の標準、日時は 13px の灰色 -->
          <div class="notice-body">{{ item.body }}</div>
          <div class="notice-meta">
            <!-- チーム名は、複数のチームに所属している人にだけ出す(1 チームなら分かりきっているので) -->
            <template v-if="multiTeam && item.title">{{ item.title }}・</template>{{ item.created_at | moment('from') }}
          </div>
        </div>
      </v-ons-list-item>
    </v-ons-list>
    </div>
  </v-ons-page>
</template>

<script>
  import { markNoticeOpened, closeShownNotifications, setUnopened, noticeState } from '../push.js';
  import { openNoticeTarget } from '../deep-link.js';

  // この起動の間に、一覧で開いた通知(読み込み直したときに、サーバの反映待ちで未読に戻って見えないように)
  const openedHere = new Set();

  export default {
    // この画面がお知らせ一覧である印(deep-link.js が、一番上に積まれているかを見分けるのに使う)
    noticeList: true,
    data() {
      return { items: [], loading: true, errored: false, reloading: false, reloadAgain: false, skipReload: false };
    },
    created() {
      this.load();
    },
    watch: {
      // 🔔の数が変わったら(投稿を開いてその投稿の通知がまとめて「開いた」になった、新しい通知が届いた等)、
      // 一覧も読み込み直す。一覧から投稿を開いて戻ったときに、古い表示が残らないように
      unopenedCount() {
        if (this.skipReload) {
          this.skipReload = false;
          return;
        }
        this.reload();
      }
    },
    computed: {
      unopenedCount() { return noticeState.unopened; },
      hasUnopened() { return this.items.some((item) => !item.opened); },
      multiTeam() {
        const teams = this.$store.state.navigator.user.myTeams;
        return !!teams && teams.length > 1;
      }
    },
    methods: {
      load() {
        this.loading = true;
        this.$http.get('/api/notices')
          .then((response) => {
            this.items = response.data.items || [];
            this.loading = false;
            this.errored = false;
            // 一覧を開いただけでは🔔の数は減らない(まだ開いていない通知の数)。念のため最新の数に合わせる
            // (いま読み込んだので、数が変わっても読み込み直さない)
            if (Number(response.data.unopened) !== noticeState.unopened) {
              this.skipReload = true;
            }
            setUnopened(response.data.unopened);
          })
          .catch(() => {
            this.loading = false;
            this.errored = true;
          });
      },
      // 表示したまま読み込み直す(くるくるは出さない。失敗しても今の表示のまま)
      reload() {
        if (this.loading || this.reloading) {
          // 読み込み中にまた数が変わったら、終わってからもう一度読み込む
          this.reloadAgain = this.reloading;
          return;
        }
        this.reloading = true;
        this.$http.get('/api/notices', { silentErrors: true })
          .then((response) => {
            // この画面で開いた通知は、サーバがまだ「開いた」にし終えていなくても開いたとして出す
            this.items = (response.data.items || [])
              .map((item) => (openedHere.has(item.nid) ? { ...item, opened: true } : item));
            this.errored = false;
          })
          .catch(() => {})
          .then(() => {
            this.reloading = false;
            if (this.reloadAgain) {
              this.reloadAgain = false;
              this.reload();
            }
          });
      },
      open(item) {
        // 🔔の数はすぐ 1 減らす(サーバの応答で正しい数に合わせ直す)
        openedHere.add(item.nid);
        if (!item.opened) {
          item.opened = true;
          setUnopened(noticeState.unopened - 1);
        }
        markNoticeOpened(item.nid);
        // 投稿はこの一覧の上に重ねて開く(戻ると一覧に戻る)。予定は一覧を閉じてカレンダーのタブで開き、
        // 別のチームの通知はそのチームで読み込み直す(deep-link.js の openTarget)
        openNoticeTarget(this.$store, item.url);
      },
      openAll() {
        this.items.forEach((item) => { item.opened = true; openedHere.add(item.nid); });
        setUnopened(0);
        this.$http.post('/api/notices/open', { all: true }, { silentErrors: true }).catch(() => {});
        closeShownNotifications(() => true);
      }
    }
  };
</script>

<style scoped>
  /* アプリ全体の .center(中央寄せ)が効いてしまうので、一覧の文は左寄せに戻す */
  .notice-center {
    text-align: left;
  }
  .notice-unopened {
    background-color: #eef4fd;
  }
  .notice-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    margin-right: 6px;
    border-radius: 4px;
    background: #2c74e8;
  }
  .notice-dot-hidden {
    visibility: hidden;
  }
  .notice-body {
    white-space: normal;
    line-height: 1.5;
  }
  .notice-meta {
    color: grey;
    font-size: 13px;
    margin-top: 4px;
  }
</style>
