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
      <form id="postForm" action="#" method="POST" v-on:submit.prevent>
        <div class="segment space center" style="width: 100%; margin: 0 auto;">
          <v-ons-segment :index.sync="memberType" style="width: 91%">
            <button>選手</button>
            <button>監督/コーチ</button>
            <button>家族/友人</button>
          </v-ons-segment>
        </div>
        <div class="ml-15 mt-10"><small class="gray">名前(必須)</small></div>
        <div class="mlr-15 center">
          <v-ons-input modifier="border" placeholder="" class="w-100p"
                       v-model="name"></v-ons-input>
        </div>
        <div class="ml-15 mt-10"><small class="gray">誕生日</small></div>
        <div class="ml-15">
          <v-ons-input modifier="border" type="date" class="w-100p"
                       v-model="birthday"></v-ons-input>
        </div>
        <div class="ml-15 mt-10"><small class="gray">背番号</small></div>
        <div class="ml-15">
          <v-ons-input modifier="border" class="backno_input" type="number"
                       v-model="backno"></v-ons-input>
        </div>
        <div class="space">
          このメンバーを招待する <v-ons-switch v-model="invitationFlg"></v-ons-switch>
        </div>
        <div class="ml-15"><small class="gray">メールアドレス</small></div>
        <div class="mlr-15 center">
          <v-ons-input modifier="border" placeholder="" class="w-100p"
                       v-model="email"></v-ons-input>
        </div>
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
        memberType: 0,
        birthday: "",
        backno: "",
        invitationFlg: true,
        email: ""
      }
    },
    methods: {
      register() {
        //TODO validate
        if (!this.name) {
          alert('氏名は必須です');
          return;
        }
        if (this.invitationFlg && !this.email) {
          alert('メールアドレスは必須です');
          return;
        }
        this.$http.post('/api/members', this.$data)
          .then(response => {
            console.log(response.data);
          })
          .catch(error => {
            console.log(error.response);
            if (error.response.status == 401) {
              window.location.href = "/login"; return;
            }
          })
          .finally(() => this.loading = false);
        this.$store.commit('navigator/pop');
      }
    }
  };
</script>

<style>
</style>