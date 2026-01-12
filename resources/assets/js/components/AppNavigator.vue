<template>
  <!-- Vuetify root app wrapper - replaces v-ons-navigator -->
  <v-app id="homeNavi">
    <!-- Dynamic component rendering based on page stack -->
    <component
      v-for="(page, index) in pageStack"
      v-show="index === pageStack.length - 1"
      :is="page"
      :key="index"
    ></component>
  </v-app>
</template>

<script>
  import AppTabbar from './AppTabbar.vue';
  import Cookies from 'js-cookie';
  export default {
    beforeCreate() {
      // console.log("AppNavigator#beforeCreate");
      // ユーザー情報取得
      const self = this;
      this.$http.get('/api/me')
        .then((response)=>{
          // globalにユーザー情報セット
          console.log('⭐me=' + response.data);
          self.$store.commit('navigator/setUser', response.data);
        })
        // .catch(error => {
        //   // console.log(error);
        //   if (error.response.status == 401) {window.location.href = "/login";}
        // })
      ;
      this.$store.commit('navigator/setCurrentTeamName', Cookies.get('current_team_name'));
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
