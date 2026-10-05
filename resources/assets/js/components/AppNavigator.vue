<template>
  <v-ons-navigator
    id="homeNavi" var="homeNavi"
    swipeable swipe-target-width="50px"
    :page-stack="pageStack"
    :pop-page="storePop"
    :options="options"
    @postpush="allowSwipeBack"
  ></v-ons-navigator>
</template>

<script>
  import AppTabbar from './AppTabbar.vue';
  import Cookies from 'js-cookie';
  import { applyUrlToStore, pushArticleOnStart, openFromUrl, installDeepLinkListeners } from '../deep-link.js';
  import { installNoticeCount } from '../push.js';
  import Vue from 'vue';
  import NoticeBanner from './NoticeBanner.vue';
  export default {
    beforeCreate() {
      // console.log("AppNavigator#beforeCreate");
      // ユーザー情報取得
      const self = this;
      this.$http.get('/api/me')
        .then((response)=>{
          // globalにユーザー情報セット
          console.log('⭐me=' + response.data);
          self.$store.commit('navigator/setUser', response.data);
          // 通知のリンクでチームを切り替えた場合(deep-link.js)、チーム名のクッキーが古いままなので合わせる。
          // 所属外のチームを指定された場合は、サーバが直した current_team_id がここで読める
          const teamId = Cookies.get('current_team_id');
          const team = (response.data.myTeams || []).find(t => String(t.id) === String(teamId));
          if (team && team.name !== Cookies.get('current_team_name')) {
            Cookies.set('current_team_name', team.name);
            self.$store.commit('navigator/setCurrentTeamName', team.name);
          }
        })
        // .catch(error => {
        //   // console.log(error);
        //   if (error.response.status == 401) {window.location.href = "/login";}
        // })
        .catch(() => {}) // 401 のリダイレクトと利用者への通知は http-errors.js で行う
      ;
      this.$store.commit('navigator/setCurrentTeamName', Cookies.get('current_team_name'));
      // 通知のリンク(/home?post=… など)で開く投稿・日付を、各画面を作る前に store に入れておく(deep-link.js)
      applyUrlToStore(this.$store);
      // navigatorにTabbarをpush
      this.$store.commit('navigator/push', AppTabbar);
      // 通知のリンクで投稿を開くときは、最初から投稿の画面を上に積む(タイムラインを一瞬見せないため)
      pushArticleOnStart(this.$store);
    },
    mounted() {
      // 通知のリンク(/home?post=… など)で起動したら、そのタブ・投稿を開く(deep-link.js)
      openFromUrl(this.$store);
      // アプリが開いたまま通知をタップしたときは、前面に戻ったときに sw.js の書き置きを読んで開く
      installDeepLinkListeners(this.$store);
      // 🔔とアイコンの数(まだ開いていない通知の数。#125)を取り直し続ける
      installNoticeCount();
      // 前面に戻ったときの「届いたお知らせ」の帯(#125)。アプリ全体の最前面に 1 つだけ置く
      const banner = new (Vue.extend(NoticeBanner))({ store: this.$store }).$mount();
      document.body.appendChild(banner.$el);
    },
    data() {
      return {
      }
    },
    computed: {
      pageStack() {
        return this.$store.state.navigator.stack;
      },
      options() {
        return this.$store.state.navigator.options;
      }
    },
    methods: {
      storePop() {
        this.$store.commit('navigator/pop');
      },
      // OnsenUI は、積んだときの動きが横からでない画面をスワイプで戻らせない。
      // 動き無しで積んだ画面(同じ投稿の開き直しなど。deep-link.js)も、左端からのスワイプで戻れるようにする
      allowSwipeBack(event) {
        const page = event.enterPage;
        if (page && page.pushedOptions && page.pushedOptions.animation === 'none') {
          page.pushedOptions = { ...page.pushedOptions, animation: 'slide' };
        }
      }
    }
  };
</script>
