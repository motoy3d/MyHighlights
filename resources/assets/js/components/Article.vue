<template>
  <!-- ■■■■■■■■■■■■■■ 投稿詳細 ■■■■■■■■■■■■■■ -->
  <v-ons-page class="bg-white">
    <!-- ツールバー -->
    <v-ons-toolbar class="navbar">
      <div class="left ml-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-angle-left" class="white" size="32px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
      <!-- 実装あとで -->
      <!--<div class="right mr-5">-->
        <!--<v-ons-toolbar-button>-->
          <!--<v-ons-icon icon="fa-pen" class="white" size="20px" @click="openEditPost()"></v-ons-icon>-->
        <!--</v-ons-toolbar-button>-->
      <!--</div>-->
    </v-ons-toolbar>
    <!-- メインコンテンツ -->
    <div class="page__background" style="background-color: white;"></div>
    <section v-if="errored">
      <p>ごめんなさい。エラーになりました。時間をおいてアクセスしてくださいm(_ _)m</p>
    </section>
    <section v-else>
      <div v-if="loading" class="progress-div">
        <v-ons-progress-circular indeterminate class="progress-circular"></v-ons-progress-circular>
      </div>
      <template v-else>
        <v-ons-row class="space">
          <v-ons-col>
            <div class="entry_title_row">
              <p class="entry_title">{{ post.title }}</p>
              <p class="updated_at">
                {{ post.created_at | moment('Y.M.D(dd) H:mm') }}
                　{{ post.created_name }}</p>
            </div>
            <div class="entry_content"><span>{{ post.content }}</span>
              <template v-for="att in post_attachments">
                <div v-if="isImage(att.file_type)" class="mt-30">
                  <img :src="att.file_path" class="image_in_post">
                  <div>
                    <a :href="att.file_path" :download="att.original_file_name" target="_blank">
                      <v-ons-icon icon="fa-download" class="fl-right lightgray"
                                  size="22px"></v-ons-icon>
                    </a>
                  </div>
                </div>
                <div v-else class="pt-10">
                  <span>{{ att.original_file_name }}</span>
                  <a :href="att.file_path">
                    <v-ons-icon icon="fa-download" class="fl-right lightgray"
                              size="22px"></v-ons-icon>
                  </a>
                </div>
              </template>
            </div>
          </v-ons-col>
        </v-ons-row>
        <!-- アンケート -->
        <v-ons-row class="space" v-if="questionnaire">
          <v-ons-col>
            <p class="bold mb-0"><v-ons-icon icon="fa-list-alt" class="black"></v-ons-icon>
              {{ questionnaire.title }}</p>
            <div>
              <table class="questionnaire_table">
                <tr><th></th><th>◯</th><th>△</th><th>✕</th><th></th></tr>
                <tr v-for="(q, index) in questionnaire.items">
                  <td>{{ q.text }}</td>
                  <td class="answer" @click="showAnswerModal(q, '◯')">
                    <a href="#" v-if="q.answerCounts['◯']"> {{ q.answerCounts['◯'] }} </a>
                    <span v-else>0</span>
                  </td>
                  <td class="answer" @click="showAnswerModal(q, '△')">
                    <a href="#" v-if="q.answerCounts['△']"> {{ q.answerCounts['△'] }} </a>
                    <span v-else>0</span>
                  </td>
                  <td class="answer" @click="showAnswerModal(q, '✕')">
                    <a href="#" v-if="q.answerCounts['✕']"> {{ q.answerCounts['✕'] }} </a>
                    <span v-else>0</span>
                  </td>
                  <td class="questionnaire_btn">
                    <v-ons-button class="smallBtn" modifier="quiet"
                                  @click="showQuestionnaireActionSheet(q.text, index);">
                      回答 <span class="black">{{ q.myAnswer }}</span>
                    </v-ons-button>
                  </td>
                </tr>
              </table>
            </div>
          </v-ons-col>
        </v-ons-row>
        <v-ons-row class="space">
          <v-ons-col class="bordertop">
            <div class="mt-10 ml-5">
              <v-ons-icon icon="fa-heart" class="heart" :style="isHeartOn? '' : 'font-weight:400'"
                          @click="toggleHeart();">
                <span class="heart_text">いいね</span>
                <span class="heart-count" v-if="heartCount">{{ heartCount }}</span>
              </v-ons-icon>
              <!--<v-ons-icon :icon="isStarOn? 'fa-star' : 'fa-star-o'" class="star"-->
                          <!--@click="toggleStar();">-->
                <!--<span class="star_text">お気に入り保存</span>-->
                <!--<span class="star-count" v-if="starCount">{{ starCount }}</span>-->
              <!--</v-ons-icon>-->
            </div>
            <!-- コメント入力 -->
            <v-ons-row class="mt-30">
              <v-ons-col width="30px" vertical-align="bottom" class="left">
                <div class="upload-btn-wrapper">
                  <span class="notification" v-if="0 < comment_files.length">
                    {{ comment_files.length }}</span>
                    <v-ons-icon icon="fa-paperclip" class="goodblue" size="24px"
                                ></v-ons-icon>
                  <input type="file" multiple @change="onFileSet"/>
                </div>
              </v-ons-col>
              <v-ons-col vertical-align="bottom">
                <textarea class="textarea comment_textarea" rows="3" placeholder="コメント"
                          v-model="comment_text" @keyup="fitTextarea()"></textarea>
              </v-ons-col>
              <v-ons-col width="50px" vertical-align="bottom" class="center">
                <v-ons-button class="ml-5 mt-10 center" ripple
                  @click="postComment()">
                  <v-ons-icon icon="fa-paper-plane" class="messageBtn"></v-ons-icon></v-ons-button>
              </v-ons-col>
            </v-ons-row>
          </v-ons-col>
        </v-ons-row>
        <!-- コメント -->
        <v-ons-row class="space lastspace" v-if="comments">
          <v-ons-col>
            <div class="mt-10 ml-15" v-for="(comment, index) in comments" :key="comment.id">
              <!--<hr class="mt-15">-->
              <div>
                <span class="bold">
                  {{ comment.name }}
                </span>
                <span class="updated_at">
                  <template v-if="moment(new Date()).diff(moment(comment.created_at), 'days') <= 2">
                    {{ comment.created_at | moment("from")}}　
                  </template>
                  <template v-else>
                    {{ comment.created_at | moment('Y.M.D(dd) H:mm') }}
                  </template>
                </span>
              </div>
              <div>
                <div class="speech-bubble">
                  <span class="comment">{{ comment.comment_text }}</span>
                  <span v-if="comment.user_id == user.id"><!-- 型が違うので==使用 -->
                    <v-ons-icon icon="fa-trash" class="delete_comment_icon"
                      @click="confirmDeleteComment(comment.id)"></v-ons-icon>
                  </span>
                  <p v-for="att in comment.attachments">
                    <a :href="att.file_path">
                      <img :src="att.file_path" v-if="isImage(att.file_type)" class="image_in_post">
                      <span>{{ att.original_file_name }}</span>
                    </a>
                  </p>
                </div>
              </div>
              <!--<div class="right mr-10">-->
                <!--<v-ons-icon icon="fa-thumbs-up"-->
                            <!--:class="isLike? 'like_on' : 'like_off'"-->
                            <!--onclick="toggleLike(this)"></v-ons-icon>-->
                <!--<span class="like-count">{{ comment.like_user_ids.length }}</span>-->
              <!--</div>-->
            </div>
          </v-ons-col>
        </v-ons-row>
        <v-ons-row v-if="post.created_id === user.id">
          <v-ons-col class="space">
            <v-ons-button class="mtb-20 red" modifier="large--quiet"
                          @click="confirmDeletePost()" :disabled="deleting">
              <v-ons-icon icon="fa-spinner" spin v-if="deleting" class="gray"></v-ons-icon>
              この投稿を削除
            </v-ons-button>
          </v-ons-col>
        </v-ons-row>
      </template>
    </section>

    <!-- アンケート回答者一覧Modal -->
    <v-ons-modal>
      <div class="answer_container p-10">
        <div class="row">
          <div class="col space">
            <div class="fl-right">
              <v-ons-icon icon="fa-close" size="24px" class="gray"
                          @click="hideAnswerModal();"></v-ons-icon>
            </div>
            <div class="center">
              {{ modal.question }} - {{ modal.answer }} {{ modal.count }}件
            </div>
            <div class="scroller mt-15">
                <div v-for="user in modal.users">{{ user.name }}</div>
            </div>
          </div>
        </div>
      </div>
    </v-ons-modal>
  </v-ons-page>
