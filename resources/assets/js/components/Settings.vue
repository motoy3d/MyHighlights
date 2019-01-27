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
        <div v-if="loading" class="progress-div">
          <v-ons-progress-circular indeterminate class="progress-circular"></v-ons-progress-circular>
        </div>
        <v-ons-list id="settings_list">
          <v-ons-list-item>
            <div style="margin-right:auto;">{{ $store.state.navigator.user.name }}</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" @click="openChangeEmail()">
            メールアドレス <div class="right">{{ $store.state.navigator.user.email }}</div>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" @click="openChangePass()">
            パスワード変更
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" @click="openICal()">
            カレンダー同期
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
        <v-ons-alert-dialog-button
          onclick="document.getElementById('logout-form').submit();">OK</v-ons-alert-dialog-button>
        <v-ons-alert-dialog-button
          onclick="$('#logout_dialog').hide();">キャンセル</v-ons-alert-dialog-button>
      </div>
    </v-ons-alert-dialog>
  </v-ons-page>
</template>

<script>
  import ICal from './ICal.vue';
  export default {
    data() {
      return {
        loading: false,
        errored: false,
        posting: false,
      }
    },
    methods: {
      openChangeEmail() {
        let self = this;
        this.$ons.notification.prompt("新しいメールアドレス",
          {title: '', buttonLabels:['キャンセル', 'OK']})
          .then(function(newEmail) {
            if (!newEmail) {
              return;
            }
            self.$ons.notification.confirm(newEmail,
                    {title: 'このアドレスでいいですか？', buttonLabels:['キャンセル', 'OK']})
              .then(function(answer){
                  if (self.posting) {
                      return;
                  }
                  if (answer === 1) {
                    self.loading = true;
                    let formData = new FormData();
                    formData.append('email', newEmail);
                    self.$http.post('/api/users/updateEmail', formData).then(response => {
                      self.$ons.notification.alert('変更されました', {title: ''});
                      self.loading = false; self.posting = false;
                    })
                    .catch(error => {
                      console.log(error);
                      if (error.response.status === 401) {
                        window.location.href = "/login";
                      }
                      self.loading = false; self.posting = false;
                    });
                    self.$http.get('/api/me')
                      .then((response)=>{
                        // globalにユーザー情報セット
                        // console.log('⭐me=' + response.data);
                        self.$store.commit('navigator/setUser', response.data);
                      });
                  }
              });
          });
      },
      openChangePass() {
        let self = this;
        this.$ons.notification.prompt("新しいパスワード(6文字以上)",
          {title: '', inputType: 'password', buttonLabels:['キャンセル', 'OK']})
          .then(function(newpass) {
            if (!newpass) {
              return;
            }
            if (newpass.length < 6) {
              self.$ons.notification.alert('6文字以上で入力してください', {title: ''});
              return;
            }
            const newpass2 = newpass;
            self.$ons.notification.confirm(
              newpass, {title: 'このパスワードでいいですか？', buttonLabels:['キャンセル', 'OK']})
              .then(function(answer){
                if (self.posting) {
                  return;
                }
                if (answer === 1) {
                  self.loading = true;
                  let formData = new FormData();
                  formData.append('new_password', newpass2);
                  self.$http.post('/api/users/updatePassword', formData)
                    .then(response => {
                      console.log(response.data);
                      self.$ons.notification.alert('変更されました', {title: ''});
                      self.loading = false; self.posting = false;
                    })
                    .catch(error => {
                      console.log(error);
                      if (error.response.status === 401) {
                        window.location.href = "/login";
                      }
                      self.loading = false; self.posting = false;
                    })
                  // .finally(() => {this.loading = false; this.posting = false;})
                }
              });
          });
      },
      openICal() {
        this.$store.commit('navigator/push', {
          extends: ICal,
          onsNavigatorOptions: {animation: 'slide'}
        });
      }
    }
  };
</script>

<style></style>