<template>
  <v-ons-page id="timeline_page">
    <v-ons-toolbar class="navbar">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
      <div class="center">
        <span class="white">横浜SCつばさ</span>
      </div>
      <!-- TODO タイムライン検索 -->
      <!--<div class="center">-->
        <!--<v-ons-search-input placeholder="検索" class="timeline_search2">-->
        <!--</v-ons-search-input>-->
      <!--</div>-->
      <div class="right mr-5">
        <v-ons-toolbar-button @click="$store.dispatch('timeline/load', $http)">
          <v-ons-icon icon="fa-refresh" size="20px" class="white"></v-ons-icon>
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
      <v-ons-icon icon="fa-plus" @click="openPost();" ripple></v-ons-icon>
    </v-ons-fab>
    <section v-if="errored">
      <p>ごめんなさい。エラーになりました。時間をおいてアクセスしてくださいm(_ _)m</p>
    </section>
    <section v-else>
      <div v-if="$store.state.timeline.loading" class="progress-div">
        <v-ons-progress-circular indeterminate class="progress-circular"></v-ons-progress-circular>
      </div>
      <template v-else>
        <v-ons-page :infinite-scroll="loadMore">
          <v-ons-pull-hook
            :action="load"
            @changestate="state = $event.state"
          >
            <span v-show="state === 'initial'"><v-ons-icon icon="arrow-down"></v-ons-icon></span>
            <span v-show="state === 'preaction'"><v-ons-icon icon="arrow-down"></v-ons-icon></span>
            <span v-show="state === 'action'">
              <v-ons-icon icon="fa-spinner" size="26px" spin></v-ons-icon>
            </span>
          </v-ons-pull-hook>
          <v-ons-list id="timeline_list">
            <v-ons-list-item
              v-for="post in posts"
              :key="post.id"
              tappable modifier="chevron"
              @click="openArticle(post);">
              <div class="entry_title_row">
                <p class="entry_title">
                  <v-ons-icon icon="fa-circle" class="new_icon" size="16px"
                  v-if="!post.read_flg"></v-ons-icon>
                  {{ post.title }}</p>
                <p class="updated_at">
                  <template v-if="moment(new Date()).diff(moment(post.updated_at), 'days') <= 2">
                    {{ post.updated_at | moment("from") }}　
                  </template>
                  <template v-else>
                    {{ post.updated_at | moment('Y.M.D(dd) H:mm') }}
                  </template>
                  {{ post.updated_name }}</p>
              </div>
              <div class="entry_content">
                <span class="post_content">{{ post.content | truncate}}</span>
                <div class="mt-10" v-if="post.comment_count || post.questionnaire_id">
                  <v-ons-icon icon="fa-comment" class="small gray mr-10"
                    v-if="post.comment_count" style="font-weight:400">
                    <span class="ml-5">{{ post.comment_count }}</span>
                  </v-ons-icon>
                  <v-ons-icon icon="fa-list-alt" class="small gray"
                    v-if="post.questionnaire_id">
                    <span>アンケート</span>
                  </v-ons-icon>
                </div>
              </div>
            </v-ons-list-item>
          </v-ons-list>
          <div class="after_list" v-if="$store.state.timeline.nextPageUrl">
            <v-ons-icon icon="fa-spinner" size="26px" spin></v-ons-icon>
          </div>
        </v-ons-page>
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
      try {
        this.load();
      } catch($ex) {
        console.log($ex);
      }
    },
    methods: {
      load(done) {
        setTimeout(() => {
          this.$store.dispatch('timeline/load', this.$http);
          if (done) done(); //pull to refreshの時のみ使用
        }, 400);
      },
      loadMore(done) {
        this.$store.dispatch('timeline/loadMore', {'http': this.$http, 'done': done});
      },
      openArticle(post) {
        post.read_flg = true;
        this.$store.commit('article/setPostId', post.id);
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
        // this.$store.commit('tabbar/setTimelineBadge', this.$store.state.timeline.posts-1);
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
        state: 'initial',
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
  .after_list {
    margin: 20px;
    text-align: center;
  }
</style>