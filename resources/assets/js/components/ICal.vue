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
    <v-ons-row class="bg-white space lastspace">
      <v-ons-col>
        <div class="contents">
          iPhone、Androidのカレンダーに取り込み(同期)ができます。<br><br>
          以下の手順を最初に一度だけ実施すれば常に最新データが維持されます。<br><br>
          【iPhoneの場合】<br>
          ホームで「設定」→「メール/連絡先/カレンダー」→「アカウントを追加」→「その他」→「照会するカレンダーを追加」→
          以下のURLを入力→「保存」をタップ<br><br><br>
          <span>{{ ical_url }}</span><br>
          <button :data-clipboard-text="ical_url" class="copyUrlBtn button smallBtn ml-10 mt-10">URLをコピー</button><br>
          <br>
          <hr>
          【Androidの場合】<br>
          パソコンでGoogleカレンダーを開く → ページ左側の「他のカレンダー」の横にある︙をクリック →
          「URL で追加」 → 以下のURLを入力 → 「カレンダーを追加」を押す。<br><br><br>
          <span>{{ ical_url }}</span><br>
          <button :data-clipboard-text="ical_url" class="copyUrlBtn button smallBtn ml-10 mt-10">URLをコピー</button><br>
        </div>
      </v-ons-col>
    </v-ons-row>
    <v-ons-alert-dialog id="copied_dialog" cancelable>
      <div class="alert-dialog-content">
        コピーしました。
      </div>
      <div class="alert-dialog-footer">
        <v-ons-alert-dialog-button
                onclick="$('#copied_dialog').hide();">OK</v-ons-alert-dialog-button>
      </div>
    </v-ons-alert-dialog>
  </v-ons-page>
</template>

<script>
  import clipboard from 'clipboard';
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
    mounted() {
      this.clipBoard = new clipboard('.copyUrlBtn');
      this.clipBoard.on('success', function(e) {
        $('#copied_dialog').show() ;
        console.info('Text:', e.text);
        e.clearSelection();
      });
      this.clipBoard.on('error', function(e) {
        alert('コピーできません');
        alert(e);
        console.log(e);
      });
    },
    data() {
      return {
        loading: false,
        errored: false,
        ical_url: '',
        clipBoard: null
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
  .lastspace {
    height: 150%;
  }
</style>
