<template>
    <v-ons-page id="members">
      <v-ons-toolbar class="navbar">
        <div class="center navbartitle">
          <v-ons-icon icon="fa-users" size="20px"></v-ons-icon> メンバー
        </div>
        <div class="toolbar__right mr-5">
          <v-ons-toolbar-button @click="">
            <v-ons-icon icon="fa-search" class="white" size="28px"></v-ons-icon>
          </v-ons-toolbar-button>
        </div>
      </v-ons-toolbar>
      <v-ons-fab position="bottom right">
        <v-ons-icon icon="fa-plus" @click="openAddMember();"/>
      </v-ons-fab>
      <v-ons-row>
        <v-ons-col>
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
          <v-ons-list id="member_list">
            <v-ons-list-item v-for="member in members" :key="member.id"
                             tappable modifier="chevron" @click="openMember();">
              <div class="left">
                <img src="img/prof/{{ member.id }}  " class="prof_img">
              </div>
              <div class="w-100p">
                <p style="text-align: left">5.しんたろう</p>
                <div class="mr-30">
                  <v-ons-button class="highlight_btn">
                    <v-ons-icon icon="fa-play"></v-ons-icon>
                    ハイライト (12)</v-ons-button>
                </div>
              </div>
            </v-ons-list-item>
          </v-ons-list>
        </v-ons-col>
      </v-ons-row>
    </v-ons-page>
</template>

<script>
  import AddMember from './AddMember.vue';
  import Member from './Member.vue';
  export default {
    data() {
      return {
        members: [],
        current_member_type: 1  //1:選手、2:監督/コーチ、3:家族/友人
      }
    },
    methods: {
      openAddMember() {
        this.$store.commit('navigator/push', {
          extends: AddMember,
          onsNavigatorOptions: {animation: 'lift'}
        });
      },
      openMember(member_id) {
        this.$store.commit('navigator/push', {
          extends: Member,
          onsNavigatorOptions: {animation: 'slide'}
        });
      }
    }
  };
</script>

<style></style>