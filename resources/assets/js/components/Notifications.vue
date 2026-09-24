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
          <small class="white">すべて既読</small>
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

  export default {
    data() {
      return { items: [], loading: true, errored: false };
    },
    computed: {
      hasUnopened() { return this.items.some((item) => !item.opened); },
      multiTeam() {
        const teams = this.$store.state.navigator.user.myTeams;
        return !!teams && teams.length > 1;
      }
    },
    created() {
      this.load();
    },
    methods: {
      load() {
        this.loading = true;
        this.$http.get('/api/notices')
          .then((response) => {
            this.items = response.data.items || [];
            this.loading = false;
            // 一覧を開いただけでは🔔の数は減らない(まだ開いていない通知の数)。念のため最新の数に合わせる
            setUnopened(response.data.unopened);
          })
          .catch(() => {
            this.loading = false;
            this.errored = true;
          });
      },
      open(item) {
        // 🔔の数はすぐ 1 減らす(サーバの応答で正しい数に合わせ直す)
        if (!item.opened) {
          item.opened = true;
          setUnopened(noticeState.unopened - 1);
        }
        markNoticeOpened(item.nid);
        // この一覧を閉じてから、通知をタップしたときと同じ処理で目的の画面を開く
        this.$store.commit('navigator/pop');
        openNoticeTarget(this.$store, item.url);
      },
      openAll() {
        this.items.forEach((item) => { item.opened = true; });
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
