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
                    v-bind:class="'day' + (n-1) + ' ' +
                    (days[n-1].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[n-1].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+7].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (days[(n-1)+7].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[(n-1)+7].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+14].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (days[(n-1)+14].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[(n-1)+14].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+21].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (days[(n-1)+21].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[(n-1)+21].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+28].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (days[(n-1)+28].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[(n-1)+28].text"></span>
                </td>
              </tr>
              <tr v-if="days[35].text">
                <td v-bind:data-date="days[(n-1)+35].date"
                    v-bind:class="'day' + (n-1) + ' '
                    + (days[(n-1)+35].date === selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
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
          <ons-list-item expandable><!-- v-ons-list-itemにするとexpandableが効かないのでons-list-item -->
            <div class="left">
              <template v-if="!schedule.allday_flg">
                {{ formatTime(schedule.time_from) }}-{{ formatTime(schedule.time_to) }}
              </template>
              {{ schedule.title }}
            </div>
            <div class="expandable-content">
              <p class="mb-20">{{ schedule.content }}</p>
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
        selectedDate: this.moment(today).format('YYYY-MM-DD'),
        currentYear: today.getFullYear(),
        currentMonth: today.getMonth()
      }
    },
    beforeMount() {
      // APIからデータ取得(AddSchedule.vueからも呼ばれるのでVuexで処理)
      this.$store.dispatch('calendar/load', this.$http);
    },
    computed: {
      schedules : {
        get() { return this.$store.state.calendar.schedules; }
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
      selectedDateSchedules: {
        get() {
          let selectedDateSchedules = [];
          if (this.schedules) {
            for (var s=0; s<this.schedules.length; s++) {
              if (this.selectedDate === this.schedules[s].schedule_date) {
                selectedDateSchedules.push(this.schedules[s]);
              }
            }
          }
          return selectedDateSchedules;
        }
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
            dayArray[dayArrayIdx + d] = { date: dateText, text: d + 1 };
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
      },
      openAddSchedule() {
        this.$store.commit('navigator/push', {
          extends: AddSchedule,
          onsNavigatorOptions: {animation: 'lift'}
        });
      },
      openEditSchedule(schedule) {
        this.$store.commit('edit_schedule/setSchedule', schedule);
        this.$store.commit('navigator/push', {
          extends: EditSchedule,
          onsNavigatorOptions: {animation: 'lift'}
        });
      },
      formatTime(time) {
        if (!time) return '';
        return this.moment(time, 'HH:mm:ss').format('H:mm');
      },
      confirmDeleteSchedule(index) {
        let self = this;
        this.$ons.notification.confirm("この予定を削除しますか？", {title: ''})
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
          })
          // .finally(() => self.deleting = false);
      },
      afterDelete() {
        this.deleting = false;
        //TODO 画面から削除
        // this.$store.commit('navigator/pop');
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
    background-color:#ff0000;
    color:#ffffff;
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
</style>