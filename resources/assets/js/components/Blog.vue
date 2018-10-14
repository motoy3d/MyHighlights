<template>
  <v-ons-page>
    <v-ons-toolbar class="navbar">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
      <div class="center navbartitle">
        <v-ons-icon icon="fa-rss" size="20px"></v-ons-icon> ブログ
      </div>
    </v-ons-toolbar>
    <div class="page__background" style="background-color: white;"></div>
    <section v-if="errored">
      <p>ごめんなさい。エラーになりました。時間をおいてアクセスしてくださいm(_ _)m</p>
    </section>
    <section v-else>
      <div v-if="loading" class="progress-div">
        <v-ons-progress-circular indeterminate class="progress-circular"></v-ons-progress-circular>
      </div>
      <template v-else>
        <v-ons-row>
          <v-ons-col>
            <!--<v-ons-page :infinite-scroll="loadMore">-->
              <v-ons-list>
                <v-ons-list-item v-for="(entry,index) in entries" :key="entry.link"
                                 modifier="chevron" tappable @click="openEntry(entry.link)">
                  <div class="blog_row">
                    <p class="blog_entry_date">{{ entry.date }}</p>
                    <p class="blog_entry_title">{{ entry.title }}</p>
                  </div>
                </v-ons-list-item>
              </v-ons-list>
            <!--</v-ons-page>-->
          </v-ons-col>
        </v-ons-row>
      </template>
    </section>
  </v-ons-page>
</template>

<script>
  export default {
    beforeCreate() {
      this.loading = true;
      let self = this;
      this.$http.get('api/blog')
        .then((response)=> {
          console.log(response.data);
          this.entries = response.data;
        })
        .catch(error => {
          this.errored = true;
          console.log(error);
        })
        .finally(() => this.loading = false)
      ;
    },
    data() {
      return {
        entries: [],
        loading: false,
        errored: false
      };
    },
    methods: {
      openEntry(link) {
        location.href = link;
      }
    }
  };
</script>

<style>
  .blog_row {
    width: 92%;
  }
  .blog_entry_title {
    font-size: 18px;
    text-align:left;
    margin: 8px 0px;
  }
  .blog_entry_date {
    color: grey;
    font-size: 13px;
    text-align: left;
    margin: 0;
  }
</style>