<template>
  <v-ons-page id="post">
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <v-ons-icon icon="md-comment" class="white" size="20px"></v-ons-icon>
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
            <v-ons-input modifier="border" placeholder="件名" name="title" class="w-100p"></v-ons-input>
          </div>
          <div class="space">
            <select id="category" name="category" class="select-input select-input--underbar">
              <option>練習</option>
              <option>練習試合</option>
              <option>公式試合</option>
              <option>イベント</option>
              <option>その他</option>
            </select>
            <!--
            <v-ons-radio name="category" modifier="material" input-id="radio-1" checked></v-ons-radio>
            <label for="radio-1" class="center">練習</label>
            <v-ons-radio name="category" modifier="material" input-id="radio-2" class="ml-10"></v-ons-radio>
            <label for="radio-2" class="center">試合</label>
            <v-ons-radio name="category" modifier="material" input-id="radio-3" class="ml-10"></v-ons-radio>
            <label for="radio-3" class="center">イベント</label>
            <v-ons-radio name="category" modifier="material" input-id="radio-4" class="ml-10"></v-ons-radio>
            <label for="radio-4" class="center">その他</label>-->
          </div>
          <div class="space">
            <textarea class="textarea w-100p" rows="10" placeholder="内容"></textarea>
          </div>
          <div class="space">
            <div class="upload-btn-wrapper">
              <v-ons-button class="smallBtn button--outline">添付ファイル</v-ons-button>
              <input type="file" name="myfile" />
            </div>
          </div>
          <div class="space">
            <v-ons-button class="smallBtn button--outline" onclick="showQuestionnaireModal()">
              アンケート作成</v-ons-button>
          </div>
          <div class="space row middle">
            <v-ons-switch id="notificate"></v-ons-switch>
            <label for="notificate" class="middle">みんなにメール通知</label>
          </div>
          <div class="space">
            <v-ons-button class="mtb-20" modifier="large"
                          @click="$store.commit('navigator/pop');">投稿</v-ons-button>
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
              <v-ons-button class="plr-20" onclick="hideQuestionnaireModal();">作成する</v-ons-button>
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
  export default {
    data() {
      return {
        
      };
    },
    methods: {
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