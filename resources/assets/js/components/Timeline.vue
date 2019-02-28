<template>
  <v-ons-page id="timeline_page">
    <v-ons-toolbar class="navbar">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
      <div class="center">
        <template v-if="$store.state.navigator.user.myTeams">
          <template v-if="1 < $store.state.navigator.user.myTeams.length">
            <v-ons-select id="teamSelection" v-model="currentTeamId" modifier="underbar"
                          class="mt-5 bold" @change="changeCurrentTeam()">
              <option v-for="team in $store.state.navigator.user.myTeams" :value="team.id" class="bold">
                {{ team.name }}
              </option>
            </v-ons-select>
          </template>
          <template v-else>
            <span class="white bold">{{ $store.state.navigator.user.myTeams[0].name }}</span>
          </template>
        </template>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button @click="showSearch($event);">
          <v-ons-icon icon="fa-search" size="20px" class="white"></v-ons-icon>
        </v-ons-toolbar-button>
        <!-- TODO いずれ動画アップ機能追加
              <v-ons-toolbar-button onclick="alert('動画アップロード画面')">
                <v-ons-icon icon="fa-cloud-upload" size="28px"></v-ons-icon>
              </v-ons-toolbar-button>
        -->
      </div>
    </v-ons-toolbar>
    <!-- 検索popover -->
    <v-ons-popover cancelable direction="down" cover-target="true"
                   class="search_popover"
                   :visible.sync="searchPopoverVisible"
                   :target="searchPopoverTarget">
      <div class="center space">
        <div>
          <v-ons-search-input placeholder="キーワード" class="keyword"
                              v-model="searchKeyword"></v-ons-search-input>
        </div>
        <div class="mt-20 mlr-10">
          <v-ons-select v-model="searchCategoryId" modifier="underbar" class="w-100p">
            <option v-for="cate in categories" :value="cate.id">
              {{ cate.name }}
            </option>
          </v-ons-select>
        </div>
        <div class="mt-20 mlr-10 left">
          未読のみ <v-ons-switch v-model="searchUnread"></v-ons-switch>
        </div>
        <div class="mt-30 mb-10">
          <v-ons-button class="search_btn" @click="search()">検索</v-ons-button>
        </div>
      </div>
    </v-ons-popover>

    <!-- ★★★メインコンテンツ -->
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
  import Cookies from 'js-cookie';
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
        if (done) {
          setTimeout(() => {
            this.$store.dispatch('timeline/load', this.$http);
            done(); //pull to refreshの時のみ使用
          }, 400);
        } else {
          this.$store.dispatch('timeline/load', this.$http);
        }
        this.loadCategories();
      },
      loadMore(done) {
        this.$store.dispatch('timeline/loadMore', {'http': this.$http, 'done': done});
      },
      openArticle(post) {
        if (!post.read_flg) {
          post.read_flg = true;
          this.$store.commit('timeline/setUnreadCount', this.$store.state.timeline.unreadCount - 1);
        }
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
      loadCategories() {
        this.$http.get('/api/posts/search_init')
          .then((response)=>{
            this.categories = [{id:null, name:'全カテゴリー'}].concat(response.data.categories);
          })
          .catch(error => {
            console.log(error);
            if (error.response.status === 401) {window.location.href = "/login";}
          });
      },
      showSearch(event) {
        this.searchPopoverTarget = event;
        this.searchPopoverVisible = true;
      },
      search() {
        console.log('search: ' + this.searchCategoryId);
        this.searchPopoverVisible = false;
        this.loading = true;
        this.$store.commit('timeline/setSearchKeyword', this.searchKeyword);
        this.$store.commit('timeline/setSearchCategoryId', this.searchCategoryId);
        this.$store.commit('timeline/setSearchUnread', this.searchUnread);
        this.load(() => {this.loading = false;});
      },
      changeCurrentTeam() {
        Cookies.set('current_team_id', this.currentTeamId);
        console.log('this.currentTeamId=' + this.currentTeamId);
        for (let t=0; t < this.$store.state.navigator.user.myTeams.length; t++) {
          let team = this.$store.state.navigator.user.myTeams[t];
          if (this.currentTeamId == team.id) {
            console.log('クッキー ' + team.name);
            Cookies.set('current_team_name', team.name); break;
          }
        }
        this.$http.get('/api/me').then((response)=>{
          this.$store.commit('navigator/setUser', response.data);// globalにユーザー情報セット
        });
        // 検索条件リセット
        this.searchKeyword = null;
        this.searchCategoryId = null;
        this.$store.commit('timeline/setSearchKeyword', this.searchKeyword);
        this.$store.commit('timeline/setSearchCategoryId', this.searchCategoryId);
        this.load(); //タイムラインリロード
        this.$store.dispatch('calendar/load', this.$http); //カレンダーリロード
        this.$store.dispatch('members/load', this.$http); //メンバーリロード
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
        errored: false,
        searchPopoverTarget: null,
        searchPopoverVisible: false,
        categories: null,
        searchKeyword: null,
        searchCategoryId: null,
        searchUnread: false,
        myTeams: null,
        currentTeamId: Cookies.get('current_team_id'),
        currentTeamName: Cookies.get('current_team_name')
      }
    }
  };
</script>

<style>
  .popover__content {
    width: 300px;
    max-width: 400px;
  }
  .search_popover {
    width: 300px;
    max-width: 400px;
  }
  .keyword {
    margin: 8px 0 8px 0;
    padding: 0 6px 0 6px;
    width: 250px;
    font-size: 16px;
    background-color: #F3F3F3;
  }
  .keyword > input {
    font-size: 16px;
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
  #teamSelection > .select-input {
    color: white !important;
    font-weight: bold !important;
  }
  .search_btn {
    width: 12rem;
  }

</style>