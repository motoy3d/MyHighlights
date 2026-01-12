<template>
  <div id="timeline_page">
    <!-- App Bar - replaces v-ons-toolbar -->
    <v-app-bar color="primary" dark app>
      <v-img
        src="/img/appicon2.png"
        max-width="32"
        max-height="32"
        class="mr-3"
      ></v-img>
      
      <v-toolbar-title>
        <template v-if="$store.state.navigator.user.myTeams">
          <template v-if="1 < $store.state.navigator.user.myTeams.length">
            <v-select
              id="teamSelection"
              v-model="currentTeamId"
              :items="$store.state.navigator.user.myTeams"
              item-text="name"
              item-value="id"
              @change="changeCurrentTeam()"
              dense
              dark
              hide-details
              class="mt-5"
            ></v-select>
          </template>
          <template v-else>
            <span class="white--text font-weight-bold">{{ $store.state.navigator.user.myTeams[0].name }}</span>
          </template>
        </template>
      </v-toolbar-title>
      
      <v-spacer></v-spacer>
      
      <v-btn icon @click="showSearch()">
        <v-icon>fa-search</v-icon>
      </v-btn>
    </v-app-bar>
    
    <!-- Search Menu - replaces v-ons-popover -->
    <v-menu
      v-model="searchPopoverVisible"
      :close-on-content-click="false"
      offset-y
      max-width="300"
    >
      <v-card>
        <v-card-text>
          <v-text-field
            v-model="searchKeyword"
            placeholder="キーワード"
            prepend-icon="fa-search"
            class="mb-4"
          ></v-text-field>
          
          <v-select
            v-model="searchCategoryId"
            :items="categories"
            item-text="name"
            item-value="id"
            label="カテゴリー"
            class="mb-4"
          ></v-select>
          
          <div class="d-flex align-center mb-4">
            <span class="mr-2">未読のみ</span>
            <v-switch
              v-model="searchUnread"
              hide-details
            ></v-switch>
          </div>
          
          <v-btn
            color="primary"
            block
            @click="search()"
          >検索</v-btn>
        </v-card-text>
      </v-card>
    </v-menu>

    <!-- FAB - replaces v-ons-fab -->
    <v-btn
      v-if="!errored"
      fab
      fixed
      bottom
      right
      color="primary"
      @click="openPost()"
      class="mb-12"
    >
      <v-icon>fa-plus</v-icon>
    </v-btn>
    
    <section v-if="errored">
      <v-alert type="error" class="ma-4">
        ごめんなさい。エラーになりました。時間をおいてアクセスしてくださいm(_ _)m
      </v-alert>
    </section>
    <section v-else>
      <div v-if="$store.state.timeline.loading" class="progress-div">
        <v-progress-circular
          indeterminate
          color="primary"
          class="progress-circular"
        ></v-progress-circular>
      </div>
      <template v-else>
        <!-- TODO: Implement pull-to-refresh for Vuetify (no direct equivalent to v-ons-pull-hook) -->
        <v-container fluid class="pa-0">
          <v-list id="timeline_list">
            <v-list-item
              v-for="post in posts"
              :key="post.id"
              @click="openArticle(post)"
              two-line
            >
              <v-list-item-content>
                <div class="entry_title_row">
                  <v-list-item-title class="entry_title">
                    <v-icon
                      v-if="!post.read_flg"
                      color="orange"
                      small
                      class="mr-1"
                    >fa-circle</v-icon>
                    {{ post.title }}
                  </v-list-item-title>
                  <v-list-item-subtitle class="updated_at">
                    <template v-if="moment(new Date()).diff(moment(post.updated_at), 'days') <= 2">
                      {{ post.updated_at | moment("from") }}　
                    </template>
                    <template v-else>
                      {{ post.updated_at | moment('Y.M.D(dd) H:mm') }}
                    </template>
                    {{ post.updated_name }}
                  </v-list-item-subtitle>
                </div>
                <div class="entry_content">
                  <span class="post_content">{{ post.content | truncate}}</span>
                  <div class="mt-2" v-if="post.comment_count || post.questionnaire_id">
                    <v-icon
                      v-if="post.comment_count"
                      small
                      class="grey--text mr-2"
                    >fa-comment</v-icon>
                    <span v-if="post.comment_count" class="grey--text">{{ post.comment_count }}</span>
                    <v-icon
                      v-if="post.questionnaire_id"
                      small
                      class="grey--text ml-3"
                    >fa-list-alt</v-icon>
                    <span v-if="post.questionnaire_id" class="grey--text">アンケート</span>
                  </div>
                </div>
              </v-list-item-content>
              <v-list-item-action>
                <v-icon>fa-chevron-right</v-icon>
              </v-list-item-action>
            </v-list-item>
          </v-list>
          <div class="after_list" v-if="$store.state.timeline.nextPageUrl">
            <v-progress-circular
              indeterminate
              color="primary"
            ></v-progress-circular>
          </div>
        </v-container>
      </template>
    </section>
  </div>
</template>

<script>
  import Article from './Article.vue';
  import Post from './Post.vue';
  import Cookies from 'js-cookie';
  export default {
    mounted() {
      try {
        this.load();
        // TODO: Implement infinite scroll event listener for Vuetify
        // Previously used :infinite-scroll on v-ons-page
        window.addEventListener('scroll', this.handleScroll);
      } catch($ex) {
        console.log($ex);
      }
    },
    beforeDestroy() {
      window.removeEventListener('scroll', this.handleScroll);
    },
    methods: {
      handleScroll() {
        const bottomOfWindow = document.documentElement.scrollTop + window.innerHeight >= document.documentElement.offsetHeight - 100;
        if (bottomOfWindow && this.$store.state.timeline.nextPageUrl) {
          this.loadMore(() => {});
        }
      },
      load(done) {
        if (done) {
          setTimeout(() => {
            this.$store.dispatch('timeline/load', this.$http);
            done();
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
      showSearch() {
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
            Cookies.set('current_team_name', team.name);
            this.$store.commit('navigator/setCurrentTeamName', team.name);
            break;
          }
        }
        this.$http.get('/api/me').then((response)=>{
          this.$store.commit('navigator/setUser', response.data);
        });
        this.searchKeyword = null;
        this.searchCategoryId = null;
        this.$store.commit('timeline/setSearchKeyword', this.searchKeyword);
        this.$store.commit('timeline/setSearchCategoryId', this.searchCategoryId);
        this.load();
        this.$store.dispatch('calendar/load', this.$http);
        this.$store.dispatch('members/load', this.$http);
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

<style scoped>
  .entry_title_row {
    width: 97%;
  }
  .entry_title {
    font-size: 18px;
    font-weight: bold;
    text-align:left;
  }
  .updated_at {
    color: grey;
    font-size: 13px;
    text-align: left;
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
  .progress-div {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
  }
</style>
