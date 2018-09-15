<template>
  <v-ons-page id="notification">
    <v-ons-toolbar class="navbar">
      <div class="left">
        <v-ons-toolbar-button @click="$store.commit('splitter/toggle');">
          <v-ons-icon icon="fa-bars" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
      <div class="center navbartitle">
        <v-ons-icon icon="fa-bell" size="20px"></v-ons-icon>
        <span>通知</span>
      </div>
    </v-ons-toolbar>
    <v-ons-row>
      <v-ons-col>
        <v-ons-list>
          <v-ons-list-item modifier="chevron" tappable="true"
                         onclick="notificationNavi.pushPage('article.html', {data: {fromPage: 'notification', article_id: 'xxx'}});">
            <v-ons-row>
              <v-ons-col width="70px" align="left" class="notif-time">23時間前</v-ons-col>
            </v-ons-row>
            <v-ons-row>
              <v-ons-col align="left">
                <div><strong>片岡基</strong>さんが投稿しました。</div>
                <div class="mt-10">明日の練習の用意</div>
              </v-ons-col>
            </v-ons-row>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" tappable="true"
                         onclick="notificationNavi.pushPage('article.html', {data: {fromPage: 'notification', article_id: 'xxx'}});">
            <v-ons-row>
              <v-ons-col width="70px" align="left" class="notif-time">昨日 16:33</v-ons-col>
            </v-ons-row>
            <v-ons-row>
              <v-ons-col align="left">
                <div><strong>山ノ内孝之さん</strong>が動画をアップしました。</div>
                <div class="mt-10">区大会 vs駒林SC</div>
              </v-ons-col>
            </v-ons-row>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" tappable="true"
                         onclick="notificationNavi.pushPage('article.html', {data: {fromPage: 'notification', article_id: 'xxx'}});">
            <v-ons-row>
              <v-ons-col width="70px" align="left" class="notif-time">12/21 9:11</v-ons-col>
            </v-ons-row>
            <v-ons-row>
              <v-ons-col align="left">
                <div><strong>田中杏子さん</strong>、他5人が動画にいいね
                  <v-ons-icon icon="fa-thumbs-o-up" class="black"></v-ons-icon>
                  しました。</div>
                <div class="mt-10">TRM vsみなとFC</div>
              </v-ons-col>
            </v-ons-row>
          </v-ons-list-item>
          <v-ons-list-item modifier="chevron" tappable="true"
                         onclick="notificationNavi.pushPage('article.html', {data: {fromPage: 'notification', article_id: 'xxx'}});">
            <v-ons-row>
              <v-ons-col width="70px" align="left" class="notif-time">12/20 20:01</v-ons-col>
            </v-ons-row>
            <v-ons-row>
              <v-ons-col align="left">
                <div><strong>高橋望さん</strong>が動画にコメントしました。</div>
                <div class="mt-10">ナイスゴール！</div>
              </v-ons-col>
            </v-ons-row>
          </v-ons-list-item>
        </v-ons-list>
      </v-ons-col>
    </v-ons-row>
  </v-ons-page>
</template>

<script>
  export default {
  };
</script>

<style></style>