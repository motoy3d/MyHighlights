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
      <form id="postForm" action="#" method="POST" v-on:submit.prevent="save">
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
        <div class="space">
          このメンバーを招待する <v-ons-switch v-model="invitationFlg"></v-ons-switch>
        </div>
        <div class="ml-15"><small class="gray">メールアドレス</small></div>
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
  </v-ons-page>
</template>

<script>
  export default {
    beforeMount() {
      this.load();
    },
    data() {
      return {
        member: null,
        name: "",
        nameKana: "",
        memberTypeSegment: null,
        birthday: "",
        backno: "",
        invitationFlg: false,
        email: "",
        adminFlg: false
      }
    },
    methods: {
      // TODO 実装
      load() {
        // console.log('start load');
        this.loading = true;
        let member_id = this.$store.state.edit_member.member_id;
        this.$http.get('/api/members/' + member_id)
          .then((response)=>{
            this.member = response.data;
            this.name = this.member.name;
            this.nameKana = this.member.name_kana;
            this.memberTypeSegment = this.member.type - 1;
            this.birthday = this.member.birthday;
            this.backno = this.member.backno;
            this.invitationFlg = false;
            this.email = this.member.email;
            this.adminFlg = this.member.admin_flg === 1;
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

<style></style>