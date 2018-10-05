<template>
  <v-ons-page id="timeline_page">
    <v-ons-toolbar class="navbar">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
      <div class="center">
        <v-ons-search-input placeholder="検索" class="timeline_search2">
        </v-ons-search-input>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button @click="$store.dispatch('timeline/loadTimeline', $http)">
          <v-ons-icon icon="fa-refresh" size="24px" class="white"></v-ons-icon>
        </v-ons-toolbar-button>
        <!-- TODO いずれ動画アップ機能追加
              <v-ons-toolbar-button onclick="alert('動画アップロード画面')">
                <v-ons-icon icon="fa-cloud-upload" size="28px"></v-ons-icon>
              </v-ons-toolbar-button>
        -->
      </div>
    </v-ons-toolbar>
    <!-- メインコンテンツ -->
    <v-ons-fab position="bottom right" v-if="!errored" ripple>
      <v-ons-icon icon="fa-plus" @click="openPost();" ripple/>
    </v-ons-fab>
    <section v-if="errored">
      <p>ごめんなさい。エラーになりました。時間をおいてアクセスしてくださいm(_ _)m</p>
    </section>
    <section v-else>
      <div v-if="$store.state.timeline.loading" class="progress-div">
        <v-ons-progress-circular indeterminate class="progress-circular"></v-ons-progress-circular>
      </div>
      <template v-else>
        <v-ons-list id="timeline_list">
          <!--		<v-ons-list-item>
                <v-ons-search-input placeholder="検索" class="timeline_search">
                </v-ons-search-input>
              </v-ons-list-item>-->
          <v-ons-list-item
            v-for="post in posts"
            :key="post.id"
            tappable modifier="chevron"
            @click="openArticle(post.id);">
            <div class="entry_title_row">
              <p class="entry_title">
                <v-ons-icon icon="fa-circle" class="new_icon" size="16px"
                v-if="!post.read_flg"></v-ons-icon>
                {{ post.title }}</p>
              <p class="updated_at">
                {{ post.updated_at | moment("from") }}　
                {{ post.updated_name }}</p>
            </div>
            <div class="entry_content">
              <span class="post_content">{{ post.content | truncate}}</span>
              <div class="mt-10" v-if="post.comment_count || post.quetionnaire_id">
                <v-ons-icon icon="fa-comment-o" class="small gray"
                  v-if="post.comment_count">
                  <span class="ml-5">{{ post.comment_count }}</span>
                </v-ons-icon>
                <v-ons-icon icon="fa-list-alt" class="small gray ml-10"
                  v-if="post.quetionnaire_id">
                  <span>アンケート</span>
                </v-ons-icon>
              </div>
            </div>
          </v-ons-list-item>
        </v-ons-list>
      </template>
    </section>
  </v-ons-page>
</template>

<script>
  import Article from './Article.vue';
  import Post from './Post.vue';
  import Calendar from './Calendar.vue';
  export default {
    mounted() {
      this.$store.dispatch('timeline/loadTimeline', this.$http);
    },
    methods: {
      openArticle(post_id) {
        // console.log("post_id=" + post_id);
        this.$store.commit('article/setPostId', post_id);
        this.$store.commit('navigator/push', {
          extends: Article,
          onsNavigatorOptions: {animation: 'slide'}
        });
      },
      openPost() {
        this.$store.commit('navigator/push', {
          extends: Post,
          onsNavigatorOptions: {animation: 'lift'}
        });
      },
      openCalendar() {
        this.$store.commit('navigator/push', {
          extends: Calendar,
          onsNavigatorOptions: {animation: 'slide'}
        });
      }
    },
    computed: {
      posts : {
        get() {return this.$store.state.timeline.posts;}
      }
    },
    data() {
      return {
        loading: true,
        errored: false
      }
    }
  };
</script>

<style>
  .timeline_search {
    margin: auto;
    width: 50%;
  }
  .timeline_search2 {
    margin: 8px 0 8px 0;
    width: 90%;
  }
  .timeline_item_read {
    background-color: #f2f2f2;
  }
  .new_icon {
    color: #ff6633;
    margin-right: 3px;
  }
  .entry_title_row {
    width: 97%;
  }
  .entry_title {
    font-size: 18px;
    font-weight: bold;
    text-align:left;
    margin: 0;
  }
  .updated_at {
    color: grey;
    font-size: 13px;
    text-align: left;
    margin: 0 0 0 5px;
  }
  .entry_content {
    width: 95%;
    text-align:left;
    margin: 5px 0 0 5px;
  }
  .post_content {
    white-space: pre-wrap;
  }
</style>