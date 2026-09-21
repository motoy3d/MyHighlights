<template>
  <!-- ホーム画面への追加の案内(#55)。設定画面とタイムラインの上部に出す -->
  <div class="install_guide" v-if="visible">
    <v-ons-icon icon="fa-times" class="install_guide_close gray" size="18px"
                @click="dismiss()"></v-ons-icon>
    <div class="install_guide_text">
      <v-ons-icon icon="fa-plus-square" class="goodblue mr-5"></v-ons-icon>
      ホーム画面に追加すると、アプリのように使え、通知も受け取れます
    </div>
    <div class="right mt-5">
      <v-ons-button v-if="canPrompt" modifier="outline" class="smallBtn"
                    @click="install()">ホーム画面に追加</v-ons-button>
      <v-ons-button v-else modifier="outline" class="smallBtn"
                    @click="showGuide()">方法を見る</v-ons-button>
    </div>
  </div>
</template>

<script>
  import {
    installState, isIOS, isStandalone, dismissInstallGuide,
    showIOSInstallGuide, promptInstall
  } from '../push.js';
  export default {
    data() {
      return {
        ios: isIOS(),
        standalone: isStandalone()
      };
    },
    computed: {
      dismissed() {
        return installState.dismissed;
      },
      // Android の Chrome が beforeinstallprompt を出したときだけ、追加ボタンを出せる
      canPrompt() {
        return !!installState.deferredPrompt;
      },
      // iPhone は Safari で開いているとき、Android は追加できるときだけ出す
      visible() {
        return !this.dismissed && !this.standalone && (this.ios || this.canPrompt);
      }
    },
    methods: {
      showGuide() {
        showIOSInstallGuide(this.$ons);
      },
      install() {
        promptInstall().then(accepted => {
          if (accepted) {
            dismissInstallGuide();
          }
        });
      },
      // 閉じたら 14 日間は出さない
      dismiss() {
        dismissInstallGuide();
      }
    }
  };
</script>

<style>
  .install_guide {
    position: relative;
    margin: 8px;
    padding: 10px 32px 10px 12px;
    background-color: #eef5ff;
    border: 1px solid #c7d8ef;
    border-radius: 6px;
    font-size: 14px;
    text-align: left;
  }
  .install_guide_close {
    position: absolute;
    top: 8px;
    right: 10px;
  }
</style>
