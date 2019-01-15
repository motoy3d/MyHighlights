<template>
  <v-ons-page id="post">
    <v-ons-toolbar class="navbar">
      <div class="left ml-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-angle-left" class="white" size="32px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
      <div class="center navbartitle">
        <v-ons-icon icon="fa-user-edit" size="20px"></v-ons-icon>
        <span>カレンダー同期設定</span>
      </div>
    </v-ons-toolbar>
    <v-ons-row class="bg-white space">
      <v-ons-col>
        <div class="contents">
          iOS、Androidのカレンダーに取り込み(同期)ができます。<br><br>
          以下の手順でスマホに登録ができます。<br><br>
          【iOSの場合】<br>
          ホームで「設定」→「メール/連絡先/カレンダー」→「アカウントを追加」→「その他」→「照会するカレンダーを追加」<br>
          <a :href="ical_url">{{ ical_url }}</a><br>
          　と入力→「保存」をタップ<br>
        </div>
      </v-ons-col>
    </v-ons-row>
  </v-ons-page>
</template>

<script>
  export default {
    beforeCreate() {
      this.$http.get('/api/ical/config')
        .then((response)=>{
          this.loading = false;
          this.ical_url = response.data.ical_url;
        })
        .catch(error => {
          this.errored = true;
          if (error.response.status === 401) {window.location.href = "/login";}
          this.loading = false;
        });
    },
    data() {
      return {
        loading: false,
        errored: false,
        ical_url: ''
      }
    },
    computed: {
    },
    methods: {

    }
  };
</script>

<style>
  .contents {
    word-break: break-all;
  }
</style>
