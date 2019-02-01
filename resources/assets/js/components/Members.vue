<template>
  <v-ons-page id="members">
    <v-ons-toolbar class="navbar">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
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
      <v-ons-icon icon="fa-plus" @click="openAddMember();"></v-ons-icon>
    </v-ons-fab>
    <v-ons-row>
      <v-ons-col>
        <div class="segment space" style="width: 91%; margin: 0 auto;">
          <button class="segment__item" @click="changeType(1)">
            <input type="radio" class="segment__input" name="segment-a" checked>
            <div class="segment__button">選手</div>
          </button>
          <button class="segment__item" @click="changeType(2)">
            <input type="radio" class="segment__input" name="segment-a">
            <div class="segment__button">監督/コーチ</div>
          </button>
          <button class="segment__item" @click="changeType(3)">
            <input type="radio" class="segment__input" name="segment-a">
            <div class="segment__button">家族</div>
          </button>
        </div>
        <v-ons-list id="member_list">
          <v-ons-list-item v-for="member in viewMembers" :key="member.id"
                           tappable modifier="chevron" @click="openMember(member.id);">
            <div class="left">
              <img :src="'/storage/prof/' + member.prof_img_filename" class="prof_img">
            </div>
            <div class="w-100p">
              <p style="text-align: left">
                <span v-if="member.type == 1 && member.backno">{{ member.backno }}.</span>
                {{ member.name }}
              </p>
              <!--<div class="mr-30">-->
                <!--<v-ons-button class="highlight_btn">-->
                  <!--<v-ons-icon icon="fa-play"></v-ons-icon>-->
                  <!--ハイライト (12)</v-ons-button>-->
              <!--</div>-->
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
    beforeCreate() {
      this.$store.dispatch('members/load', this.$http);
    },
    data() {
      return {
        loading: true,
        viewMemberType: 1  //1:選手、2:監督/コーチ、3:家族/友人
      }
    },
    computed: {
      viewMembers: {
        get() {
          let members = [];
          if (!this.$store.state.members.members) {
            return members;
          }
          for (let i=0; i<this.$store.state.members.members.length; i++) {
            let mem = this.$store.state.members.members[i];
            if (mem.type == this.viewMemberType) {
              members.push(mem);
            }
          }
          return members;
        }
      }
    },
    methods: {
      changeType(type) {
        this.viewMemberType = type;
      },
      openAddMember() {
        this.$store.commit('navigator/push', {
          extends: AddMember,
          onsNavigatorOptions: {animation: 'lift'}
        });
      },
      openMember(member_id) {
        this.$store.commit('edit_member/setMemberId', member_id);
        this.$store.commit('navigator/push', {
          extends: Member,
          onsNavigatorOptions: {animation: 'slide'}
        });
      }
    }
  };
</script>

<style>
  #member_list ons-list-item {
    padding: 0px 15px;
  }
  .prof_img {
    width: 64px;
    height: 64px;
  }
  .highlight_btn {
    font-size: 13px;
    border-radius: 20px;
    padding: 1px 20px;
    width: 150px;
  }
</style>