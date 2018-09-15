<template>
  <v-ons-page>
    <v-ons-splitter>
      <v-ons-splitter-side side="left" width="220px"
                           collapse swipeable :open.sync="isOpen">
        <v-ons-page>
          <div class="background splitter"></div>
          <v-ons-list class="menulist">
            <v-ons-list-item @click="loadView('Timeline')" class="menuitem" tappable>
              <v-ons-icon icon="fa-chevron-right" class="current_menu_icon"></v-ons-icon>
              <v-ons-icon icon="fa-rss" class="menu_icon" size="16px"></v-ons-icon>
              タイムライン
            </v-ons-list-item>
            <v-ons-list-item @click="loadView('Notifications')" class="menuitem" tappable>
              <v-ons-icon icon="fa-chevron-right" class="current_menu_icon hidden"></v-ons-icon>
              <v-ons-icon icon="fa-bell" class="menu_icon" size="16px"></v-ons-icon>
              通知
            </v-ons-list-item>
            <v-ons-list-item onclick="loadView('Members')" class="menuitem" tappable>
              <v-ons-icon icon="fa-chevron-right" class="current_menu_icon hidden"></v-ons-icon>
              <v-ons-icon icon="fa-user" class="menu_icon" size="16px"></v-ons-icon>
              メンバー
            </v-ons-list-item>
            <v-ons-list-item onclick="loadView('Settings')" class="menuitem" tappable>
              <v-ons-icon icon="fa-chevron-right" class="current_menu_icon hidden"></v-ons-icon>
              <v-ons-icon icon="fa-cog" class="menu_icon" size="16px"></v-ons-icon>
              設定
            </v-ons-list-item>
            <v-ons-list-item onclick="loadView('Contact')" class="menuitem" tappable>
              <v-ons-icon icon="fa-chevron-right" class="current_menu_icon hidden"></v-ons-icon>
              <v-ons-icon icon="fa-envelope" class="menu_icon" size="16px"></v-ons-icon>
              お問い合わせ
            </v-ons-list-item>
          </v-ons-list>
        </v-ons-page>
      </v-ons-splitter-side>
      <v-ons-splitter-content>
        <!--<app-tabbar></app-tabbar>-->
        <component
          :is="currentPage"
        ></component>
      </v-ons-splitter-content>
    </v-ons-splitter>
  </v-ons-page>
</template>

<script>
  import Timeline from './Timeline.vue';
  import Notifications from './Notifications.vue';
  import Article from './Article.vue';
  export default {
    data() {
      return {
        currentPage: Timeline
      };
    },
    computed: {
      isOpen: {
        get() {
          console.log("isOpen.get");
          return this.$store.state.splitter.open;
        },
        set(newValue) {
          console.log("isOpen.set " + newValue);
          this.$store.commit('splitter/toggle', newValue)
        }
      }
    },
    methods: {
      loadView(pageName) {
        if (pageName == 'Timeline') {
          this.currentPage = Timeline;
        } else if (pageName == 'Notifications') {
          this.currentPage = Notifications;
        }
        this.$store.commit('splitter/toggle');
      },
      loadLink(url) {
        window.open(url, '_blank');
      }
    }
  };
</script>

<style>
</style>
