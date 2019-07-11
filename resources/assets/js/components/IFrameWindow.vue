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
        return document.documentElement.clientHeight;
      }
    },
    methods: {
    }
  };
</script>

<style>
</style>