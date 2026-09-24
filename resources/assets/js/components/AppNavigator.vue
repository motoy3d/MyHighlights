<template>
  <v-ons-navigator
    id="homeNavi" var="homeNavi"
    swipeable swipe-target-width="50px"
    :page-stack="pageStack"
    :pop-page="storePop"
    :options="options"
  ></v-ons-navigator>
</template>

<script>
  import AppTabbar from './AppTabbar.vue';
  import Cookies from 'js-cookie';
  import { applyUrlToStore, openFromUrl, installDeepLinkListeners } from '../deep-link.js';
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
    },
    mounted() {
      // 通知のリンク(/home?post=… など)で起動したら、そのタブ・投稿を開く(deep-link.js)
      openFromUrl(this.$store);
      // アプリが開いたまま通知をタップしたときは、前面に戻ったときに sw.js の書き置きを読んで開く
      installDeepLinkListeners(this.$store);
      // 🔔とアイコンの数(お知らせ一覧を最後に開いた後に届いた通知の数。#125)を取り直し続ける
      installNoticeCount();
      // 前面に戻ったときの「この通知を開きますか」の帯(#125)。アプリ全体の最前面に 1 つだけ置く
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
      }
    }
  };
</script>
