<template>
  <!-- アプリ内のお知らせ(🔔。#125)。各タブのツールバーの右に置く。
       数は「まだ開いていない通知の数」で、ホーム画面のアイコンの数と同じ -->
  <v-ons-toolbar-button v-if="enabled" class="notice-bell" @click="openList()" aria-label="お知らせ">
    <v-ons-icon icon="fa-bell" size="20px" class="white"></v-ons-icon>
    <span v-if="unopened > 0" class="notice-bell-count">{{ unopened > 99 ? '99+' : unopened }}</span>
  </v-ons-toolbar-button>
</template>

<script>
  import Notifications from './Notifications.vue';
  import { installState, noticeState } from '../push.js';

  export default {
    computed: {
      // 段階的な公開の対象外の人には出さない(設計書 §4)
      enabled() { return installState.pushEnabled; },
      unopened() { return noticeState.unopened; }
    },
    methods: {
      openList() {
        this.$store.commit('navigator/push', {
          extends: Notifications,
          onsNavigatorOptions: { animation: 'lift' }
        });
      }
    }
  };
</script>

<style scoped>
  .notice-bell {
    position: relative;
  }
  .notice-bell-count {
    position: absolute;
    top: 2px;
    right: -2px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 8px;
    background: #e53935;
    color: #fff;
    font-size: 10px;
    font-weight: bold;
    line-height: 16px;
    text-align: center;
    box-sizing: border-box;
  }
</style>
