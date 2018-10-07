<template>
  <!-- ■■■■■■■■■■■■■■ 投稿詳細 ■■■■■■■■■■■■■■ -->
  <v-ons-page class="bg-white">
    <!-- ツールバー -->
    <v-ons-toolbar class="navbar">
      <div class="left ml-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-chevron-left" class="white" size="24px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
      <!--<div class="right mr-5">-->
        <!--<v-ons-toolbar-button>-->
          <!--<v-ons-icon icon="fa-pencil" class="white" size="24px"></v-ons-icon>-->
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
                {{ post.updated_at | moment('YYYY.M.D H:mm') }}
                　{{ post.updated_name }}</p>
            </div>
            <div class="entry_content"><span>{{ post.content }}</span>
              <template v-for="att in post_attachments">
                <p v-if="isImage(att.file_type)">
                  <a :href="att.file_path" target="_blank"><img :src="att.file_path" class="image_in_post"></a>
                </p>
                <p v-else><a :href="att.file_path">{{ att.original_file_name }}</a></p>
              </template>
            </div>
          </v-ons-col>
        </v-ons-row>
        <!-- アンケート -->
        <v-ons-row class="space" v-if="quetionnaire">
          <v-ons-col>
            <p class="bold"><v-ons-icon icon="fa-bookmark" class="black"></v-ons-icon>
              6/9(土)ルーキーリーグ出欠確認</p>
            <!-- ActionSheetで入力するので不要
                  <div class="mt-5">
                    <v-ons-button class="smallBtn button--outline" onclick="showQuestionnaireModal();">
                      アンケートに回答する</v-ons-button>
                  </div>-->
            <div class="mt-5">
              <table class="quetionnaire_table">
                <tr>
                  <td>回答候補１</td>
                  <td class="quetionnaire_results">○10 △0 ✕1</td>
                  <td class="quetionnaire_btn">
                    <v-ons-button class="smallBtn button--quiet" onclick="showQuestionnaireActionSheet();">
                      回答
                    </v-ons-button>
                  </td>
                </tr>
                <tr>
                  <td>回答候補２</td>
                  <td>○10 △0 ✕1</td>
                  <td class="quetionnaire_btn">
                    <v-ons-button class="smallBtn button--quiet" onclick="showQuestionnaireActionSheet();">
                      回答
                    </v-ons-button>
                  </td>
                </tr>
              </table>
            </div>
          </v-ons-col>
        </v-ons-row>
        <v-ons-row class="space">
          <v-ons-col class="bordertop">
            <div class="center mt-20">
              <v-ons-icon :icon="isHeartOn? 'fa-heart' : 'fa-heart-o'" class="heart"
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
            <!-- コメント -->
            <v-ons-row class="mt-30">
              <v-ons-col>
                <textarea class="textarea comment_textarea"
                          rows="4" placeholder="コメント" v-model="comment_text"></textarea>
              </v-ons-col>
              <v-ons-col width="50px" vertical-align="bottom">
                <v-ons-button class="ml-10 mt-10 right" ripple
                  @click="postComment()">
                  <v-ons-icon icon="fa-paper-plane"></v-ons-icon></v-ons-button>
              </v-ons-col>
            </v-ons-row>
            <!--<div class="upload-btn-wrapper">-->
              <!--<v-ons-button class="smallBtn button&#45;&#45;outline">添付ファイル</v-ons-button>-->
              <!--<input type="file" name="myfile" />-->
            <!--</div>-->
          </v-ons-col>
        </v-ons-row>
        <v-ons-row class="space lastspace" v-if="comments">
          <v-ons-col>
            <div class="mt-10 ml-15" v-for="(comment, index) in comments" :key="comment.id">
              <!--<hr class="mt-15">-->
              <div>
                <span class="bold">
                  {{ comment.name }}
                </span>
                <span class="updated_at">
                  {{ comment.created_at | moment("from")}}　
                </span>
              </div>
              <div>
                <div class="speech-bubble">
                  <span class="comment">{{ comment.comment_text }}</span>
                  <span v-if="comment.user_id === user.id">
                    <v-ons-icon icon="fa-trash-o" class="delete_comment_icon"
                      @click="confirmDeleteComment(comment.id)"></v-ons-icon>
                  </span>
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
      </template>
    </section>

    <!-- アンケート回答画面Modal ※ActionSheetで回答入力するので不要 -->
    <v-ons-modal var="quetionnaireAnswerModal">
      <form id="quetionnaireAnswerForm" action="#" method="POST">
        <div class="quetionnaire_container p-10">
          <div class="row">
            <div class="col">
              <h4>6/9(土)ルーキーリーグ出欠確認</h4>
              <div class="mt-20">
                <p>回答候補１</p>
                <v-ons-radio name="selection" input-id="selection-0"></v-ons-radio>
                <label for="selection-0" class="center">◯</label>
                <v-ons-radio name="selection" input-id="selection-1"></v-ons-radio>
                <label for="selection-1" class="center">△</label>
                <v-ons-radio name="selection" input-id="selection-2"></v-ons-radio>
                <label for="selection-2" class="center">✕</label>
                <p>回答候補２</p>
                <v-ons-radio name="selection" input-id="selection-0"></v-ons-radio>
                <label for="selection-0" class="center">◯</label>
                <v-ons-radio name="selection" input-id="selection-1"></v-ons-radio>
                <label for="selection-1" class="center">△</label>

                <table class="quetionnaire_table">
                  <tr>
                    <td>回答候補１</td>
                    <td>
                      <v-ons-select>
                        <option></option>
                        <option>◯</option>
                        <option>△</option>
                        <option>✕</option>
                      </v-ons-select>
                    </td>
                  </tr>
                  <tr>
                    <td>回答候補２</td>
                    <td>
                      <v-ons-select>
                        <option></option>
                        <option>◯</option>
                        <option>△</option>
                        <option>✕</option>
                      </v-ons-select>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="space">
              <v-ons-button class="plr-30" onclick="hideQuestionnaireModal();">OK</v-ons-button>
            </div>
            <div class="space">
              <v-ons-button class="bg-gray" onclick="hideQuestionnaireModal();">閉じる</v-ons-button>
            </div>
          </div>
        </div>
      </form>
    </v-ons-modal>
  </v-ons-page>
