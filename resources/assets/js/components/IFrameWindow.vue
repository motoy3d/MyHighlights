<template>
  <v-ons-page id="iframe">
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <span>{{ originalFileName }}</span>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-close" class="white" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
    </v-ons-toolbar>
    <iframe :src="url" :width="iframeWidth" :height="iframeHeight"></iframe>
  </v-ons-page>
</template>

<script>
  // app.scss の --safe-area-bottom (= env(safe-area-inset-bottom)) を px で読む。
  // カスタムプロパティのままだと getPropertyValue() が式の文字列を返すので、
  // 実際のプロパティに当てて計算済みの値を取る
  function safeAreaBottom() {
    const probe = document.createElement('div');
    probe.style.cssText = 'position:fixed;visibility:hidden;padding-bottom:var(--safe-area-bottom)';
    document.body.appendChild(probe);
    const px = parseFloat(getComputedStyle(probe).paddingBottom) || 0;
    probe.remove();
    return px;
  }

  export default {
    data() {
      return {
        loading: true,
      }
    },
    props: ['url', 'originalFileName'],
    computed: {
      iframeWidth() {
        return document.documentElement.clientWidth;
      },
      iframeHeight() {
        // 45はツールバー。画面下端のセーフエリア(ホームインジケーター)の分も除く
        return document.documentElement.clientHeight - 45 - safeAreaBottom();
      }
    },
    methods: {
    }
  };
</script>

<style>
</style>