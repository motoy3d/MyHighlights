<template>
  <v-ons-page id="post">
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <!--<v-ons-icon icon="fa-user-plus" size="20px"></v-ons-icon>-->
        <span></span>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-close" class="white" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
    </v-ons-toolbar>
    <div class="bg-white h-100p w-100p">
      <iframe :src="url" width="100%" height="100%"></iframe>
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
    props: ['url'],
    computed: {
    },
    methods: {
    }
  };
</script>

<style>
</style>