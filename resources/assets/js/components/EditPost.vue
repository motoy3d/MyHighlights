<template>
  <v-ons-page id="post" class="bg-white">
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <v-ons-icon icon="fa-comment-alt" class="white" size="20px"></v-ons-icon>
        <span>投稿編集</span>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-close" class="white" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
        </div>
    </v-ons-toolbar>
    <div class="page__background" style="background-color: white;"></div>
    <div class="row">
      <div class="col bg-white">
        <div v-if="loading" class="progress-div">
          <v-ons-progress-circular indeterminate class="progress-circular"></v-ons-progress-circular>
        </div>
        <template v-else>
          <form id="postForm" action="#" method="POST">
            <div class="space center">
              <v-ons-input modifier="border" placeholder="件名"
                           class="w-100p" v-model="title"></v-ons-input>
            </div>
            <div class="space">
              <v-ons-select v-model="selected_category">
                <option v-for="cate in categories" :value="cate.id" :key="cate.id">
                  {{ cate.name }}
                </option>
              </v-ons-select>
            </div>
            <div class="space">
              <textarea class="textarea w-100p" rows="16" placeholder="本文"
              v-model="contents"></textarea>
            </div>
            <!--既存ファイル一覧-->
            <div class="space mb-20" v-if="0 < post_attachments.length">
              <template v-for="att in post_attachments">
                <div v-if="isImage(att.file_type)" class="mt-30" :key="att.id">
                  <img :src="att.file_path" class="image_in_post">
                  <div>
                    <v-ons-icon icon="fa-trash" class="fl-right gray mt-5 ml-15 mr-10"
                                @click="deleteFileDb(att.id);"></v-ons-icon>
                    <a :href="att.file_path" :download="att.original_file_name" class="break" target="_blank">
                      <v-ons-icon icon="fa-download" class="fl-right lightgray"
                                  size="22px"></v-ons-icon>
                    </a>
                  </div>
                </div>
                <div v-else class="mt-30" :key="att.id">
                  <span class="break">{{ att.original_file_name }}</span>
                  <v-ons-icon icon="fa-trash" class="fl-right gray mt-5 ml-15 mr-10"
                              @click="deleteFileDb(att.id);"></v-ons-icon>
                  <a :href="att.file_path">
                    <v-ons-icon icon="fa-download" class="fl-right lightgray"
                                size="22px"></v-ons-icon>
                  </a>
                </div>
              </template>
            </div>

            <div class="space">
              <!--今回アップファイル一覧-->
              <div class="mb-10" v-if="0 < fileNames.length">
                <ul>
                  <li v-for="(file, index) in fileNames" class="mtb-10 break" :key="index">
                    <span>{{ file }}
                      <v-ons-icon icon="fa-trash" class="gray ml-5"
                                  @click="deleteFile(index);"></v-ons-icon>
                    </span>
                  </li>
                </ul>
              </div>
              <div class="upload-btn-wrapper">
                <v-ons-button class="smallBtn" modifier="outline">
                  <v-ons-icon icon="fa-file"></v-ons-icon> 添付ファイル</v-ons-button>
                <input type="file" multiple @change="onFileSet"/>
              </div>
            </div>

            <!-- アンケート -->
            <v-ons-row class="space" v-if="questionnaire">
              <v-ons-col>
                <p class="bold mb-0"><v-ons-icon icon="fa-list-alt" class="black"></v-ons-icon>
                  {{ questionnaire.title }}</p>
                <div>
                  <table class="questionnaire_table">
                    <tr><th></th><th>◯</th><th>△</th><th>✕</th></tr>
                    <tr v-for="(q, index) in questionnaire.items" :key="index">
                      <td>{{ q.text }}</td>
                      <td class="answer">
                        <span v-if="q.answerCounts && q.answerCounts['◯']"> {{ q.answerCounts['◯'] }} </span>
                        <span v-else>0</span>
                      </td>
                      <td class="answer">
                        <span v-if="q.answerCounts && q.answerCounts['△']"> {{ q.answerCounts['△'] }} </span>
                        <span v-else>0</span>
                      </td>
                      <td class="answer">
                        <span v-if="q.answerCounts && q.answerCounts['✕']"> {{ q.answerCounts['✕'] }} </span>
                        <span v-else>0</span>
                      </td>
                    </tr>
                  </table>
                </div>
                <a href="#" class="mt-5 fl-right" @click="showQuestionnaireModal()">アンケート選択肢追加</a>
              </v-ons-col>
            </v-ons-row>

            <div class="space">
            </div>
            <!--<div class="space row middle">-->
              <!--<v-ons-switch id="notificate" v-model="notification_flg"></v-ons-switch>-->
              <!--<label for="notificate" class="middle">みんなにメール通知</label>-->
            <!--</div>-->
            <div class="space">
              <v-ons-button id="postBtn" class="mtb-20" modifier="large"
                            @click="update()" :disabled="posting">
                <v-ons-icon icon="fa-spinner" spin v-if="posting"></v-ons-icon>
                保存
              </v-ons-button>
            </div>
          </form>
        </template>
      </div>
    </div>

    <!-- アンケート項目追加画面Modal -->
    <v-ons-modal id="questionnaireModal" v-if="questionnaire">
      <form id="createQuestionnaireForm" action="#" method="POST" v-on:submit.prevent="post">
        <div class="questionnaire_container p-10">
          <div class="row">
            <div class="col space">
              <div class="right">
                <v-ons-icon icon="fa-close" size="24px" class="gray"
                            @click="hideQuestionnaireModal();"></v-ons-icon>
              </div>
              <h4 class="mt-5">アンケート選択肢追加</h4>
              <div>
                <table class="questionnaire_table">
                  <tr v-for="(q, index) in questionnaire.items" :key="index">
                    <td class="left">{{ q.text }}</td>
                  </tr>
                </table>
              </div>
              <template v-for="(selection, index) in added_questionnaire_selections_tmp">
                <div :class="index === 0? 'mt-30' : 'mt-10'" :key="index">
                  <v-ons-input modifier="border" :placeholder="'選択肢'" class="w-90p"
                               v-model="selection.text"></v-ons-input>
                  <v-ons-icon icon="fa-trash" class="delete_selection_icon"
                              @click="deleteQuestionnaireSelection(index);"></v-ons-icon>
                </div>
              </template>
              <div class="mt-10 left" v-if="added_questionnaire_selections_tmp.length <= 7">
                <v-ons-button class="small"  modifier="quiet" ripple
                              @click="addQuestionnaireSelection()">
                  <v-ons-icon icon="fa-plus" class="mr-5"></v-ons-icon>
                  選択肢追加
                </v-ons-button>
              </div>
            </div>
          </div>
          <div class="row mt-10">
            <div class="space">
              <v-ons-button class="plr-30"
                            @click="saveQuestionnaire();">OK</v-ons-button>
            </div>
          </div>
        </div>
      </form>
    </v-ons-modal>
  </v-ons-page>
