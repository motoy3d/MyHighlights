<template>
  <v-ons-page id="post">
    <v-ons-toolbar class="navbar">
      <div class="left ml-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-angle-left" class="white" size="32px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
      <div class="center navbartitle">
        <v-ons-icon icon="fa-user-edit" size="20px"></v-ons-icon>
        <span>メンバー情報</span>
      </div>
    </v-ons-toolbar>
    <div class="bg-white">
      <div class="center pt-10">
        <img v-if="avatarFileName" :src="'/storage/prof/' + avatarFileName" class="prof_img_main">
        <div><v-ons-button class="smallBtn" modifier="quiet" @click="showAvatarSelection($event)">変更</v-ons-button></div>
      </div>
      <form id="postForm" action="#" method="POST" v-on:submit.prevent="save">
        <div class="segment space center" style="width: 100%; margin: 0 auto;">
          <v-ons-segment :index.sync="memberTypeSegment" style="width: 91%">
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
          <v-ons-input modifier="border" placeholder="" id="name" name="name" class="w-100p"
                       v-model="name"></v-ons-input>
        </div>
        <div class="ml-15 mt-10"><small class="gray">名前(かな)</small></div>
        <div class="mlr-15 center">
          <v-ons-input modifier="border" placeholder="" class="w-100p"
                       v-model="nameKana"></v-ons-input>
        </div>
        <div class="ml-15 mt-10"><small class="gray">誕生日</small></div>
        <div class="ml-15">
          <v-ons-input modifier="border" name="title" type="date" class="w-100p"
                       v-model="birthday"></v-ons-input>
        </div>
        <template v-if="memberTypeSegment === 0">
          <div class="ml-15 mt-10"><small class="gray">背番号</small></div>
          <div class="ml-15">
            <v-ons-input modifier="border" name="backno" class="backno_input" type="number"
                         v-model="backno"></v-ons-input>
          </div>
        </template>
        <!--<div class="space mt-10">-->
          <!--<div class="upload-btn-wrapper">-->
            <!--<v-ons-button>プロフィール画像</v-ons-button>-->
            <!--<input type="file" name="myfile" />-->
          <!--</div>-->
        <!--</div>-->
        <div class="space" v-if="!email">
          このメンバーを招待する <v-ons-switch v-model="invitationFlg"></v-ons-switch>
        </div>
        <div class="ml-15 mt-10">
          <small class="gray">メールアドレス</small>
          <span class="notification ml-5 bg-gray" v-if="invitationFlg || user_id"><small>必須</small></span>
        </div>
        <div class="mlr-15 center">
          <v-ons-input modifier="border" placeholder="" name="title" class="w-100p"
                       v-model="email" :disabled="!email && !invitationFlg"></v-ons-input>
        </div>
        <div class="space mt-10">
          管理者 <v-ons-switch v-model="adminFlg"></v-ons-switch>
        </div>
        <div class="space">
          <v-ons-button class="mtb-20" modifier="large" @click="save()">保存</v-ons-button>
        </div>
      </form>
    </div>
    <!-- 検索popover -->
    <v-ons-popover cancelable direction="down" cover-target="true"
                   class="avatar_popover"
                   :visible.sync="avatarPopoverVisible"
                   :target="avatarPopoverTarget">
      <div class="center space">
        <img src="/storage/prof/preset_boy.png" id="preset_boy.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_boy.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_girl.png" id="preset_girl.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_girl.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_man.png" id="preset_man.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_man.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_woman.png" id="preset_woman.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_woman.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_kuma.png" id="preset_kuma.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_kuma.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_lion.png" id="preset_lion.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_lion.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_zou.png" id="preset_zou.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_zou.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_buta.png" id="preset_buta.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_buta.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_penguin.png" id="preset_penguin.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_penguin.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_kurage.png" id="preset_kurage.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_kurage.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_kinoko.png" id="preset_kinoko.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_kinoko.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_egg.png" id="preset_egg.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_egg.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_tofu.png" id="preset_tofu.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_tofu.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_obake.png" id="preset_obake.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_obake.png'? 'prof_img_selected' : '')"
        <img src="/storage/prof/preset_zombi.png" id="preset_zombi.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_zombi.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_greenmonster.png" id="preset_greenmonster.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_greenmonster.png'? 'prof_img_selected' : '')">
        <img src="/storage/prof/preset_hero.png" id="preset_hero.png" @click="changeAvatar($event)"
             :class="'prof_img_s ' + (selectedAvatarFilenameComputed === 'preset_hero.png'? 'prof_img_selected' : '')">
      </div>
    </v-ons-popover>
  </v-ons-page>
</template>

<script>
  export default {
    beforeCreate() {
      this.loading = true;
      let member_id = this.$store.state.edit_member.member_id;
      this.$http.get('/api/members/' + member_id)
        .then((response)=>{
          this.member = response.data;
          this.user_id = this.member.user_id;
          this.name = this.member.name;
          this.nameKana = this.member.name_kana;
          this.memberTypeSegment = this.member.type - 1;
          this.birthday = this.member.birthday;
          this.backno = this.member.backno;
          this.invitationFlg = false;
          this.email = this.member.email;
          this.adminFlg = this.member.admin_flg === 1;
          this.avatarFileName = this.member.prof_img_filename;
          this.selectedAvatarFilename = this.member.prof_img_filename;
          this.loading = false;
        })
        .catch(error => {
          console.log(error);
          this.errored = true;
          if (error.response.status == 401) {window.location.href = "/login"; return;}
          this.loading = false;
        })
      // .finally(() => this.loading = false)
      ;
    },
    data() {
      return {
        member: null,
        user_id: "",
        name: "",
        nameKana: "",
        memberTypeSegment: null,
        birthday: "",
        backno: "",
        invitationFlg: false,
        email: "",
        adminFlg: false,
        avatarFileName: "preset_white.png",
        changeAvatarMode: false,
        selectedAvatarFilename: "",
        avatarPopoverTarget: null,
        avatarPopoverVisible: false,
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
      showAvatarSelection() {
        this.avatarPopoverTarget = event;
        this.avatarPopoverVisible = true;
      },
      changeAvatar(event) {
        // console.log(event.srcElement.id);
        this.selectedAvatarFilename = event.srcElement.id;
        this.avatarFileName = event.srcElement.id;
        this.avatarPopoverVisible = false;
      },
      save() {
        if (!this.name) {
          this.$ons.notification.alert('氏名を入れてください', {title: ''});
          return;
        }
        if (this.memberTypeSegment !== 0 && this.invitationFlg && !this.email) {
          this.$ons.notification.alert('メールアドレスを入れてください', {title: ''});
          return;
        }
        this.$http.put('/api/members/' + this.member.id, this.$data)
          .then(response => {
            // console.log(response.data);
            this.loading = false;
          })
          .catch(error => {
            console.log(error.response);
            if (error.response.status == 401) {window.location.href = "/login";}
            this.loading = false;
          })
        // .finally(() => this.loading = false)
        ;
        this.$store.dispatch('members/load', this.$http);
        this.$store.commit('navigator/pop');
      }
    }
  };
</script>

<style>
  .prof_img_main {
    width: 96px;
    height: 96px;
    margin-left: 5px;
    margin-right: 5px;
  }
  .prof_img_s {
    width: 52px;
    height: 52px;
    margin-left: 5px;
    margin-right: 5px;
  }
  .prof_img_selected {
    border: #ff8d00 2px solid;
  }
  .avatar_popover {
    width: 360px;
    max-width: 400px;
  }
</style>