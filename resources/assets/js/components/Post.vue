<template>
  <v-ons-page id="post" class="bg-white">
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <v-ons-icon icon="fa-comment-alt" class="white" size="20px"></v-ons-icon>
        <span>投稿</span>
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
        <form id="postForm" action="#" method="POST" onsubmit="return false;">
          <div class="space center">
            <v-ons-input modifier="border" placeholder="件名"
                         class="w-100p" v-model="title"></v-ons-input>
          </div>
          <div class="space">
            <v-ons-select v-model="selected_category" class="category_select">
              <option v-for="cate in categories" :value="cate.id" :key="cate.id">
                {{ cate.name }}
              </option>
            </v-ons-select>
          </div>
          <div class="space">
            <textarea class="textarea w-100p" rows="16" placeholder="本文"
            v-model="contents"></textarea>
          </div>
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

          <!-- アンケート -->
          <v-ons-row class="space mb-20" v-if="questionnaire_title">
            <v-ons-col>
              <p><v-ons-icon icon="fa-list-alt"></v-ons-icon>
                <span class="bold">{{ questionnaire_title }}</span>
                <a href="#" class="small fl-right" @click="deleteQuestionnaire()">アンケート削除</a>
              </p>
              <div class="mt-5">
                <table class="questionnaire_table">
                  <template v-for="(q, index) in questionnaire_selections">
                    <tr v-if="q.text" :key="index">
                      <td>{{ q.text }}</td>
                      <td class="questionnaire_results">◯　△　✕</td>
                    </tr>
                  </template>
                </table>
              </div>
            </v-ons-col>
          </v-ons-row>

          <div class="space">
            <div class="upload-btn-wrapper">
              <v-ons-button class="smallBtn" modifier="outline" :disabled="maxFiles <= files.length">
                <v-ons-icon icon="fa-file"></v-ons-icon> 添付ファイル</v-ons-button>
              <input type="file" multiple @change="onFileSet"/>
            </div>
            <v-ons-button class="smallBtn fl-right" modifier="outline"
                          @click="showQuestionnaireModal()">
              <v-ons-icon icon="fa-list-alt"></v-ons-icon>アンケート作成</v-ons-button>
          </div>
          <div class="space">
          </div>
          <!--<div class="space row middle">-->
            <!--<v-ons-switch id="notificate" v-model="notification_flg"></v-ons-switch>-->
            <!--<label for="notificate" class="middle">みんなにメール通知</label>-->
          <!--</div>-->
          <div class="space">
            <v-ons-button id="postBtn" class="mtb-20" modifier="large"
                          @click="post()" :disabled="posting">
              <v-ons-icon icon="fa-spinner" spin v-if="posting"></v-ons-icon>
              投稿
            </v-ons-button>
          </div>
        </form>
      </div>
    </div>

    <!-- アンケート作成画面Modal -->
    <v-ons-modal var="questionnaireModal">
      <form id="createQuestionnaireForm" action="#" method="POST" v-on:submit.prevent="post">
        <div class="questionnaire_container p-10">
          <div class="row">
            <div class="col space">
              <div class="right">
                <v-ons-icon icon="fa-close" size="24px" class="gray"
                            @click="hideQuestionnaireModal();"></v-ons-icon>
              </div>
              <h4 class="mt-5">アンケート作成</h4>
              <div class="mt-10">
                <v-ons-input modifier="border" placeholder="タイトル・質問" name="q_title"
                             class="w-100p" v-model="questionnaire_title_tmp"></v-ons-input>
              </div>
              <template v-for="(selection, index) in questionnaire_selections_tmp">
                <div :class="index === 0? 'mt-30' : 'mt-10'" :key="index">
                  <v-ons-input modifier="border" :placeholder="'選択肢' + (index+1)" class="w-90p"
                               v-model="selection.text"></v-ons-input>
                  <v-ons-icon icon="fa-trash" class="delete_selection_icon"
                    @click="deleteQuestionnaireSelection(index);"></v-ons-icon>
                </div>
              </template>
              <div class="mt-10 left" v-if="questionnaire_selections_tmp.length <= 7">
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
                            @click="saveQuestionnaire();">作成する</v-ons-button>
            </div>
          </div>
        </div>
      </form>
    </v-ons-modal>
  </v-ons-page>
