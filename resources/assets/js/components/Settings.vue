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
    <!-- ホーム画面への追加の案内(#55)。iPhone の Safari / Android の Chrome のときだけ出る -->
    <install-guide></install-guide>
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
            <v-ons-icon icon="fa-envelope" size="20px" class="gray mr-5"></v-ons-icon> メールで通知を受け取る
            <div class="right">
              <v-ons-switch v-model="mailNotificationFlg"
                            @click="updateMailNotificationFlg()"></v-ons-switch>
            </div>
          </v-ons-list-item>
          <!-- #110 プッシュ通知。サーバが enabled を返した人にだけ出す(段階的な公開) -->
          <template v-if="push.enabled">
            <v-ons-list-item v-if="pushSupport === 'ok'" id="push_device_item">
              <v-ons-icon icon="fa-bell" size="20px" class="gray mr-5"></v-ons-icon> この端末で通知を受け取る
              <div class="right">
                <v-ons-switch id="push_device_switch" v-model="deviceSwitch"
                              :disabled="pushBusy"></v-ons-switch>
              </div>
            </v-ons-list-item>
            <v-ons-list-item v-else id="push_unavailable_item">
              <div style="text-align: left; width: 100%;">
                <div>
                  <v-ons-icon icon="fa-bell" size="20px" class="lightgray mr-5"></v-ons-icon> この端末で通知を受け取る
                </div>
                <p class="gray small mt-5 mb-0" id="push_unavailable_message">{{ pushUnavailableMessage }}</p>
                <v-ons-button v-if="pushSupport === 'ios-needs-home-screen'" modifier="quiet" class="small pl-0"
                              @click="showIOSInstallGuide()">ホーム画面に追加する方法</v-ons-button>
              </div>
            </v-ons-list-item>
            <!-- 通知の種類ごとのオン・オフ(ユーザー単位。端末でオンにしているときだけ出す) -->
            <template v-if="pushSupport === 'ok' && deviceOn">
              <v-ons-list-item v-for="item in pushPreferenceItems" :key="item.key" class="push_pref_item">
                <span class="ml-20 small">{{ item.label }}</span>
                <div class="right">
                  <v-ons-switch v-model="push.preferences[item.key]"
                                @change="savePushPreferences()"></v-ons-switch>
                </div>
              </v-ons-list-item>
              <v-ons-list-item>
                <div class="right">
                  <v-ons-button modifier="outline" class="smallBtn" id="push_test_btn"
                                :disabled="pushTesting" @click="sendTestPush()">テスト通知を送る</v-ons-button>
                </div>
              </v-ons-list-item>
            </template>
          </template>
          <v-ons-list-item modifier="chevron" @click="openICal()">
            <v-ons-icon icon="fa-calendar-alt" size="20px" class="gray mr-5"></v-ons-icon> カレンダー同期
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
        <br>
        <v-ons-list>
          <a href="mailto:motoy3d@gmail.com?subject=ツバサップ問い合わせ" target="_blank"
          style="text-decoration: none; color:#1f1f21;">
            <v-ons-list-item modifier="chevron">
                <v-ons-icon icon="fa-info-circle" size="20px" class="gray mr-5"></v-ons-icon>問い合わせ・バグ報告・ご感想
            </v-ons-list-item>
          </a>
        </v-ons-list>
        <br><br>
        <v-ons-list>
          <v-ons-list-item modifier="chevron" onclick="$('#logout_dialog').show()">
            <v-ons-icon icon="fa-sign-out-alt" size="20px" class="gray mr-5"></v-ons-icon>ログアウト
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
  import InstallGuide from './InstallGuide.vue';
  import Cookies from 'js-cookie';
  import * as webPush from '../push.js';

  // 通知の種類(docs/design/110-web-push.md §7.3)。並びは画面の表示順
  const PUSH_PREFERENCE_ITEMS = [
    {key: 'new_post', label: '新しい投稿'},
    {key: 'comment_on_mine', label: '自分の投稿・自分がコメントした投稿へのコメント'},
    {key: 'comment_on_others', label: 'その他の投稿へのコメント'},
    {key: 'schedule_change', label: '予定の変更・中止'},
    {key: 'schedule_comment', label: '予定へのコメント（届くのは予定を作った人と指導者だけ）'}
  ];

  export default {
    components: { InstallGuide },
    data() {
      return {
        loading: false,
        errored: false,
        posting: false,
        isIOSAndPWA: this.$ons.platform.isIOS() && location.href.indexOf('launcher=true') != -1,
        push: {
          enabled: false,
          vapidPublicKey: null,
          preferences: {}
        },
        pushSupport: webPush.pushSupport(),
        pushPreferenceItems: PUSH_PREFERENCE_ITEMS,
        deviceOn: false,   // この端末で購読しているか
        pushBusy: false,   // 購読・解除の処理中
        pushTesting: false
      }
    },
    mounted() {
      this.loadPushConfig();
    },
    computed: {
      // 「この端末で通知を受け取る」スイッチ。
      // 通知の許可は iOS では利用者の操作の中でしか求められないので、セッターの中で(await より前に)求める
      deviceSwitch: {
        get() {return this.deviceOn;},
        set(on) {
          this.deviceOn = on; // いったん見た目どおりにし、失敗したら戻す(戻したことがスイッチに伝わるように)
          if (on) {
            this.turnOnPush();
          } else {
            this.turnOffPush();
          }
        }
      },
      pushUnavailableMessage() {
        switch (this.pushSupport) {
          case 'ios-needs-home-screen':
            return 'iPhoneでは、ホーム画面に追加したアイコンから開くと通知を受け取れます。';
          case 'denied':
            return '通知が拒否されています。端末の設定からこのアプリの通知を許可してください。';
          default:
            return 'この端末・ブラウザでは通知を使えません。メール通知をご利用ください。';
        }
      },
      mailNotificationFlg: {
        get() {return this.$store.state.navigator.user.mail_notification_flg == 1},
        set(mailNotificationFlg) {this.$store.state.navigator.user.mail_notification_flg = mailNotificationFlg;}
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
                    })
                    .catch(() => {}); // 利用者への通知は http-errors.js で済んでいる
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
                      })
                      .catch(() => {}); // 利用者への通知は http-errors.js で済んでいる
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
          this.$http.get('/api/me').then((response)=>{
            this.$store.commit('navigator/setUser', response.data);
          }).catch(() => {}); // 利用者への通知は http-errors.js で済んでいる
          this.loading = false; this.posting = false;
        })
        .catch(error => {
          console.log(error);
          if (error.response.status === 401) {window.location.href = "/login";}
          this.loading = false; this.posting = false;
        });
      },
      /** 通知の設定を読み込み、この端末の購読状態をスイッチに反映する */
      loadPushConfig() {
        this.$http.get('/api/push/config')
          .then(response => {
            const data = response.data || {};
            this.push.enabled = !!data.enabled;
            this.push.vapidPublicKey = data.vapid_public_key;
            this.push.preferences = Object.assign({}, data.preferences || {});
            if (this.push.enabled) {
              this.refreshDeviceState();
            }
          })
          .catch(() => {
            // 設定が取れないときはスイッチを出さない(メール通知はそのまま使える)
            this.push.enabled = false;
          });
      },
      refreshDeviceState() {
        this.pushSupport = webPush.pushSupport();
        if (this.pushSupport !== 'ok' || Notification.permission !== 'granted') {
          this.deviceOn = false;
          return;
        }
        webPush.currentSubscription()
          .then(subscription => {
            this.deviceOn = !!subscription;
            if (subscription) {
              // サーバ側の購読情報を最新にする。同じ端末で別の人がログインした場合も、
              // 今ログインしている人の購読として付け替わる
              webPush.sendSubscription(subscription).catch(() => {});
            }
          })
          .catch(() => { this.deviceOn = false; });
      },
      turnOnPush() {
        if (this.pushBusy) {return;}
        this.pushBusy = true;
        // ここより前に await を挟まないこと(iOS は操作の直後でないと許可を求められない)
        webPush.requestPermission()
          .then(permission => {
            if (permission !== 'granted') {
              this.deviceOn = false;
              this.pushSupport = webPush.pushSupport();
              // 許可のダイアログを閉じただけ(default)なら、もう一度スイッチから求められるので何も出さない
              if (permission === 'denied') {
                this.$ons.notification.alert(
                  '通知が拒否されています。端末の設定からこのアプリの通知を許可してください。', {title: ''});
              }
              return;
            }
            return webPush.subscribe(this.push.vapidPublicKey)
              .then(() => {
                this.$ons.notification.toast('この端末で通知を受け取ります', {timeout: 2000});
              });
          })
          .catch(error => {
            console.log(error);
            this.deviceOn = false;
            // 通信エラーは http-errors.js が知らせる。それ以外(購読の失敗)はここで知らせる
            if (!error || !error.response) {
              this.$ons.notification.alert('通知の登録に失敗しました。時間をおいてもう一度お試しください。', {title: ''});
            }
          })
          .then(() => { this.pushBusy = false; });
      },
      turnOffPush() {
        if (this.pushBusy) {return;}
        this.pushBusy = true;
        webPush.unsubscribe()
          .catch(error => {
            // 端末側の購読は取り消し済み。サーバに残った分は次の送信で消える
            console.log(error);
          })
          .then(() => { this.pushBusy = false; });
      },
      savePushPreferences() {
        // v-model の反映を待ってから送る
        this.$nextTick(() => {
          this.$http.put('/api/push/preferences', {preferences: this.push.preferences})
            .then(response => {
              if (response.data && response.data.preferences) {
                this.push.preferences = Object.assign({}, response.data.preferences);
              }
            })
            .catch(error => {
              console.log(error);
              // 保存できなかったので、サーバの状態に戻す
              this.loadPushConfig();
            });
        });
      },
      sendTestPush() {
        this.pushTesting = true;
        this.$http.post('/api/push/test')
          .then(response => {
            const sent = response.data ? response.data.sent : 0;
            this.$ons.notification.toast('テスト通知を送りました（' + sent + '台）', {timeout: 3000});
          })
          .catch(error => { console.log(error); })
          .then(() => { this.pushTesting = false; });
      },
      showIOSInstallGuide() {
        webPush.showIOSInstallGuide(this.$ons);
      },
      async logout() {
        // ログアウトしたら、この端末の通知の購読も解除する。家族で端末を共有していると、
        // 残したままでは前の人宛ての通知（投稿の冒頭など）が届き続けてしまうため。
        // 通信が遅くてもログアウトを待たせないよう、最大3秒で打ち切る
        try {
          await Promise.race([
            webPush.unsubscribe(),
            new Promise(resolve => setTimeout(resolve, 3000))
          ]);
        } catch (e) {
          // 解除に失敗してもログアウトは続ける（サーバの購読は次の送信で無効と分かった時点で消える）
        }
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