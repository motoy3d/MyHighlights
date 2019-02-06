<template>
  <v-ons-page id="post">
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <v-ons-icon icon="fa-user-plus" size="20px"></v-ons-icon>
        <span>メンバー登録</span>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-close" class="white" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
    </v-ons-toolbar>
    <div class="bg-white">
      <small class="gray space">選手と保護者をそれぞれ登録してください。</small>
      <form id="postForm" action="#" method="POST" v-on:submit.prevent>
        <div class="segment space center" style="width: 100%; margin: 0 auto;">
          <v-ons-segment :index.sync="memberTypeSegment" style="width: 91%" @postchange="changeMemberType()">
            <button>選手</button>
            <button>監督/コーチ</button>
            <button>家族/友人</button>
          </v-ons-segment>
        </div>
        <div class="ml-15 mt-10">
          <small class="gray">名前</small>
          <span class="notification ml-5 bg-gray"><small>必須</small></span>
        </div>
        <div class="mlr-15 center">
          <v-ons-input modifier="border" placeholder="" class="w-100p"
                       v-model="name"></v-ons-input>
        </div>
        <div class="ml-15 mt-10"><small class="gray">名前(かな)</small></div>
        <div class="mlr-15 center">
          <v-ons-input modifier="border" placeholder="" class="w-100p"
                       v-model="nameKana"></v-ons-input>
        </div>
        <div class="ml-15 mt-10"><small class="gray">誕生日</small></div>
        <div class="ml-15">
          <v-ons-input modifier="border" type="date" class="w-100p"
                       v-model="birthday"></v-ons-input>
        </div>
        <template v-if="memberTypeSegment === 0">
          <div class="ml-15 mt-10"><small class="gray">背番号</small></div>
          <div class="ml-15">
            <v-ons-input modifier="border" class="backno_input" type="number"
                         v-model="backno"></v-ons-input>
          </div>
        </template>
        <div class="space mt-15 center">
            <img src="/storage/prof/preset_boy.png" id="preset_boy.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_boy.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_girl.png" id="preset_girl.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_girl.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_man.png" id="preset_man.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_man.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_woman.png" id="preset_woman.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_woman.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_kuma.png" id="preset_kuma.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_kuma.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_lion.png" id="preset_lion.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_lion.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_zou.png" id="preset_zou.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_zou.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_kaeru.png" id="preset_kaeru.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_kaeru.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_penguin.png" id="preset_penguin.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_penguin.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_kurage.png" id="preset_kurage.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_kurage.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_kinoko.png" id="preset_kinoko.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_kinoko.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_egg.png" id="preset_egg.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_egg.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_tofu.png" id="preset_tofu.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_tofu.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_obake.png" id="preset_obake.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_obake.png'? 'prof_imgelected' : '')"
            <img src="/storage/prof/preset_zombi.png" id="preset_zombi.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_zombi.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_greenmonster.png" id="preset_greenmonster.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_greenmonster.png'? 'prof_imgelected' : '')">
            <img src="/storage/prof/preset_hero.png" id="preset_hero.png" @click="changeAvatar($event)"
                 :class="'prof_img ' + (selectedAvatarFilenameComputed === 'preset_hero.png'? 'prof_imgelected' : '')">
        </div>
        <template v-if="memberTypeSegment !== 0">
          <div class="space">
            このメンバーを招待する <v-ons-switch v-model="invitationFlg"></v-ons-switch>
          </div>
          <div class="ml-15"><small class="gray">メールアドレス</small></div>
          <div class="mlr-15 center">
            <v-ons-input modifier="border" placeholder="" class="w-100p"
                         v-model="email" :disabled="!invitationFlg"></v-ons-input>
          </div>
          <div class="space mt-10">
            管理者 <v-ons-switch v-model="adminFlg"></v-ons-switch>
          </div>
        </template>
        <div class="space">
          <v-ons-button class="mtb-20" modifier="large" @click="register();">登録</v-ons-button>
        </div>
      </form>
    </div>
  </v-ons-page>
</template>

<script>
  export default {
    data() {
      return {
        loading: true,
        name: "",
        nameKana: "",
        memberTypeSegment: 0,
        birthday: "",
        backno: "",
        invitationFlg: false,
        email: "",
        adminFlg: false,
        selectedAvatarFilename: "preset_boy.png"
      }
    },
    computed: {
      selectedAvatarFilenameComputed: {
        get() {
          return this.selectedAvatarFilename;
        }
      }
    },
    methods: {
      register() {
        if (!this.name) {
          this.$ons.notification.alert('氏名を入れてください', {title: ''});
          return;
        }
        if (this.memberTypeSegment !== 0 && this.invitationFlg && !this.email) {
          this.$ons.notification.alert('メールアドレスを入れてください', {title: ''});
          return;
        }
        this.$http.post('/api/members', this.$data)
          .then(response => {
            this.loading = false;
          })
          .catch(error => {
            console.log(error.response);
            if (error.response.status === 401) {window.location.href = "/login";}
            this.loading = false;
          })
          // .finally(() => this.loading = false)
        ;
        this.$ons.notification.alert('メンバーを登録しました。', {title: ''});
        this.$store.dispatch('members/load', this.$http);
        this.$store.commit('navigator/pop');
      },
      changeMemberType() {
        if (this.memberTypeSegment === 0) {
          this.invitationFlg = false;
        } else{
          this.invitationFlg = true;
        }
      },
      changeAvatar(event) {
        // console.log(event.srcElement.id);
        this.selectedAvatarFilename = event.srcElement.id;
      }
    }
  };
</script>

<style>
  .prof_img {
    width: 64px;
    height: 64px;
    margin-left: 5px;
    margin-right: 5px;
  }
  .prof_imgelected {
    border: #ff8d00 2px solid;
  }
</style>