<template>
  <div id="timeline_page">
    <!-- Header using Element UI -->
    <el-header class="timeline-header" height="60px">
      <div class="header-content">
        <img
          src="/img/appicon2.png"
          class="app-icon"
          alt="icon"
        />
        
        <div class="header-title">
          <template v-if="$store.state.navigator.user.myTeams">
            <template v-if="1 < $store.state.navigator.user.myTeams.length">
              <el-select
                id="teamSelection"
                v-model="currentTeamId"
                @change="changeCurrentTeam()"
                size="small"
              >
                <el-option
                  v-for="team in $store.state.navigator.user.myTeams"
                  :key="team.id"
                  :label="team.name"
                  :value="team.id"
                ></el-option>
              </el-select>
            </template>
            <template v-else>
              <span class="team-name">{{ $store.state.navigator.user.myTeams[0].name }}</span>
            </template>
          </template>
        </div>
        
        <el-button
          type="text"
          icon="el-icon-search"
          @click="showSearch()"
          class="search-btn"
        ></el-button>
      </div>
    </el-header>
    
    <!-- Search Popover -->
    <el-popover
      v-model="searchPopoverVisible"
      placement="bottom"
      width="300"
      trigger="manual"
    >
      <div class="search-form">
        <el-input
          v-model="searchKeyword"
          placeholder="キーワード"
          prefix-icon="el-icon-search"
          class="mb-3"
        ></el-input>
        
        <el-select
          v-model="searchCategoryId"
          placeholder="カテゴリー"
          class="mb-3 full-width"
        >
          <el-option
            v-for="cat in categories"
            :key="cat.id"
            :label="cat.name"
            :value="cat.id"
          ></el-option>
        </el-select>
        
        <div class="mb-3">
          <span>未読のみ</span>
          <el-switch
            v-model="searchUnread"
            class="ml-2"
          ></el-switch>
        </div>
        
        <el-button
          type="primary"
          @click="search()"
          class="full-width"
        >検索</el-button>
      </div>
    </el-popover>

    <!-- FAB Button -->
    <el-button
      v-if="!errored"
      type="primary"
      icon="el-icon-plus"
      circle
      size="large"
      @click="openPost()"
      class="timeline-fab"
    ></el-button>
    
    <section v-if="errored">
      <el-alert
        type="error"
        title="ごめんなさい。エラーになりました。時間をおいてアクセスしてくださいm(_ _)m"
        :closable="false"
        class="error-alert"
      ></el-alert>
    </section>
    <section v-else>
      <div v-if="$store.state.timeline.loading" class="progress-div">
        <i class="el-icon-loading" style="font-size: 32px; color: #409EFF;"></i>
      </div>
      <template v-else>
        <!-- TODO: Implement pull-to-refresh for Element UI -->
        <div class="timeline-content">
          <el-card
            v-for="post in posts"
            :key="post.id"
            class="timeline-card"
            @click.native="openArticle(post)"
            shadow="hover"
          >
            <div class="entry_title_row">
              <div class="entry_title">
                <i
                  v-if="!post.read_flg"
                  class="fa fa-circle"
                  style="color: orange; font-size: 8px; margin-right: 8px;"
                ></i>
                {{ post.title }}
              </div>
              <div class="updated_at">
                <template v-if="moment(new Date()).diff(moment(post.updated_at), 'days') <= 2">
                  {{ post.updated_at | moment("from") }}　
                </template>
                <template v-else>
                  {{ post.updated_at | moment('Y.M.D(dd) H:mm') }}
                </template>
                {{ post.updated_name }}
              </div>
            </div>
            <div class="entry_content">
              <span class="post_content">{{ post.content | truncate}}</span>
              <div class="post-meta" v-if="post.comment_count || post.questionnaire_id">
                <span v-if="post.comment_count" class="meta-item">
                  <i class="fa fa-comment"></i>
                  {{ post.comment_count }}
                </span>
                <span v-if="post.questionnaire_id" class="meta-item">
                  <i class="fa fa-list-alt"></i>
                  アンケート
                </span>
              </div>
            </div>
            <i class="fa fa-chevron-right chevron-icon"></i>
          </el-card>
          <div class="after_list" v-if="$store.state.timeline.nextPageUrl">
            <i class="el-icon-loading" style="font-size: 24px; color: #409EFF;"></i>
          </div>
        </div>
      </template>
    </section>
  </div>
</template>

<style scoped>
.timeline-header {
  background: #409EFF;
  color: white;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
}

.header-content {
  display: flex;
  align-items: center;
  height: 100%;
  padding: 0 16px;
}

.app-icon {
  width: 32px;
  height: 32px;
  margin-right: 12px;
}

.header-title {
  flex: 1;
}

.team-name {
  font-weight: bold;
  font-size: 16px;
}

.search-btn {
  color: white !important;
  font-size: 20px;
}

.search-form {
  padding: 8px;
}

.full-width {
  width: 100%;
}

.mb-3 {
  margin-bottom: 12px;
}

.timeline-fab {
  position: fixed;
  bottom: 80px;
  right: 20px;
  z-index: 99;
  width: 56px;
  height: 56px;
}

.error-alert {
  margin: 16px;
}

#timeline_page {
  padding-top: 60px;
  min-height: 100vh;
  background: #f5f5f5;
}

.progress-div {
  display: flex;
  justify-content: center;
  padding: 40px;
}

.timeline-content {
  padding: 12px;
}

.timeline-card {
  margin-bottom: 12px;
  cursor: pointer;
  position: relative;
  padding-right: 30px;
}

.entry_title_row {
  margin-bottom: 8px;
}

.entry_title {
  font-size: 16px;
  font-weight: bold;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
}

.updated_at {
  font-size: 12px;
  color: #909399;
}

.entry_content {
  font-size: 14px;
  color: #606266;
}

.post_content {
  display: block;
  margin-bottom: 8px;
}

.post-meta {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: #909399;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.chevron-icon {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  color: #C0C4CC;
}

.after_list {
  text-align: center;
  padding: 20px;
}
</style>

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
