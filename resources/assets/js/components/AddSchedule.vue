<template>
  <v-ons-page id="post">
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <v-ons-icon icon="fa-calendar" class="white" size="20px"></v-ons-icon>
        <span>予定登録</span>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-close" class="white" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
    </v-ons-toolbar>
    <div class="bg-white space">
      <form id="addScheduleForm" action="#" method="POST">
        <v-ons-row class="space">
          <v-ons-col width="70%">
            <!-- pickadate
                    <input id="dateForAdd" class="text-input text-input--border js__datepicker" 
                    name="dateForAdd" type="text" value="2018/03/31"/>-->
            <v-ons-input id="dateForAdd" class="text-input text-input--border"
                       name="dateForAdd" type="date" value="2018/05/31"></v-ons-input>
          </v-ons-col>
          <v-ons-col>
            <v-ons-switch id="allday_switch" value="1"></v-ons-switch>
            <label for="allday_switch" class="ml-5 mt-10">終日</label>
          </v-ons-col>
        </v-ons-row>
        <v-ons-row class="space">
          <!-- pickadate
                <input id="startHourForAdd" class="text-input text-input--border js__timepicker" 
                name="startHourForAdd" type="text" value=""/>
                <input id="startMinuteForAdd" class="text-input text-input--border js__timepicker" 
                name="startMinuteForAdd" type="text" value=""/>
                 〜 
                <input id="endTimeForAdd" class="text-input text-input--border js__timepicker" name="endTimeForAdd" type="text" value=""/>
                -->
          <v-ons-input class="time_input" modifier="border" type="time" id="startTimeForAdd"></v-ons-input>
          <div class="plr-10 pt-10">〜</div>
          <v-ons-input class="time_input" modifier="border" type="time" id="endTimeForAdd"></v-ons-input>
        </v-ons-row>
        <v-ons-row class="space">
          <v-ons-input modifier="border" name="title" type="text" class="w-100p" placeholder="件名"></v-ons-input>
        </v-ons-row>
        <div class="space">
          <select id="category" name="category" class="select-input select-input--underbar">
            <option>練習</option>
            <option>練習試合</option>
            <option>公式試合</option>
            <option>イベント</option>
            <option>その他</option>
          </select>
        </div>
        <div class="space">
          <textarea class="textarea w-100p" rows="7" placeholder="詳細"></textarea>
        </div>
        <div class="space">
          <div class="upload-btn-wrapper">
            <v-ons-button class="smallBtn button--outline">添付ファイル</v-ons-button>
            <input type="file" name="myfile" />
          </div>
        </div>
        <div class="space">
          みんなに通知 <v-ons-switch checked></v-ons-switch>
        </div>
        <div class="space">
          <v-ons-button class="mtb-20" modifier="large"
                        @click="$store.commit('navigator/pop');">登録</v-ons-button>
        </div>
      </form>
    </div>
  </v-ons-page>
</template>

<script>
  export default {
    beforeCreate() {
      // $('#dateForAdd').pickadate();
      // $('#startHourForAdd').pickatime({format:'H時', interval:60});
      // $('#startMinuteForAdd').pickatime({format:'i分', interval:5, min: new Date(2018,1,1,0,0), max: new Date(2018,1,1,0,59)});
      // $('#endTimeForAdd').pickatime();
    }
  };
</script>

<style>
</style>