</template>

<script>
  import Post from './Post.vue';
  export default {
    mounted() {
      this.load();
    },
    data() {
      return {
        post: {},
        post_responses: {},
        post_attachments: {},
        quetionnaire: {},
        comments: {},
        comment_text: "",
        likes_count: 0,
        likes: [],
        user: {},
        loading: false,
        errored: false
      }
    },
    computed: {
      isHeartOn: {
        get() {return this.post_responses.like_flg;},
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
      },
    },
    methods: {
      load() {
        console.log('start load');
        this.loading = true;
        let post_id = this.$store.state.article.post_id;
        this.$http.get('/api/posts/' + post_id)
          .then((response)=>{
            this.post = response.data.post;
            this.post_responses = response.data.post_responses;
            this.post_attachments = response.data.post_attachments;
            this.quetionnaire = response.data.quetionnaire;
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
        this.$http.post('/api/post_comments/' + post_id, this.$data)
          .then((response)=>{
            console.log(response.data);
            self.comment_text = '';
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
        console.log("コメントID=" + comment_id);
        let post_id = this.$store.state.article.post_id;
        let self = this;
        self.$http.delete('/api/post_comments/' + post_id + '/' + comment_id)
          .then((response)=>{
            console.log(response.data);
            self.load();
          })
          .catch(error => {
            console.log(error);
            errored = true;
            if (error.response.status === 401) {
              window.location.href = "/login"; return;
            }
          })
          .finally(() => self.loading = false);
      },
      openPost() {
        this.$store.commit('post/setPostId', post_id);
        this.$store.commit('navigator/push', {
          extends: Post,
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
      showQuestionnaireModal() {
        var modal = document.querySelector('ons-modal');
        modal.show();
      },
      hideQuestionnaireModal() {
        var modal = document.querySelector('ons-modal');
        modal.hide();
      },
      showQuestionnaireActionSheet() {
        ons.openActionSheet({
          title: '回答',
          cancelable: true,
          buttons: ['◯', '△', '✕', 'キャンセル']
        });
      },
      isImage(fileExtension) {
        if (fileExtension.toLowerCase() === 'jpg' ||
          fileExtension.toLowerCase() === 'jpeg' ||
          fileExtension.toLowerCase() === 'png' ||
          fileExtension.toLowerCase() === 'gif' ||
          fileExtension.toLowerCase() === 'bmp'
        ) {
          return true;
        }
        return false;
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
  .quetionnaire_table {
    width: 100%;
  }
  .quetionnaire_table td {
    border-bottom: 1px solid gray;
  }
  .quetionnaire_results {
    width: 100px;
  }
  .quetionnaire_btn {
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
</style>