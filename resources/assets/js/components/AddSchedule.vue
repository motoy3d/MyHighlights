<template>
  <v-ons-page id="post" class="bg-white">
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <v-ons-icon icon="fa-calendar" class="white" size="20px"
                    style="font-weight:400"></v-ons-icon>
        <span>予定登録</span>
      </div>
      <div class="right mr-5">
        <v-ons-toolbar-button @click="$store.commit('navigator/pop');">
          <v-ons-icon icon="fa-close" class="white" size="28px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
    </v-ons-toolbar>
    <div class="page__background" style="background-color: white;"></div>
    <div class="bg-white space">
      <form id="addScheduleForm" action="#" method="POST">
        <v-ons-row class="space">
          <v-ons-col width="70%">
            <!-- pickadate
                    <input id="dateForAdd" class="text-input text-input--border js__datepicker" 
                    name="dateForAdd" type="text" value="2018/03/31"/>-->
            <v-ons-input id="dateForAdd" class="text-input text-input--border"
                       name="dateForAdd" type="date" v-model="schedule_date"></v-ons-input>
          </v-ons-col>
          <v-ons-col>
            <v-ons-switch id="allday_switch" v-model="allday_flg"></v-ons-switch>
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
          <v-ons-input class="time_input" modifier="border" type="time"
                       v-model="time_from" :disabled="allday_flg"></v-ons-input>
          <div class="plr-10 pt-10">〜</div>
          <v-ons-input class="time_input" modifier="border" type="time"
                       v-model="time_to" :disabled="allday_flg"></v-ons-input>
        </v-ons-row>
        <v-ons-row class="space">
          <v-ons-input modifier="border" name="title" type="text" class="w-100p"
                       placeholder="件名" v-model="title"></v-ons-input>
        </v-ons-row>
        <div class="space">
          <v-ons-select v-model="selected_category" class="category_select">
            <option v-for="cate in categories" :value="cate.id">
              {{ cate.name }}
            </option>
          </v-ons-select>
        </div>
        <div class="space">
          <textarea class="textarea w-100p" rows="7" placeholder="詳細"
                    v-model="contents"></textarea>
        </div>
        <div class="mb-10" v-if="0 < fileNames.length">
          <ul>
            <li v-for="(file, index) in fileNames" class="mtb-10 break">
              {{ file }}
            </li>
          </ul>
        </div>
        <!--<div class="space">-->
          <!--<div class="upload-btn-wrapper">-->
            <!--<v-ons-button class="smallBtn" modifier="outline">添付ファイル</v-ons-button>-->
            <!--<input type="file" name="myfile" />-->
          <!--</div>-->
        <!--</div>-->
        <!--<div class="space">-->
          <!--みんなに通知 <v-ons-switch v-model="notification_flg"></v-ons-switch>-->
        <!--</div>-->
        <div class="space">
          <v-ons-button id="postBtn" class="mtb-20" modifier="large"
                        @click="addSchedule()" :disabled="posting">
            <v-ons-icon icon="fa-spinner" spin v-if="posting"></v-ons-icon>
            投稿
          </v-ons-button>
        </div>
      </form>
    </div>
  </v-ons-page>
</template>

<script>
  export default {
    beforeCreate() {
      this.$http.get('/api/schedules/create')
        .then((response)=>{
          this.categories = response.data.categories;
          this.loading = false;
        })
        .catch(error => {
          console.log(error);
          this.errored = true;
          if (error.response.status === 401) {window.location.href = "/login";}
          this.loading = false;
        })
        // .finally(() => this.loading = false)
      ;
      // $('#dateForAdd').pickadate();
      // $('#startHourForAdd').pickatime({format:'H時', interval:60});
      // $('#startMinuteForAdd').pickatime({format:'i分', interval:5, min: new Date(2018,1,1,0,0), max: new Date(2018,1,1,0,59)});
      // $('#endTimeForAdd').pickatime();
    },
    beforeMount() {
      this.schedule_date = this.$store.state.add_schedule.selectedDate;
    },
    data() {
      return {
        loading: false,
        errored: false,
        posting: false,
        schedule_date: null,
        allday_flg: false,
        time_from: '',
        time_to: '',
        title: '',
        categories: [],
        selected_category: null,
        category_id: null,
        contents: '',
        files: [],
        fileNames: [],
        notification_flg: false
      };
    },
    methods: {
      // ファイルが選択された時
      onFileSet(event) {
        // console.log("onFileSet.");
        this.files = event.target.files;
        this.fileNames = [];
        for (let i=0; i<this.files.length; i++) {
          this.fileNames.push(this.files[i].name);
        }
        // console.log(this.files);
      },
      addSchedule() {
        //TODO validate
        if (this.posting) {
          return;
        }
        if (!this.title) {this.$ons.notification.alert('タイトルを入れてください', {title: ''});return;}
        this.posting = true;
        this.category_id = this.selected_category? this.selected_category : this.categories[0].id;
        let self = this;
        // 送信フォームデータ準備
        let formData = new FormData();
        formData.append('title', this.title);
        formData.append('schedule_date', this.schedule_date);
        formData.append('allday_flg', this.allday_flg);
        formData.append('time_from', this.time_from);
        formData.append('time_to', this.time_to);
        formData.append('contents', this.contents);
        formData.append('category_id', this.category_id);
        formData.append('notification_flg', this.notification_flg);
        for(let i = 0; i < this.files.length; i++) {
          formData.append('files[]', this.files[i]);
        }
        // console.log('送信フォーム');
        // console.log(this.$data);
        // 送信
        this.$http.post('/api/schedules', formData)
          .then(response => {
            // console.log(response.data);
            // TODO toastの方がよいか
            this.$ons.notification.alert('登録しました', {title: ''})
              .then(function(){
                self.$store.dispatch('calendar/load', self.$http);
                self.$store.commit('navigator/pop');
              });
            this.loading = false; this.posting = false;
          })
          .catch(error => {
            console.log(error.response);
            if (error.response.status === 401) {
              window.location.href = "/login";
            }
            this.loading = false; this.posting = false;
          })
          // .finally(() => {this.loading = false; this.posting = false;})
        ;
      }
    }
  };
</script>

<style>
  .category_select {
    width: 10rem;
  }
</style>