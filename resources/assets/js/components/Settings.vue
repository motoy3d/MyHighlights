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
          <v-ons-list-header>アカウント</v-ons-list-header>
          <v-ons-list-item>
            {{ $store.state.navigator.user.name }}
            <div class="right"><v-ons-button modifier="quiet" class="small" @click="openChangeName(false)">変更</v-ons-button></div>
          </v-ons-list-item>
          <v-ons-list-item>
            {{ $store.state.navigator.user.name_kana }}
            <div class="right"><v-ons-button modifier="quiet" class="small" @click="openChangeName(true)">変更</v-ons-button></div>
          </v-ons-list-item>
          <v-ons-list-item>
            {{ $store.state.navigator.user.email }}
            <div class="right"><v-ons-button modifier="quiet" class="small" @click="openChangeEmail()">変更</v-ons-button></div>
          </v-ons-list-item>
          <v-ons-list-item>
            パスワード ***********
            <div class="right"><v-ons-button modifier="quiet" class="small" @click="openChangePass()">変更</v-ons-button></div>
          </v-ons-list-item>
        </v-ons-list>
        <br>
        <v-ons-list>
          <v-ons-list-item >
            メールで通知を受け取る
            <div class="right">
              <v-ons-switch v-model="mailNotificationFlg"
                            @click="updateMailNotificationFlg()"></v-ons-switch>
            </div>
          </v-ons-list-item>
          <v-ons-list-item >
            LINEで通知を受け取る
            <div class="right">
              <v-ons-switch v-model="lineNotificationFlg"
                            @click="updateLINENotificationFlg()"></v-ons-switch>
            </div>
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
        </v-ons-list>
        <br><br>
        <v-ons-list>
          <v-ons-list-item modifier="chevron" onclick="$('#logout_dialog').show()">
            ログアウト
          </v-ons-list-item>
        </v-ons-list>
        <br><br><br><br>
        <v-ons-list>
          <v-ons-list-item class="red" modifier="chevron" onclick="$('#withdrawal_dialog').show()">
            {{ $store.state.navigator.currentTeamName }}を退会する
          </v-ons-list-item>
        </v-ons-list>
        <br><br>
      </v-ons-col>
    </v-ons-row>
    <v-ons-alert-dialog id="logout_dialog" cancelable>
      <div class="alert-dialog-content">
        ログアウトしますか？
      </div>
      <div class="alert-dialog-footer">
        <v-ons-alert-dialog-button
                @click="logout()">OK</v-ons-alert-dialog-button>
        <v-ons-alert-dialog-button
                onclick="$('#logout_dialog').hide();">キャンセル</v-ons-alert-dialog-button>
      </div>
    </v-ons-alert-dialog>
    <v-ons-alert-dialog id="withdrawal_dialog" cancelable>
      <div class="alert-dialog-content">
        本当に[{{ $store.state.navigator.currentTeamName }}]を退会しますか？
      </div>
      <div class="alert-dialog-footer">
        <v-ons-alert-dialog-button class="red" @click="withdraw()">退会する</v-ons-alert-dialog-button>
        <v-ons-alert-dialog-button
                onclick="$('#withdrawal_dialog').hide();">キャンセル</v-ons-alert-dialog-button>
      </div>
    </v-ons-alert-dialog>
  </v-ons-page>
</template>

<script>
  import ICal from './ICal.vue';
  import Cookies from 'js-cookie';
  export default {
    data() {
      return {
        loading: false,
        errored: false,
        posting: false,
      }
    },
    computed: {
      mailNotificationFlg: {
        get() {return this.$store.state.navigator.user.mail_notification_flg == 1},
        set(mailNotificationFlg) {this.$store.state.navigator.user.mail_notification_flg = mailNotificationFlg;}
      },
      lineNotificationFlg: {
        get() {return this.$store.state.navigator.user.line_notification_flg == 1},
        set(lineNotificationFlg) {this.$store.state.navigator.user.line_notification_flg = lineNotificationFlg;}
      }
    },
    methods: {
      openChangeName(isKana) {
        let self = this;
        let defVal = isKana? this.$store.state.navigator.user.name_kana : this.$store.state.navigator.user.name;
        this.$ons.notification.prompt(isKana? "氏名かな変更" : "氏名変更",
          {defaultValue: defVal, title: '', buttonLabels:['キャンセル', 'OK']})
          .then(function(newName) {
            if (!newName) {
              return;
            }
            self.$ons.notification.confirm(newName,
              {title: 'この氏名でいいですか？', buttonLabels:['キャンセル', 'OK']})
              .then(function(answer){
                if (self.posting) {
                  return;
                }
                if (answer === 1) {
                  self.loading = true;
                  let formData = new FormData();
                  formData.append(isKana? 'name_kana' : 'name', newName);
                  let apiUrl = '/api/users/' + (isKana? 'updateNameKana' : 'updateName');
                  self.$http.post(apiUrl, formData).then(response => {
                    self.$ons.notification.alert('変更されました', {title: ''});
                    self.loading = false; self.posting = false;
                  })
                  .catch(error => {
                    console.log(error);
                    if (error.response.status === 401) {window.location.href = "/login";}
                    self.loading = false; self.posting = false;
                  });
                  self.$http.get('/api/me')
                    .then((response)=>{
                      self.$store.commit('navigator/setUser', response.data);
                    });
                }
              });
          });
      },
      openChangeEmail() {
        let self = this;
        let defVal = this.$store.state.navigator.user.email;
        this.$ons.notification.prompt("メールアドレス変更",
          {defaultValue: defVal, title: '', buttonLabels:['キャンセル', 'OK']})
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
                      if (error.response.status === 401) {window.location.href = "/login";}
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
                      if (error.response.status === 401) {window.location.href = "/login";}
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
      },
      updateMailNotificationFlg() {
        let formData = new FormData();
        formData.append('mail_notification_flg', this.mailNotificationFlg? 1 : 0);
        this.$http.post('/api/users/updateMailNotificationFlg', formData).then(response => {
          this.loading = false; this.posting = false;
        })
        .catch(error => {
          console.log(error);
          if (error.response.status === 401) {window.location.href = "/login";}
          this.loading = false; this.posting = false;
        });
        this.$http.get('/api/me').then((response)=>{
          this.$store.commit('navigator/setUser', response.data);
        });
      },
      updateLINENotificationFlg() {
        if (this.lineNotificationFlg) {
          window.location.href = '/goto_line_auth';
        } else {
          //TODO 通知解除
        }
        this.$http.get('/api/me').then((response)=>{
          this.$store.commit('navigator/setUser', response.data);
        });
      },
      logout() {
        $('#logout-form').submit();
      },
      withdraw() {
        $('#withdrawal_user_id').val(this.$store.state.navigator.user.id);
        $('#withdrawal_team_id').val(Cookies.get('current_team_id'));
        $('#withdrawal-form').submit();
      }
    }
  };
</script>

<style></style>