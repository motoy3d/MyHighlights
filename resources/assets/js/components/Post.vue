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
            <textarea class="textarea w-100p" rows="10" placeholder="内容"
            v-model="contents"></textarea>
          </div>
          <div class="space">
            <div class="upload-btn-wrapper">
              <v-ons-button class="smallBtn button--outline">
                <v-ons-icon icon="fa-file"></v-ons-icon> 添付ファイル</v-ons-button>
              <input type="file" name="myfile" />
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
            <v-ons-button class="mtb-20" modifier="large"
                          @click="post()">投稿</v-ons-button>
          </div>
        </form>
      </div>
    </div>

    <!-- アンケート作成画面Modal -->
    <v-ons-modal var="quetionnaireModal">
      <form id="createQuetionnaireForm" action="#" method="POST">
        <div class="quetionnaire_container p-10">
          <div class="row">
            <div class="col">
              <h4>アンケート作成</h4>
              <div class="mt-10">
                <v-ons-input modifier="border" placeholder="アンケートタイトル" name="q_title" class="w-90p"></v-ons-input>
              </div>
              <div class="mt-20">
                <v-ons-input modifier="border" placeholder="選択肢1" name="q_answer01" class="w-90p"></v-ons-input>
              </div>
              <div class="mt-10">
                <v-ons-input modifier="border" placeholder="選択肢2" name="q_answer02" class="w-90p"></v-ons-input>
              </div>
              <div class="mt-10 mb-10">
                <v-ons-input modifier="border" placeholder="選択肢3" name="q_answer03" class="w-90p"></v-ons-input>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="space">
              <v-ons-button class="plr-20"
                            @click="hideQuestionnaireModal();">作成する</v-ons-button>
            </div>
            <div class="space">
              <v-ons-button class="bg-gray"
                            @click="hideQuestionnaireModal();">閉じる</v-ons-button>
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
        categories: null,
        title: "",
        selected_category: null,
        category_id: null,
        contents: "",
        notification_flg: false
      }
    },
    methods: {
      post() {
        //TODO validate
        if (!this.title) {
          this.$ons.notification.alert('タイトルは必須です', {title: ''});
          return;
        }
        if (!this.contents) {
          this.$ons.notification.alert('内容は必須です', {title: ''});
          return;
        }
        this.category_id = this.selected_category? this.selected_category.id : null;
        let self = this;
        this.$http.post('/api/posts', this.$data)
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
          .finally(() => this.loading = false);
      },
      afterPost() {
        this.$store.commit('navigator/pop');
        this.$store.dispatch('timeline/loadTimeline', this.$http);
      },
      showQuestionnaireModal() {
        var modal = document.querySelector('ons-modal');
        modal.show();
      },
      hideQuestionnaireModal() {
        var modal = document.querySelector('ons-modal');
        modal.hide();
      }
    }
  };
</script>

<style></style>