</template>

<script>
  import EditPost from './EditPost.vue';
  export default {
    mounted() {
      this.load();
    },
    data() {
      return {
        post: {},
        post_responses: {},
        post_attachments: {},
        questionnaire: {},
        questionnaire_answers: [],
        answer_user_names: [],
        popover_target: null,
        popover_visible: false,
        comments: {},
        comment_text: "",
        comment_files: [],
        likes_count: 0,
        likes: [],
        user: {},
        modal: {
          question: null,
          answer: null,
          count: null,
          users: null
        },
        loading: false,
        deleting: false,
        errored: false
      }
    },
    computed: {
      isHeartOn: {
        get() {console.log('isHeartOn='+this.post_responses.like_flg);return this.post_responses.like_flg;},
        set(like_flg) {this.post_responses.like_flg = like_flg;}
      },
      heartCount: {
        get() {return this.likes_count;},
        set(likes_count) {this.likes_count = likes_count;}
      },
      isStarOn: {
        get() {return this.post_responses.star_flg;},
        set(like_flg) {this.post_responses.star_flg = star_flg;}
      },
      starCount: {
        get() {return this.post.star_count;},
        set(star_count) {this.post.star_count = star_count;}
      }
    },
    methods: {
      load() {
        // console.log('start load');
        this.loading = true;
        let post_id = this.$store.state.article.post_id;
        this.$http.get('/api/posts/' + post_id)
          .then((response)=>{
            this.post = response.data.post;
            this.post_responses = response.data.post_responses;
            this.post_attachments = response.data.post_attachments;
            this.questionnaire = response.data.questionnaire;
            this.comments = response.data.comments;
            this.likes = response.data.likes;
            this.likes_count = this.likes? this.likes.length : 0;
            this.user = response.data.user;
            this.loading = false;
          })
          .catch(error => {
            console.log(error);
            this.errored = true;
            if (error.response.status == 401) {
              window.location.href = "/login"; return;
            }
          })
          .finally(() => this.loading = false);
      },
      postComment() {
        if (!this.comment_text) {
          return;
        }
        let post_id = this.$store.state.article.post_id;
        let self = this;
        // 送信フォームデータ準備
        let formData = new FormData();
        formData.append('comment_text', this.comment_text);
        for(let i = 0; i < this.comment_files.length; i++){
          formData.append('comment_files[]', this.comment_files[i]);
        }
        this.$http.post('/api/post_comments/' + post_id, formData)
          .then((response)=>{
            // console.log(response.data);
            self.comment_text = '';
            self.comment_files = [];
            self.load();
          })
          .catch(error => {
            this.errored = true;
            if (error.response.status == 401) {
              window.location.href = "/login"; return;
            }
          })
          .finally(() => this.loading = false);
      },
      confirmDeleteComment(comment_id) {
        let self = this;
        this.$ons.notification.confirm("削除しますか？", {title: ''})
          .then(function(ok) {
            if(!ok) {return;}
            self.deleteComment(comment_id);
          });
      },
      deleteComment(comment_id) {
        // console.log("コメントID=" + comment_id);
        let post_id = this.$store.state.article.post_id;
        let self = this;
        self.$http.delete('/api/post_comments/' + post_id + '/' + comment_id)
          .then((response)=>{
            // console.log(response.data);
            self.load();
          })
          .catch(error => {
            console.log(error);
            self.errored = true;
            if (error.response.status === 401) {
              window.location.href = "/login"; return;
            }
          })
          .finally(() => self.loading = false);
      },
      openEditPost() {
        this.$store.commit('navigator/push', {
          extends: EditPost,
          onsNavigatorOptions: {animation: 'lift'}
        });
      },
      toggleHeart() {
        if(this.isHeartOn) {
          this.isHeartOn = 0; this.heartCount--;
        } else {
          this.isHeartOn = 1; this.heartCount++;
        }
        let form = new FormData();
        form.append('like_flg', this.isHeartOn);
        let post_id = this.$store.state.article.post_id;
        this.$http.post('/api/post_responses/' + post_id, form)
          .catch(error => {
            this.errored = true;
          });
      },
      toggleStar(starIcon) {
        if(this.isStarOn) {
          this.isStarOn = 0; this.starCount--;
        } else {
          this.isStarOn = 1; this.starCount++;
        }
        let form = new FormData();
        form.append('star_flg', this.isStarOn);
        let post_id = this.$store.state.article.post_id;
        this.$http.post('/api/post_responses/' + post_id, form)
          .catch(error => {
            this.errored = true;
          });
      },
      toggleLike(likeIcon) {
      },
      showAnswerModal(question, answer) {
        if (!question.answerCounts[answer]) {
          return;
        }
        this.modal.question = question.text;
        this.modal.answer = answer;
        this.modal.count = question.answerCounts[answer];
        this.modal.users = question.usersAnswers.filter(function(data) {
          return data.answer === answer;
        });
        document.querySelector('ons-modal').show();
      },
      showAnswerPopover(event) {
        this.popover_target = event;
        this.popover_visible = true;
      },
      hideAnswerModal() {
        document.querySelector('ons-modal').hide();
      },
      showQuestionnaireActionSheet(question, index) {
        let selections = ['◯', '△', '✕', 'キャンセル'];
        let self = this;
        let answer = this.$ons.openActionSheet({
          title: question,
          cancelable: true,
          buttons: selections
        })
        .then(function(answer){
          if (answer === undefined || answer < 0 || 2 < answer) {return;}
          let form = new FormData();
          form.append('post_id', self.post.id);
          form.append('questionnaire_id', self.questionnaire.id);
          form.append('question_no', index);
          form.append('answer', selections[answer]);
          self.$http.post('/api/questionnaires/answer', form)
            .then((response)=>{
              // console.log(response.data);
              self.load();
            })
            .catch(error => {
              self.errored = true;
              if (error.response.status === 401) {
                window.location.href = "/login"; return;
              }
            })
            .finally(() => self.loading = false);
        });
      },
      isImage(fileExtension) {
        if (['jpg','jpeg','png','gif','bmp'].includes(fileExtension.toLowerCase())) {
          return true;
        }
        return false;
      },
      // ファイルが選択された時
      onFileSet(event) {
        // console.log("onFileSet.");
        this.comment_files = event.target.files;
      },
      fitTextarea() {
        let num = event.srcElement.value.match(/\r\n|\n/g);
        if (num != null && 3 < num.length) {
          event.srcElement.rows = num.length > 8 ? 8 : num.length + 1;
        } else {
          event.srcElement.rows = 3;
        }
      },
      confirmDeletePost() {
        let self = this;
        this.$ons.notification.confirm("この投稿を削除しますか？", {title: ''})
          .then(function(ok) {
            if(!ok) {return;}
            self.deletePost(self.$store.state.article.post_id);
          });
      },
      deletePost() {
        this.deleting = true;
        let post_id = this.$store.state.article.post_id;
        let self = this;
        self.$http.delete('/api/posts/' + post_id)
          .then((response)=>{
            // console.log(response.data);
            this.$ons.notification.alert('削除しました', {title: ''})
              .then(function(){
                self.afterDelete();
              });
          })
          .catch(error => {
            console.log(error);
            self.errored = true;
            if (error.response.status === 401) {
              window.location.href = "/login"; return;
            }
          })
          .finally(() => self.deleting = false);
      },
      afterDelete() {
        this.deleting = false;
        this.$store.commit('navigator/pop');
        this.$store.dispatch('timeline/load', this.$http);
      }
    }
  };