</template>

<script>
  export default {
    mounted() {
      this.load();
    },
    data() {
      return {
        loading: true,
        errored: false,
        posting: false,
        categories: null,
        title: "",
        selected_category: null,
        selected_category_id: null,
        contents: "",
        notification_flg: false,
        files: [],
        fileNames: [],
        post_attachments: [],
        questionnaire: null,
        user: {},
        questionnaire_title: null,
        added_questionnaire_selections_tmp: [{text:''}],
        added_questionnaire_selections: null,
        maxFiles: 20
      }
    },
    props: ['reloadArticle'],
    computed: {
      postBtnColor: {
        get() {return this.posting? "white" : "";}
      }
    },
    methods: {
      load() {
        this.loading = true;
        // console.log('start load');
        let post_id = this.$store.state.article.post_id;
        this.$http.get('/api/posts/' + post_id)
          .then((response)=>{
            let post = response.data.post;
            // console.log(post);
            this.title = post.title;
            this.categories = response.data.categories;
            for (let i=0; i<this.categories.length; i++) {
              let cat = this.categories[i];
              // console.log(cat.id + ":" + post.category_id);
              if (cat.id === post.category_id) {
                this.selected_category = cat.id;
              }
            }
            this.contents = post.content;
            this.notification_flg = post.notification_flg === 1;
            this.post_attachments = response.data.post_attachments;
            this.questionnaire = response.data.questionnaire;
            this.user = response.data.user;
            this.loading = false;
          })
          .catch(error => {
            console.log(error);
            this.errored = true;
            if (error.response.status === 401) {window.location.href = "/login";}
            this.loading = false;
          })
        // .finally(() => this.loading = false);
        ;
      },
      update() {
        //TODO validate
        if (this.posting) {
          return;
        }
        if (!this.title) {this.$ons.notification.alert('タイトルを入れてください', {title: ''});return;}
        if (!this.contents) {this.$ons.notification.alert('本文を入れてください', {title: ''});return;}
        this.posting = true;
        this.selected_category_id = this.selected_category? this.selected_category : this.categories[0].id;
        let self = this;
        // 送信フォームデータ準備
        let formData = new FormData();
        formData.append('title', this.title);
        // console.log('title>>>>>>>>>' + this.title);
        formData.append('contents', this.contents);
        formData.append('category_id', this.selected_category_id);
        formData.append('notification_flg', this.notification_flg? 1 : 0);
        if (this.questionnaire) {
          formData.append('questionnaire_title', this.questionnaire_title);
          formData.append('added_questionnaire_selections', JSON.stringify(this.added_questionnaire_selections));
        }
        for(let i = 0; i < this.files.length; i++) {
          formData.append('files[]', this.files[i]);
        }
        // PUTメソッドではFormDataが通常送れない仕様のためワークアラウンド　https://qiita.com/komatzz/items/21b58c92e14d2868ac8e
        let config = {headers: {'content-type': 'multipart/form-data'}};
        config.headers['X-HTTP-Method-Override'] = 'PUT'; // PUT で上書く

        // 送信
        let post_id = this.$store.state.article.post_id;
        this.$http.post('/api/posts/' + post_id, formData, config)
          .then(response => {
            // console.log(response.data);
            this.$ons.notification.alert('保存しました', {title: ''})
              .then(function(){
                self.afterPost();
              });
            this.loading = false; this.posting = false;
          })
          .catch(error => {
            console.log(error.response);
            if (error.response.status === 401) {window.location.href = "/login";}
            this.loading = false; this.posting = false;
          })
          // .finally(() => {this.loading = false; this.posting = false;})
        ;
      },
      afterPost() {
        this.added_questionnaire_selections = null;
        this.$store.commit('navigator/pop');
        this.$store.dispatch('timeline/load', this.$http);
        this.reloadArticle();
      },
      // ファイルが選択された時
      onFileSet(event) {
        const upFiles = event.target.files;
        for(let i=0; i<upFiles.length; i++) {
          // console.log('fileset ' + i);
          if(this.maxFiles <= this.files.length) {
            this.$ons.notification.alert(this.maxFiles + 'ファイルまで添付可能です。', {title: ''});
            return false;
          }
          // console.log('count ok ');
          const maxMB = 10;
          if ((1024*1024*maxMB) < upFiles[i].size) {
            this.$ons.notification.alert('1ファイル最大' + maxMB + 'MBまで添付可能です。', {title: ''});
            return false;
          }
          // console.log('size ok ');
          this.files.push(upFiles[i]);
          this.fileNames.push(upFiles[i].name);
        }
      },
      // 今回追加したがアップロード前のファイルを削除
      deleteFile(index) {
        this.files.splice(index, 1);
        this.fileNames.splice(index, 1);
      },
      // すでにアップ済み&DB登録済みのファイルを削除
      deleteFileDb(post_attachment_id) {
        let self = this;
        this.$ons.notification.confirm("このファイルを削除しますか？", {title: '', buttonLabels:['キャンセル', 'OK']})
          .then(function(ok) {
            if(!ok) {return;}
            self.$http.delete('/api/post_attachments/' + post_attachment_id)
              .then((response)=>{
                console.log(response.data);
                self.load();
                self.loading = false;
                self.reloadArticle(); //Article画面リロード
              })
              .catch(error => {
                console.log(error);
                self.errored = true;
                if (error.response.status === 401) {
                  window.location.href = "/login"; return;
                }
                self.loading = false;
              })
          });
      },
      isImage(fileExtension) {
        if (['jpg','jpeg','png','gif','bmp'].includes(fileExtension.toLowerCase())) {
          return true;
        }
        return false;
      },
      showQuestionnaireModal() {
        $('#questionnaireModal').show();
      },
      hideQuestionnaireModal() {
        $('#questionnaireModal').hide();
      },
      saveQuestionnaire() {
        // モーダルを閉じる。DB保存は投稿編集画面で「保存」を押した時に実行される。
        // ディープコピー
        this.added_questionnaire_selections = JSON.parse(JSON.stringify(this.added_questionnaire_selections_tmp));
        this.questionnaire.items = this.questionnaire.items.concat(this.added_questionnaire_selections);
        console.log(this.questionnaire.items);
        this.hideQuestionnaireModal();
      },
      addQuestionnaireSelection() {
        this.added_questionnaire_selections_tmp.push({text: ''});
      },
      deleteQuestionnaireSelection(index) {
        this.added_questionnaire_selections_tmp.splice(index, 1);
      },
      deleteQuestionnaire() {
      }
    }
  };
</script>

<style>
  .delete_selection_icon {
    color: gray;
    float: right;
    margin: 10px;
  }
  .questionnaire_table {
    width: 100%;
  }
  .questionnaire_table td {
    border-bottom: 1px solid gray;
  }
  .questionnaire_results {
    width: 100px;
  }
  .questionnaire_container {
    color: black;
    background-color: white;
    width: 95%;
    margin: 20px auto 20px auto;
  }
</style>