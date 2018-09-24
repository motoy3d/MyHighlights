<template>
  <v-ons-page id="post">
    <v-ons-toolbar class="navbar">
      <div class="left ml-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-chevron-left" class="white" size="24px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
      <div class="center navbartitle">
        <v-ons-icon icon="fa-user-edit" size="20px"></v-ons-icon>
        <span>メンバー情報</span>
      </div>
    </v-ons-toolbar>
    <div class="bg-white">
      <form id="postForm" action="#" method="POST">
        <div class="segment space" style="width: 91%; margin: 0 auto;">
          <button class="segment__item">
            <input type="radio" class="segment__input" name="segment-a" checked>
            <div class="segment__button">選手</div>
          </button>
          <button class="segment__item">
            <input type="radio" class="segment__input" name="segment-a">
            <div class="segment__button">監督/コーチ</div>
          </button>
          <button class="segment__item">
            <input type="radio" class="segment__input" name="segment-a">
            <div class="segment__button">家族</div>
          </button>
        </div>
        <div class="ml-15 mt-10"><small class="gray">名前(必須)</small></div>
        <div class="mlr-15 center">
          <v-ons-input modifier="border" placeholder="" id="name" name="name" class="w-100p"></v-ons-input>
        </div>
        <div class="ml-15 mt-10"><small class="gray">誕生日</small></div>
        <div class="ml-15">
          <v-ons-input modifier="border" name="title" type="date" class="w-100p"></v-ons-input>
        </div>
        <div class="ml-15 mt-10"><small class="gray">背番号</small></div>
        <div class="ml-15">
          <v-ons-input modifier="border" name="backno" class="backno_input" type="number"></v-ons-input>
        </div>
        <div class="space mt-10">
          <div class="upload-btn-wrapper">
            <v-ons-button>プロフィール画像</v-ons-button>
            <input type="file" name="myfile" />
          </div>
        </div>
        <div class="space">
          このメンバーを招待する <v-ons-switch checked></v-ons-switch>
        </div>
        <div class="ml-15"><small class="gray">メールアドレス</small></div>
        <div class="mlr-15 center">
          <v-ons-input modifier="border" placeholder="" name="title" class="w-100p"></v-ons-input>
        </div>
        <div class="space">
          <v-ons-button class="mtb-20" modifier="large">保存</v-ons-button>
        </div>
      </form>
    </div>
  </v-ons-page>
</template>

<script>
  import AddMember from './AddMember.vue';
  export default {
    methods: {
      save() {
      }
    }
  };
</script>

<style></style>