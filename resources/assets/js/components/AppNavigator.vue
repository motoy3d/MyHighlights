<template>
  <v-ons-navigator
    id="homeNavi" var="homeNavi"
    swipeable swipe-target-width="50px"
    :page-stack="pageStack"
  ></v-ons-navigator>
</template>

<script>
  import AppSplitter from './AppSplitter.vue';
  export default {
    data() {
      return {
        pageStack: [AppSplitter]
      }
    }
  };
</script>