</template>

<script>
  export default {
    beforeCreate() {
      this.$http.get('/api/posts/create')
        .then((response)=>{
          this.categories = response.data.categories;
          this.loading = false;
        })
        .catch(error => {
          console.log(error);
          this.errored = true;
          if (error.response.status === 401) {
            window.location.href = "/login";
          }
          this.loading = false;
        })
        // .finally(() => this.loading = false)
      ;
    },
    data() {
      return {
        loading: false,
        errored: false,
        posting: false,
        categories: null,
        title: "",
        selected_category: null,
        category_id: null,
        contents: "",
        notification_flg: false,
        files: [],
        fileNames: [],
        questionnaire_title_tmp: null,
        questionnaire_title: null,
        questionnaire_selections_tmp: [{text:''}, {text:''}, {text:''}],
        questionnaire_selections: [{text:''}, {text:''}, {text:''}],
        maxFiles: 20
      }
    },
    methods: {
      post() {
        //TODO validate
        if (this.posting) {
          return;
        }
        if (!this.title) {this.$ons.notification.alert('タイトルを入れてください', {title: ''});return;}
        if (!this.contents) {this.$ons.notification.alert('本文を入れてください', {title: ''});return;}
        this.posting = true;
        this.category_id = this.selected_category? this.selected_category : this.categories[0].id;
        let self = this;
        // 送信フォームデータ準備
        let formData = new FormData();
        formData.append('title', this.title);
        formData.append('contents', this.contents);
        formData.append('category_id', this.category_id);
        formData.append('notification_flg', this.notification_flg);
        if (this.questionnaire_title) {
          formData.append('questionnaire_title', this.questionnaire_title);
          formData.append('questionnaire_selections', JSON.stringify(this.questionnaire_selections));
        }
        for(let i = 0; i < this.files.length; i++) {
          formData.append('files[]', this.files[i]);
        }
        // 送信
        this.$http.post('/api/posts', formData)
          .then(response => {
            // console.log(response.data);
            this.$ons.notification.alert('投稿しました', {title: ''})
              .then(function(){
                self.afterPost();
              });
            this.loading = false; this.posting = false;
          })
          .catch(error => {
            console.log(error.response);
            if (error.response.status === 401) {window.location.href = "/login";}
            if (error.response.status === 413) {
              this.$ons.notification.alert('アップロード容量が大きすぎます。容量を減らして投稿してください。', {title: ''});
            }
            this.loading = false; this.posting = false;
          })
          // .finally(() => {this.loading = false; this.posting = false;})
        ;
      },
      afterPost() {
        this.$store.commit('navigator/pop');
        this.$store.dispatch('timeline/load', this.$http);
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
      deleteFile(index) {
        this.files.splice(index, 1);
        this.fileNames.splice(index, 1);
      },
      showQuestionnaireModal() {
        this.questionnaire_title_tmp = this.questionnaire_title;
        // ディープコピー
        this.questionnaire_selections_tmp = JSON.parse(JSON.stringify(this.questionnaire_selections));
        var modal = document.querySelector('ons-modal');
        modal.show();
      },
      hideQuestionnaireModal() {
        var modal = document.querySelector('ons-modal');
        modal.hide();
      },
      saveQuestionnaire() {
        if (!this.questionnaire_title_tmp) {
          this.$ons.notification.alert('タイトル・質問を入れてください');return;}
        if (!this.questionnaire_selections_tmp[0].text) {
          this.$ons.notification.alert('選択肢を入れてください');return;}

        this.questionnaire_title = this.questionnaire_title_tmp;
        // ディープコピー
        this.questionnaire_selections = JSON.parse(JSON.stringify(this.questionnaire_selections_tmp));
        this.hideQuestionnaireModal();
      },
      addQuestionnaireSelection() {
        this.questionnaire_selections_tmp.push({text: ''});
      },
      deleteQuestionnaireSelection(index) {
        if (this.questionnaire_selections_tmp.length === 1) {
          return;
        }
        this.questionnaire_selections_tmp.splice(index, 1);
      },
      deleteQuestionnaire() {
        this.questionnaire_title = null;
        this.questionnaire_selections = [{text: ''}, {text: ''}, {text: ''}];
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
  .category_select {
    width: 10rem;
  }
</style>