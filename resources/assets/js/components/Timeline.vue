<template>
  <v-ons-page>
    <v-ons-toolbar class="navbar">
      <div class="left toolbar__left">
        <v-ons-toolbar-button @click="$store.commit('splitter/toggle');">
          <v-ons-icon icon="fa-bars" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
        <v-ons-toolbar-button @click="openCalendar()">
          <v-ons-icon icon="fa-calendar" size="24px" class="mb-5"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
      <div class="center toolbar__center">
        <v-ons-search-input placeholder="検索" class="timeline_search2">
        </v-ons-search-input>
      </div>
      <div class="toolbar__right mr-5">
        <v-ons-toolbar-button onclick="fn.openPage('html/post.html','lift');">
          <v-ons-icon icon="fa-plus" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
        <!-- TODO いずれ動画アップ機能追加
              <v-ons-toolbar-button onclick="alert('動画アップロード画面')">
                <v-ons-icon icon="fa-cloud-upload" size="28px"></v-ons-icon>
              </v-ons-toolbar-button>
        -->
      </div>
    </v-ons-toolbar>
    <!-- メインコンテンツ -->
    <section v-if="errored">
      <p>ごめんなさい。エラーになりました。時間をおいてアクセスしてくださいm(_ _)m</p>
    </section>
    <section v-else>
      <div v-if="loading">読み込み中...</div>
      <v-ons-list v-else id="timeline_list">
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
              <v-ons-icon icon="fa-circle" class="new_icon" size="16px"></v-ons-icon>
              {{ post.title }}</p>
            <p class="updated_at">3.10 15:39　片岡瑛太(父)</p>
          </div>
          <div class="entry_content">
          <span>{{ post.content }}
          </span>
            <div class="mt-10">
              <v-ons-icon icon="fa-comment-o" class="small gray">
                <span>4</span>
              </v-ons-icon>
              <v-ons-icon icon="fa-list-alt" class="small gray ml-10">
                <span>アンケート</span>
              </v-ons-icon>
            </div>
          </div>
        </v-ons-list-item>

<!--
        <v-ons-list-item tappable modifier="chevron"
                       onclick="homeNavi.pushPage('html/article.html', {data: {fromPage: 'timeline'}})">
          <div class="entry_title_row">
            <p class="entry_title">
              <v-ons-icon icon="fa-circle" class="new_icon" size="16px"></v-ons-icon>
              3/11(日)練習場所変更</p>
            <p class="updated_at">3.10 15:39　片岡瑛太(父)</p>
          </div>
          <div class="entry_content">
          <span>3/11(日)の練習場所を変更いたします。<br>
          </span>
            <div class="mt-10">
              <v-ons-icon icon="fa-comment-o" class="small gray">
                <span>4</span>
              </v-ons-icon>
              <v-ons-icon icon="fa-list-alt" class="small gray ml-10">
                <span>アンケート</span>
              </v-ons-icon>
            </div>
          </div>
        </v-ons-list-item>

        <v-ons-list-item tappable modifier="chevron"
                       onclick="homeNavi.pushPage('html/article.html', {data: {fromPage: 'timeline'}})">
          <div class="entry_title_row">
            <p class="entry_title">3/10（土）TRM@太尾小</p>
            <p class="updated_at">3.3 10:54　片岡瑛太(父)</p>
          </div>
          <div class="entry_content">
          <span>
            ・・・・本文・・・。<br>
          </span>
          </div>
        </v-ons-list-item>

        <v-ons-list-item tappable modifier="chevron"
                       onclick="homeNavi.pushPage('html/article.html', {data: {fromPage: 'timeline'}})">
          <div class="entry_title_row">
            <p class="entry_title">3/4（日）送り出し会＠綱島東小</p>
            <p class="updated_at">3.2 20:54　片岡瑛太(父)</p>
          </div>
          <div class="entry_content">
          <span>
            出欠確認を行います。<br>
          </span>
            <div class="mt-10">
              <v-ons-icon icon="fa-comment-o" class="small gray">
                <span>1</span>
              </v-ons-icon>
              <v-ons-icon icon="fa-list-alt" class="small gray ml-10">
                <span>アンケート</span>
              </v-ons-icon>
            </div>
          </div>
        </v-ons-list-item>

        <v-ons-list-item tappable modifier="chevron">
          <div class="entry_title_row">
            <p class="entry_title">3/4(日) xxxx</p>
            <p class="updated_at">3.2 20:54　片岡瑛太(父)</p>
          </div>
          <div class="entry_content">
          <span>
            ・・・・・・・・<br>
          </span>
          </div>
        </v-ons-list-item>

        <v-ons-list-item tappable modifier="chevron">
          <div class="entry_title_row">
            <p class="entry_title">3/4(日) xxxx</p>
            <p class="updated_at">3.2 20:54　片岡瑛太(父)</p>
          </div>
          <div class="entry_content">
          <span>
            ・・・・・・・・<br>
          </span>
          </div>
        </v-ons-list-item>

        <v-ons-list-item tappable modifier="chevron">
          <div class="entry_title_row">
            <p class="entry_title">3/4(日) xxxx</p>
            <p class="updated_at">3.2 20:54　片岡瑛太(父)</p>
          </div>
          <div class="entry_content">
          <span>
            ・・・・・・・・<br>
          </span>
          </div>
        </v-ons-list-item>

        <v-ons-list-item tappable modifier="chevron">
          <div class="entry_title_row">
            <p class="entry_title">3/4(日) xxxx</p>
            <p class="updated_at">3.2 20:54　片岡瑛太(父)</p>
          </div>
          <div class="entry_content">
          <span>
            ・・・・・・・・<br>
          </span>
          </div>
        </v-ons-list-item>

        <v-ons-list-item tappable modifier="chevron">
          <div class="entry_title_row">
            <p class="entry_title">3/4(日) xxxx</p>
            <p class="updated_at">3.2 20:54　片岡瑛太(父)</p>
          </div>
          <div class="entry_content">
          <span>
            ・・・・・・・・<br>
          </span>
          </div>
        </v-ons-list-item>
-->
      </v-ons-list>
    </section>
  </v-ons-page>
</template>

<script>
  import Article from './Article.vue';
  import Calendar from './Calendar.vue';
  export default {
    beforeCreate() {
      console.log("Timeline#beforeCreate");
      this.$http.get('http://localhost:8000/api/posts')
        .then((response)=>{
          this.posts = response.data.data
          console.log(this.posts);
        })
        .catch(error => {
          console.log(error);
          this.errored = true;
        })
        .finally(() => this.loading = false);
    },
    methods: {
      openArticle(post_id) {
        console.log("post_id=" + post_id);
        this.$store.commit('navigator/push', {
          extends: Article,
          onsNavigatorOptions: {
            animation: 'slide'
          }
        });
      },
      openCalendar() {
        this.$store.commit('navigator/push', {
          extends: Calendar,
          onsNavigatorOptions: {
            animation: 'slide'
          }
        });
      }
    },
    data() {
      return {
        posts: {},
        loading: true,
        errored: false
      }
    }
  };
</script>