</script>

<style>
  .article_container {
    padding: 15px;
    background-color: white;
  }
  .comment_textarea {
    width: 100%;
  }
  .entry_title {
    font-size: 18px;
    font-weight: bold;
    text-align:left;
    margin: 0;
  }
  .entry_content {
    font-size: 16px;
    text-align:left;
    margin: 5px 0 0 5px;
    white-space: pre-wrap;
  }
  .updated_at {
    color: grey;
    font-size: 13px;
    text-align: left;
    margin: 0 0 0 5px;
  }
  .highlight_summary {
    font-size: 12px;
    line-height: 50%;
    margin: 0 0 0 10px;
  }
  .video_thumbnail {
    margin: 6px 0 6px 0;
  }
  .questionnaire_table {
    width: 100%;
  }
  .questionnaire_table td, th {
    border-bottom: 1px solid gray;
  }
  .questionnaire_results {
    width: 100px;
  }
  .answer {
    width: 26px;
    text-align: center;
  }
  .questionnaire_btn {
    width: 60px;
  }
  .responsebar {
    text-align: center;
    margin: 20px auto 0 auto;
    width: 100%;
  }
  .heart {
    color: #ff6060;
    font-size: 18px;
    /*  margin: 0 0 0 30px;*/
  }
  .heart-count {
    color: red;
    font-size: 13px;
  }
  .heart_text {
    color: black;
    font-size: 16px;
    margin-left: 5px;
  }
  .star {
    color: orange;
    font-size: 18px;
    margin: 0 0 0 40px;
  }
  .star-count {
    color: orange;
    font-size: 13px;
  }
  .star_text {
    color: black;
    font-size: 16px;
  }
  .like_off {
    color: #cccccc;
    font-size: 24px;
    margin-top: 5px;
  }
  .like_on {
    color: #ff6060;
    font-size: 24px;
    margin-top: 5px;
  }
  .like-count {
    font-size: 13px;
    margin: 0 0 0 6px;
  }
  .comment {
    font-size: 14px;
    margin: 0;
    white-space: pre-wrap;
  }
  .comment_card {
    background-color: #81ff4f;
    margin-bottom: 0;
  }
  .comment-count {
    color: grey;
    font-size: 13px;
    margin: 0 0 0 4px;
  }
  .comment-toggle {
    color: #cccccc;
    font-size: 26px;
    font-weight: bold;
    margin: 0 0 0 20px;
  }
  .lastspace {
    margin-bottom: 80px;
  }
  .speech-bubble {
    position: relative;
    background: #81ff4f;
    border-radius: .3em;
    padding: 15px;
    margin-top: 6px;
  }

  .speech-bubble:after {
    content: '';
    position: absolute;
    top: 0;
    left: 5%;
    width: 0;
    height: 0;
    border: 6px solid transparent;
    border-bottom-color: #81ff4f;
    border-top: 0;
    margin-left: -6px;
    margin-top: -6px;
  }
  .delete_comment_icon {
    color: gray;
    float: right;
  }
  .image_in_post {
    max-width: 100%;
  }
  .messageBtn {
    width: 20px;
  }
  .answer_container {
    color: black;
    background-color: white;
    width: 70%;
    margin: 20px auto 20px auto;
  }
  .scroller {
    display: block;
    width: 80%;
    height: 460px;
    overflow: scroll;
    -webkit-overflow-scrolling: touch;
    -ms-overflow-style: none;
    -ms-scroll-snap-type: mandatory;
  }
</style>