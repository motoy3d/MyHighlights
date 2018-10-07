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
            <div style="margin-right:auto;">片岡　基</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron">
            メールアドレス <div class="right">motoy3d@gmail.com</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" @click="openChangePass()">
            パスワード変更
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron">
            チーム名 <div class="right">横浜SCつばさ</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron">
            テーマカラー <div class="right">スカイブルー</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron">
            プラン確認/変更 <div class="right">フリー</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron">
            利用規約 <div class="right"></div>
          </v-ons-list-item>
          <v-ons-list-item>
            バージョン <div class="right">0.1 (2018.5.23)</div>
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
        <v-ons-alert-dialog-button onclick="location.href='login'">OK</v-ons-alert-dialog-button>
        <v-ons-alert-dialog-button onclick="$('#logout_dialog').hide();">キャンセル</v-ons-alert-dialog-button>
      </div>
    </v-ons-alert-dialog>
  </v-ons-page>
</template>

<script>
  export default {
    methods: {
      openChangePass() {
        let self = this;
        this.$ons.notification.prompt("新しいパスワードを入れてください", {title: ''})
          .then(function(newpass) {
            self.$ons.notification.confirm(
              newpass, {title: 'このパスワードでいいですか？'})
              .then(function(answer){
                alert(answer);
              });
          });
      }
    }
  };
</script>

<style></style>