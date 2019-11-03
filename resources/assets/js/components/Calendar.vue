<template>
  <v-ons-page @reload="load()">
    <v-ons-toolbar class="navbar">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
      <div class="center navbartitle">
        <span class="month_text mr-20" @click="goPrevMonth()">
          <v-ons-icon icon="fa-caret-left" size="24px"></v-ons-icon>
          {{ prevMonthText }}
        </span>
        <span class="current_year_month">{{ currentYearMonthText }}</span>
        <span class="month_text ml-20" @click="goNextMonth()">
          {{ nextMonthText }}
          <v-ons-icon icon="fa-caret-right" size="24px"></v-ons-icon>
        </span>
      </div>
    </v-ons-toolbar>
    <v-ons-fab position="bottom right">
      <v-ons-icon icon="fa-plus" @click="openAddSchedule();"></v-ons-icon>
    </v-ons-fab>
    <div>
      <form id="calendarForm" action="#" method="POST">
        <div class="center">
          <table class="calendar-table calendar">
            <tbody @click="selectDate()">
              <tr>
                <th class="day-head day0">日</th>
                <th class="day-head day1">月</th>
                <th class="day-head day2">火</th>
                <th class="day-head day3">水</th>
                <th class="day-head day4">木</th>
                <th class="day-head day5">金</th>
                <th class="day-head day6">土</th>
              </tr>
              <tr>
                <td v-bind:data-date="days[n-1].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (getHolidayName(days[n-1].date) && n!=7? 'holiday ' : '')
                    + (days[n-1].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7" :key="(n-1)">
                  <span v-html="days[n-1].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+7].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (getHolidayName(days[(n-1)+7].date) && n!=7? 'holiday ' : '')
                    + (days[(n-1)+7].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7" :key="(n-1)+7">
                  <span v-html="days[(n-1)+7].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+14].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (getHolidayName(days[(n-1)+14].date) && n!=7? 'holiday ' : '')
                    + (days[(n-1)+14].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7" :key="(n-1)+14">
                  <span v-html="days[(n-1)+14].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+21].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (getHolidayName(days[(n-1)+21].date) && n!=7? 'holiday ' : '')
                    + (days[(n-1)+21].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7" :key="(n-1)+21">
                  <span v-html="days[(n-1)+21].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+28].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (getHolidayName(days[(n-1)+28].date) && n!=7? 'holiday ' : '')
                    + (days[(n-1)+28].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7" :key="(n-1)+28">
                  <span v-html="days[(n-1)+28].text"></span>
                </td>
              </tr>
              <tr v-if="days[35].text">
                <td v-bind:data-date="days[(n-1)+35].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (getHolidayName(days[(n-1)+35].date)? 'holiday ' : '')
                    + (days[(n-1)+35].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7" :key="(n-1)+35">
                  <span v-html="days[(n-1)+35].text"></span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </form>
    </div>
    <div>
      <v-ons-list v-if="selectedDate">
        <v-ons-list-header>
          {{ selectedDateText }}
        </v-ons-list-header>
        <template v-for="(schedule, index) in selectedDateSchedules">
          <ons-list-item :id="'scheduleListItem' + index" expandable :key="index"><!-- v-ons-list-itemにするとexpandableが効かないのでons-list-item -->
            <div class="left">
              <template v-if="!schedule.allday_flg">
                {{ formatTime(schedule.time_from) }}-{{ formatTime(schedule.time_to) }}
              </template>
              {{ schedule.title }}
            </div>
            <div class="expandable-content">
              <p class="mb-20 pre-wrap">{{ schedule.content }}</p>
              <v-ons-button class="button smallBtn" modifier="outline"
                            @click="openEditSchedule(schedule);">
                <v-ons-icon icon="fa-pencil" class="schedule_edit_icon"></v-ons-icon>
                &nbsp;編集&nbsp;
              </v-ons-button>
              <!-- TODO 予定コピー -->
              <!--<v-ons-button class="button smallBtn ml-10" modifier="outline"-->
                            <!--@click="alert('TODO');">-->
                <!--<v-ons-icon icon="fa-copy" class="schedule_edit_icon"></v-ons-icon>-->
                <!--コピー-->
              <!--</v-ons-button>-->
              <v-ons-button class="button smallBtn fl-right gray pt-10" modifier="quiet"
                            @click="confirmDeleteSchedule(index)">
                <v-ons-icon icon="fa-trash" class="gray"></v-ons-icon>
              </v-ons-button>

              <!-- コメント入力 -->
              <v-ons-row class="mt-30">
                <v-ons-col width="30px" vertical-align="bottom" class="left">
                  <v-ons-icon icon="fa-bell" :class="(comment_notification_flg? 'goodblue' : 'lightgray') + ' mb-5'"
                              size="24px" @click="toggleNotification()"></v-ons-icon>
                </v-ons-col>
                <v-ons-col vertical-align="bottom">
                <textarea class="textarea comment_textarea" rows="3" placeholder="コメント"
                          v-model="comment_text" @keyup="fitTextarea()"></textarea>
                </v-ons-col>
                <v-ons-col width="50px" vertical-align="bottom" class="center">
                  <v-ons-button class="ml-5 mt-10 center" ripple
                                @click="postComment(schedule.id)">
                    <v-ons-icon icon="fa-paper-plane" class="messageBtn"></v-ons-icon></v-ons-button>
                </v-ons-col>
              </v-ons-row>

              <!-- コメント一覧 -->
              <v-ons-row class="space lastspace" v-if="schedule.comments">
                <v-ons-col>
                  <div class="mt-10 ml-15" v-for="comment in schedule.comments" :key="comment.id">
                    <!--<hr class="mt-15">-->
                    <div class="mb-10">
                      <div class="fl-left">
                        <img :src="'/storage/prof/' + comment.prof_img_filename" class="prof_img_xs">
                      </div>
                      <div>
                        <span class="bold">
                          {{ comment.created_name }}
                        </span>
                        <span class="updated_at">
                          <template v-if="moment(new Date()).diff(moment(comment.created_at), 'days') <= 2">
                            {{ comment.created_at | moment("from") }}　
                          </template>
                          <template v-else>
                            {{ comment.created_at | moment('Y.M.D(dd) H:mm') }}
                          </template>
                        </span>
                      </div>
                    </div>
                    <div>
                      <div class="speech-bubble">
                        <span class="comment" v-html="replaceATag(comment.comment_text)"></span>
                        <span v-if="comment.user_id == $store.state.navigator.user.id"><!-- 型が違うので==使用 -->
                          <v-ons-icon icon="fa-trash" class="delete_comment_icon"
                                      @click="confirmDeleteComment(schedule.id, comment.id)"></v-ons-icon>
                        </span>
                        <p v-for="(att, index) in comment.attachments" :key="index">
                          <a :href="att.file_path">
                            <img :src="att.file_path" v-if="isImage(att.file_type)" class="image_in_post">
                            <span>{{ att.original_file_name }}</span>
                          </a>
                        </p>
                      </div>
                    </div>
                    <!--<div class="right mr-10">-->
                    <!--<v-ons-icon icon="fa-thumbs-up"-->
                    <!--:class="isLike? 'like_on' : 'like_off'"-->
                    <!--onclick="toggleLike(this)"></v-ons-icon>-->
                    <!--<span class="like-count">{{ comment.like_user_ids.length }}</span>-->
                    <!--</div>-->
                  </div>
                </v-ons-col>
              </v-ons-row>

            </div>
          </ons-list-item>
        </template>
        <v-ons-list-item v-if="selectedDate && selectedDateSchedules.length === 0">
          <div class="top list-item__top">
            予定はありません。
          </div>
        </v-ons-list-item>
      </v-ons-list>
    </div>
    <div class="lastspace"></div>
  </v-ons-page>
</template>

<script>
  import AddSchedule from './AddSchedule.vue';
  import EditSchedule from './EditSchedule.vue';
  export default {
    data() {
      // console.log(">>>>> Calendar#data()");
      var today = new Date();
      return {
        errored: false,
        deleting: false,
        selectedDate: null,
        selectedDateSchedules: [],
        currentYear: today.getFullYear(),
        currentMonth: today.getMonth(),
        comment_text: "",
        comment_files: [],
        comment_notification_flg: true,
      }
    },
    beforeCreate() {
      // APIからデータ取得(AddSchedule.vueからも呼ばれるのでVuexで処理)
      this.$store.dispatch('calendar/load', this.$http);
    },
    computed: {
      schedules : {
        get() { return this.$store.state.calendar.schedules; }
      },
      holidays : {
        get() { return this.$store.state.calendar.holidays; }
      },
      currentYearMonthText: {
        get() { return this.currentYear + '年' + (this.currentMonth + 1) + '月'; }
      },
      prevMonthText: {
        get() { return (this.currentMonth === 0 ? 12 : this.currentMonth) + '月'; }
      },
      nextMonthText: {
        get() { return (this.currentMonth === 11 ? 1 : this.currentMonth + 2) + '月'; }
      },
      selectedDateText: {
        get() {
          return this.selectedDate?
            window.fn.dateFormat.format(new Date(this.selectedDate), "yyyy年M月d日(w)") : ""; }
      },
      days: { //スケジュールが入った日毎の配列
        get() {
          // console.log(">>>>> Calendar#computed");
          let dayArray = new Array(42); //6週分
          dayArray.fill({ date: '', text: '' });
          let firstDay = new Date(this.currentYear, this.currentMonth, 1);
          let dayArrayIdx = firstDay.getDay(); //1日の曜日
          let lastDate = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
          for (let d=0; d<lastDate; d++) {
            let dateText = this.currentYear + '-' + (('0' + (this.currentMonth + 1)).slice(-2))
              + '-' + (('0' + (d + 1)).slice(-2));
            dayArray[dayArrayIdx + d] = {
              date: dateText, text: d + 1 + ' ' + this.getHolidayName(dateText) };
            if (this.schedules) {
              for (let s=0; s<this.schedules.length; s++) {
                let sche = this.schedules[s];
                if (dateText === sche.schedule_date) {
                  let time = sche.allday_flg? '' : this.formatTime(sche.time_from) + ' ';
                  dayArray[dayArrayIdx + d].text +=
                    '<br><ons-icon icon="caret-right"></ons-icon> ' + time + sche.title;
                }
              }
            }
          }
          return dayArray;
        }
      }
    },
    methods: {
      goPrevMonth() {
        if(this.currentMonth === 0) {
          this.currentYear--;
          this.currentMonth = 11;
        } else {
          this.currentMonth--;
        }
        this.selectedDate = null;
      },
      goNextMonth() {
        if(this.currentMonth === 11) {
          this.currentYear++;
          this.currentMonth = 0;
        } else {
          this.currentMonth++;
        }
        this.selectedDate = null;
      },
      /**
       * 日付をタップした時の処理。画面下側の予定表示を更新する。
       * @param e
       */
      selectDate(e) {
        let evt = e || window.event;
        let target = $(evt.target || evt.srcElement);
        if (target.prop('tagName') === 'SPAN') {
          target = target.parent(); //タップする場所によってSPANかTDになるがTDで処理するため。
        }
        // jQueryのdata()メソッドはキャッシュしてしまうので使わない。
        // jQueryオブジェクト内の元のDOMオブジェクトのgetAttributeを使う。(キャッシュしないので)
        let td = target[0]; //jQueryオブジェクト内の元のDOMオブジェクト
        if (!td.getAttribute('data-date')) {
          return;
        }
        this.selectedDate = td.getAttribute('data-date');
        this.loadSchedules();
      },
      loadSchedules() {
        this.selectedDateSchedules = [];
        // console.log('--------------- loadSchedules');
        if (this.schedules) {
          for (var s=0; s<this.schedules.length; s++) {
            if (this.selectedDate === this.schedules[s].schedule_date) {
              let schedule = this.schedules[s];
              this.selectedDateSchedules.push(schedule);
              let self = this;
              // コメント取得
              this.$http.get('/api/schedule_comments/' + schedule.id)
                .then((response)=>{
                  // Vueに対して変更通知 https://jp.vuejs.org/v2/guide/reactivity.html
                  self.$set(schedule, 'comments', response.data);
                })
                .catch(error => {
                  this.errored = true;
                  if (error.response.status == 401) {window.location.href = "/login"; return;}
                  this.loading = false;
                });
            }
          }
          setTimeout(function() {
            // 予定の内容を展開する
            let scheduleListItem0 = document.querySelector('#scheduleListItem0');
            if (scheduleListItem0 && !scheduleListItem0.expanded) {
              scheduleListItem0.showExpansion();
            }
          }, 150);
        }
      },
      getHolidayName(date) {
        if (!date || !this.holidays) {return '';}
        let holidayName = '';
        this.holidays.forEach(function(holiday){
          if (holiday.holiday_date == date) {
            holidayName = holiday.name;
            return;
          }
        });
        return holidayName;
      },
      openAddSchedule() {
        this.$store.commit('add_schedule/setSelectedDate', this.selectedDate);
        this.$store.commit('navigator/push', {
          extends: AddSchedule,
          onsNavigatorOptions: {animation: 'lift'}
        });
      },
      openEditSchedule(schedule) {
        this.$store.commit('edit_schedule/setSchedule', schedule);
        this.$store.commit('navigator/push', {
          extends: EditSchedule,
          onsNavigatorOptions: {animation: 'lift'},
          onsNavigatorProps: {
            reloadSchedules : this.loadSchedules
          }
        });
      },
      formatTime(time) {
        if (!time) return '';
        return this.moment(time, 'HH:mm:ss').format('H:mm');
      },
      confirmDeleteSchedule(index) {
        let self = this;
        this.$ons.notification.confirm("この予定を削除しますか？", {title: '', buttonLabels:['キャンセル', 'OK']})
          .then(function(ok) {
            if(!ok) {return;}
            self.deleteSchedule(index);
          });
      },
      deleteSchedule(index) {
        this.deleting = true;
        let schedule_id = this.selectedDateSchedules[index].id;
        let self = this;
        self.$http.delete('/api/schedules/' + schedule_id)
          .then((response)=>{
            // console.log(response.data);
            this.$store.dispatch('calendar/load', this.$http);
            this.$ons.notification.alert('削除しました', {title: ''})
              .then(function(){
                self.afterDelete();
              });
          })
          .catch(error => {
            console.log(error);
            self.errored = true;
            if (error.response.status === 401) {
              window.location.href = "/login"; return;
            }
          });
      },
      afterDelete() {
        this.deleting = false;
        //TODO 画面から削除
        // this.$store.commit('navigator/pop');
      },
      // ファイルが選択された時
      onFileSet(event) {
        // console.log("onFileSet.");
        this.comment_files = event.target.files;
      },
      fitTextarea() {
        let num = event.srcElement.value.match(/\r\n|\n/g);
        if (num != null && 3 < num.length) {
          event.srcElement.rows = num.length > 8 ? 8 : num.length + 1;
        } else {
          event.srcElement.rows = 3;
        }
      },
      postComment(schedule_id) {
        if (!this.comment_text) {
          return;
        }
        let self = this;
        // 送信フォームデータ準備
        let formData = new FormData();
        formData.append('comment_text', this.comment_text);
        formData.append('comment_notification_flg', this.comment_notification_flg);
        for(let i = 0; i < this.comment_files.length; i++){
          formData.append('comment_files[]', this.comment_files[i]);
        }
        this.$http.post('/api/schedule_comments/' + schedule_id, formData)
          .then((response)=>{
            // console.log(response.data);
            self.comment_text = '';
            self.comment_files = [];
            this.loadSchedules();
            this.loading = false;
          })
          .catch(error => {
            this.errored = true;
            if (error.response.status == 401) {window.location.href = "/login"; return;}
            this.loading = false;
          })
        // .finally(() => this.loading = false)
        ;
      },
      confirmDeleteComment(schedule_id, comment_id) {
        let self = this;
        this.$ons.notification.confirm("削除しますか？", {title: '', buttonLabels:['キャンセル', 'OK']})
          .then(function(ok) {
            if(!ok) {return;}
            self.deleteComment(schedule_id, comment_id);
          });
      },
      deleteComment(schedule_id, comment_id) {
        // console.log("コメントID=" + comment_id);
        let self = this;
        self.$http.delete('/api/schedule_comments/' + schedule_id + '/' + comment_id)
          .then((response)=>{
            // console.log(response.data);
            this.loadSchedules();
          })
          .catch(error => {
            console.log(error);
            self.errored = true;
            if (error.response.status === 401) {window.location.href = "/login"; return;}
            self.loading = false;
          })
        // .finally(() => self.loading = false)
        ;
      },
      toggleNotification() {
        this.comment_notification_flg = !this.comment_notification_flg;
      },
      replaceATag(text) {
        return window.fn.replaceATag(text);
      }
    }
  }
</script>

<style>
  table.calendar-table {
    margin:1px auto 0 auto;
    padding:0;
    width:100%;
    border-top:solid 1px #a3a3a3;
    border-left:solid 1px #a3a3a3;
    border-collapse: collapse;
  }
  table.calendar-table caption{
    text-align:left;
    background:#eee;
  }
  table.calendar-table caption div{
    position:relative;
    padding: 13px;
    font-size: 16px;
    height: 24px;
    font-weight: bold;
  }
  table.calendar-table caption span{
    display:block;
    padding:3px;
    text-align:center;
  }
  table.calendar-table caption a{
    display:inline-block;
    position:absolute;
    background:#eee;
    text-decoration:none;
    font-weight:bold;
    padding:3px;
    top:0;
  }
  table.calendar-table caption a:link,
  table.calendar-table caption a:visited{
    color:#666;
  }
  table.calendar-table caption a:hover{
    color:#f0f;
  }
  table.calendar-table caption a.next{
    top: 10px;
    right:30px;
  }
  table.calendar-table caption a.prev{
    top: 10px;
    left:30px;
  }
  table.calendar-table tr th{
    padding:0;
    text-align:center;
    background-color:#c7d8ef;
    border-right:solid 1px #a3a3a3;
    border-bottom:solid 1px #a3a3a3;
  }
  table.calendar-table tr th.day0{
    background-color:#ef9595;
    width: 60px;
  }
  table.calendar-table tr th.day6{
    background-color:#a6c0e4;
    width: 60px;
  }
  table.calendar-table tr td{
    padding:0 1px 0 1px;
    vertical-align: top;
    text-align: left;
    background-color:#ffffff;
    border-right:solid 1px #a3a3a3;
    border-bottom:solid 1px #a3a3a3;
    font-size:12px;
    height: 60px;
    width: 25px;
  }
  table.calendar-table tr td#day1 {
    border-right:none;
    background-color:#eeeeee;
  }
  table.calendar-table tr td#calLeft {
    border-right:none;
    background-color:#eeeeee;
  }
  table.calendar-table tr td#calRight {
    background-color:#eeeeee;
  }
  table.calendar-table tr td.day0{
    background-color:#ffcccc;
  }
  table.calendar-table tr td.day6{
    width:31px;
    background-color:#e9f2ff;
  }
  table.calendar-table tr td.holiday{
    background-color:#ffcccc;
  }
  table.calendar-table tr td.selectedDate{
    background-color:#fff090;
    border: 2px #808080 solid;
  }
  table.calendar-table tr td span{
    font-size:9px;
    line-height:0.7;
  }
  .schedule_edit_icon {
    color: #2266ff;
  }
  .current_year_month {
    vertical-align: top;
  }
  .month_text {
    font-size: 12px;
    vertical-align: top;
  }
  .lastspace {
    margin-bottom: 80px;
  }
  .comment_textarea {
    width: 100%;
  }
  .lastspace {
    margin-bottom: 80px;
  }
  .comment {
    font-size: 14px;
    margin: 0;
    white-space: pre-wrap;
    user-select: text;
    -webkit-user-select: text;
    -webkit-touch-callout: default;
    -webkit-tap-highlight-color: rgba(41, 147, 239, 1) !important;
  }
  .speech-bubble {
    position: relative;
    background: #81ff4f;
    border-radius: .3em;
    padding: 15px;
    margin-top: 6px;
  }

  .speech-bubble:after {
    content: '';
    position: absolute;
    top: 0;
    left: 5%;
    width: 0;
    height: 0;
    border: 6px solid transparent;
    border-bottom-color: #81ff4f;
    border-top: 0;
    margin-left: -6px;
    margin-top: -6px;
  }
  .delete_comment_icon {
    color: gray;
    float: right;
  }
  .image_in_post {
    max-width: 100%;
  }
  .messageBtn {
    width: 20px;
  }
  .prof_img_xs {
    width: 24px;
    height: 24px;
    margin-left: 5px;
    margin-right: 3px;
  }
</style>