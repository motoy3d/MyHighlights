<template>
  <v-ons-navigator
    id="homeNavi" var="homeNavi"
    swipeable swipe-target-width="50px"
    :page-stack="pageStack"
    :pop-page="storePop"
    :options="options"
  ></v-ons-navigator>
</template>

<script>
  import AppSplitter from './AppSplitter.vue';
  import AppTabbar from './AppTabbar.vue';
  export default {
    beforeCreate() {
      // console.log("AppNavigator#beforeCreate");
      // ユーザー情報取得
      const self = this;
      this.$http.get('/api/me')
        .then((response)=>{
          // globalにユーザー情報セット
          // console.log('⭐me=' + response.data);
          self.$store.commit('navigator/setUser', response.data);
        })
        // .catch(error => {
        //   // console.log(error);
        //   if (error.response.status == 401) {window.location.href = "/login";}
        // })
      ;
      // navigatorにTabbarをpush
      this.$store.commit('navigator/push', AppTabbar);
    },
    data() {
      return {
      }
    },
    computed: {
      pageStack() {
        return this.$store.state.navigator.stack;
      },
      options() {
        return this.$store.state.navigator.options;
      }
    },
    methods: {
      storePop() {
        this.$store.commit('navigator/pop');
      }
    }
  };
</script>
