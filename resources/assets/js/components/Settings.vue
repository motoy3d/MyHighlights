<template>
  <v-ons-page id="settings">
    <v-ons-toolbar class="navbar">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
      <div class="center navbartitle">
        <v-ons-icon icon="fa-cog" size="20px"></v-ons-icon> 設定
      </div>
    </v-ons-toolbar>
    <v-ons-row>
      <v-ons-col>
        <v-ons-list id="settings_list">
          <v-ons-list-item modifier="chevron">
            <div style="margin-right:auto;">{{ $store.state.navigator.user.name }}</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" @click="openChangeEmail()">
            メールアドレス <div class="right">{{ $store.state.navigator.user.email }}</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" @click="openChangePass()">
            パスワード変更
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron">
            チーム名 <div class="right">横浜SCつばさ 36th</div>
          </v-ons-list-item>
          <!--<v-ons-list-item modifier="chevron">-->
            <!--テーマカラー <div class="right">スカイブルー</div>-->
          <!--</v-ons-list-item>-->
          <!--<v-ons-list-item modifier="chevron">-->
            <!--プラン確認/変更 <div class="right">フリー</div>-->
          <!--</v-ons-list-item>-->
          <!--<v-ons-list-item modifier="chevron">-->
            <!--利用規約 <div class="right"></div>-->
          <!--</v-ons-list-item>-->
          <v-ons-list-item>
            バージョン <div class="right">2018.11.25</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" onclick="$('#logout_dialog').show()">
            ログアウト
          </v-ons-list-item>
        </v-ons-list>
      </v-ons-col>
    </v-ons-row>
    <v-ons-alert-dialog id="logout_dialog" cancelable>
      <div class="alert-dialog-content">
        ログアウトしますか？
      </div>
      <div class="alert-dialog-footer">
        <v-ons-alert-dialog-button onclick="document.getElementById('logout-form').submit();">OK</v-ons-alert-dialog-button>
        <v-ons-alert-dialog-button onclick="$('#logout_dialog').hide();">キャンセル</v-ons-alert-dialog-button>
      </div>
    </v-ons-alert-dialog>
  </v-ons-page>
</template>

<script>
  export default {
    methods: {
      openChangeEmail() {
        let self = this;
        this.$ons.notification.prompt("新しいメールアドレス", {title: ''})
          .then(function(newEmail) {
            self.$ons.notification.confirm(
              newEmail, {title: 'このアドレスでいいですか？'})
              .then(function(answer){
                alert(answer);
                //TODO API
              });
          });
      },
      openChangePass() {
        let self = this;
        this.$ons.notification.prompt("新しいパスワード", {title: ''})
          .then(function(newpass) {
            self.$ons.notification.confirm(
              newpass, {title: 'このパスワードでいいですか？'})
              .then(function(answer){
                alert(answer);
                //TODO API
              });
          });
      }
    }
  };
</script>

<style></style>