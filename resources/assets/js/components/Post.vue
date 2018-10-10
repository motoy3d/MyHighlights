<template>
  <v-ons-page id="post">
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
    <div class="row">
      <div class="col bg-white">
        <form id="postForm" action="#" method="POST">
          <div class="space center">
            <v-ons-input modifier="border" placeholder="件名"
                         class="w-100p" v-model="title"></v-ons-input>
          </div>
          <div class="space">
            <v-ons-select v-model="selected_category">
              <option v-for="cate in categories" :value="cate.id">
                {{ cate.name }}
              </option>
            </v-ons-select>
          </div>
          <div class="space">
            <textarea class="textarea w-100p" rows="10" placeholder="本文"
            v-model="contents"></textarea>
          </div>
          <div class="mb-10" v-if="0 < fileNames.length">
            <ul>
              <li v-for="(file, index) in fileNames" class="mtb-10">
                {{ file }}
              </li>
            </ul>
          </div>
          <div class="space">
            <div class="upload-btn-wrapper">
              <v-ons-button class="smallBtn button--outline">
                <v-ons-icon icon="fa-file"></v-ons-icon> 添付ファイル</v-ons-button>
              <input type="file" multiple @change="onFileSet"/>
            </div>
            <v-ons-button class="smallBtn button--outline" style="float:right"
                          @click="showQuestionnaireModal()">
              <v-ons-icon icon="fa-list-alt"></v-ons-icon>アンケート作成</v-ons-button>
          </div>
          <div class="space">
          </div>
          <div class="space row middle">
            <v-ons-switch id="notificate" v-model="notification_flg"></v-ons-switch>
            <label for="notificate" class="middle">みんなにメール通知</label>
          </div>
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
    <v-ons-modal var="quetionnaireModal">
      <form id="createQuetionnaireForm" action="#" method="POST">
        <div class="quetionnaire_container p-10">
          <div class="row">
            <div class="col space">
              <div class="right">
                <v-ons-icon icon="fa-close" size="24px" class="gray"
                            @click="hideQuestionnaireModal();"></v-ons-icon>
              </div>
              <h4 class="mt-5">アンケート作成</h4>
              <div class="mt-10">
                <v-ons-input modifier="border" placeholder="タイトル・質問" name="q_title"
                             class="w-100p" v-model="quetionnaire_title_tmp"></v-ons-input>
              </div>
              <v-ons-page>
                <template v-for="(selection, index) in quetionnaire_selections_tmp">
                  <div :class="index === 0? 'mt-30' : 'mt-10'">
                    <v-ons-input modifier="border" :placeholder="'選択肢' + (index+1)" class="w-90p"
                                 v-model="selection.text"></v-ons-input>
                    <v-ons-icon icon="fa-trash-o" class="delete_selection_icon"></v-ons-icon>
                  </div>
                </template>
              </v-ons-page>
              <div class="mt-10 left">
                <v-ons-button class="small button--quiet" ripple
                  @click="addQuetionnaireSelection()">
                  <v-ons-icon icon="fa-plus" class="mr-5"></v-ons-icon>
                  選択肢追加
                </v-ons-button>
              </div>
            </div>
          </div>
          <div class="row mt-10">
            <div class="space">
              <v-ons-button class="plr-30"
                            @click="saveQuetionnaire();">作成する</v-ons-button>
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
          this.categories = response.data.categories
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
    data() {
      return {
        loading: false,
        posting: false,
        categories: null,
        title: "",
        selected_category: null,
        category_id: null,
        contents: "",
        notification_flg: false,
        files: [],
        fileNames: [],
        quetionnaire_title_tmp: null,
        quetionnaire_title: null,
        quetionnaire_selections_tmp: [{text:''}, {text:''}, {text:''}],
        quetionnaire_selections: [{text:''}, {text:''}, {text:''}]
      }
    },
    computed: {
      postBtnColor: {
        get() {return this.posting? "white" : "";}
      }
    },
    methods: {
      post() {
        //TODO validate
        if (this.posting) {
          return;
        }
        if (!this.title) {
          this.$ons.notification.alert('タイトルを入れてください', {title: ''});
          return;
        }
        if (!this.contents) {
          this.$ons.notification.alert('本文を入れてください', {title: ''});
          return;
        }
        this.posting = true;
        this.category_id = this.selected_category? this.selected_category : this.categories[0].id;
        let self = this;
        // 送信フォームデータ準備
        let formData = new FormData();
        formData.append('title', this.title);
        formData.append('contents', this.contents);
        formData.append('category_id', this.category_id);
        formData.append('notification_flg', this.notification_flg);
        for(let i = 0; i < this.files.length; i++){
          formData.append('files[]', this.files[i]);
        }
        // 送信
        this.$http.post('/api/posts', formData)
          .then(response => {
            console.log(response.data);
            this.$ons.notification.alert('投稿しました', {title: ''})
              .then(function(){
                self.afterPost();
              });
          })
          .catch(error => {
            console.log(error.response);
            if (error.response.status == 401) {
              window.location.href = "/login"; return;
            }
          })
          .finally(() => {this.loading = false; this.posting = false;});
      },
      afterPost() {
        this.$store.commit('navigator/pop');
        this.$store.dispatch('timeline/load', this.$http);
      },
      // ファイルが選択された時
      onFileSet(event) {
        console.log("onFileSet.");
        this.files = event.target.files;
        this.fileNames = [];
        for (let i=0; i<this.files.length; i++) {
          this.fileNames.push(this.files[i].name);
        }
        console.log(this.files);
      },
      showQuestionnaireModal() {
        this.quetionnaire_title_tmp = this.quetionnaire_title;
        this.quetionnaire_selections_tmp = JSON.parse(JSON.stringify(this.quetionnaire_selections));
        var modal = document.querySelector('ons-modal');
        modal.show();
      },
      hideQuestionnaireModal() {
        var modal = document.querySelector('ons-modal');
        modal.hide();
      },
      saveQuetionnaire() {
        this.quetionnaire_title = this.quetionnaire_title_tmp;
        this.quetionnaire_selections = JSON.parse(JSON.stringify(this.quetionnaire_selections_tmp));
        this.hideQuestionnaireModal();
      },
      addQuetionnaireSelection() {
        this.quetionnaire_selections_tmp.push({text: ''});
      }
    }
  };
</script>

<style>
  .post_progress {
    margin-right: 10px;
    width: 15px;
    color: white;
  }
  .delete_selection_icon {
    color: gray;
    float: right;
    margin: 10px;
  }
</style>