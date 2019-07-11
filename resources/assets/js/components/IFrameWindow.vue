<template>
  <v-ons-page id="iframe">
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <!--<v-ons-icon icon="fa-user-plus" size="20px"></v-ons-icon>-->
        <span>{{ originalFileName }}</span>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-close" class="white" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
    </v-ons-toolbar>
    <!--<div class="h-100p w-100p">-->
    <div>
      <iframe :src="url" :width="iframeWidth" :height="iframeHeight"></iframe>
    </div>
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
        return document.documentElement.clientHeight - 100;
      }
    },
    methods: {
    }
  };
</script>

<style>
</style>