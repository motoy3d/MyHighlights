<template>
  <v-ons-page>
    <v-ons-toolbar class="navbar">
      <div class="center navbartitle">
        <span class="month_text mr-20" @click="goPrevMonth()">
          <v-ons-icon icon="fa-caret-left" size="24px"/>
          {{ prevMonthText }}
        </span>
        <span class="current_year_month">{{ currentYearMonthText }}</span>
        <span class="month_text ml-20" @click="goNextMonth()">
          {{ nextMonthText }}
          <v-ons-icon icon="fa-caret-right" size="24px"/>
        </span>
      </div>
    </v-ons-toolbar>
    <v-ons-fab position="bottom right">
      <v-ons-icon icon="fa-plus" @click="openAddSchedule();"/>
    </v-ons-fab>
    <div>
      <form id="calendarForm" action="#" method="POST">
        <div class="center">
          <table class="calendar-table calendar">
            <!--<caption>-->
              <!--<div>-->
                <!--<a href="#" class="prev" @click="goPrevMonth()">-->
                  <!--<i class="fa fa-caret-left"></i> {{ prevMonthText }}-->
                <!--</a>-->
                <!--<a href="#" class="next" @click="goNextMonth()">{{ nextMonthText }}-->
                  <!--<i class="fa fa-caret-right"></i>-->
                <!--</a>-->
              <!--</div>-->
            <!--</caption>-->
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
                    v-bind:class="'day' + (n-1) + ' ' + (days[n-1].date == selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[n-1].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+7].date"
                    v-bind:class="'day' + (n-1) + ' ' + (days[(n-1)+7].date == selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[(n-1)+7].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+14].date"
                    v-bind:class="'day' + (n-1) + ' ' + (days[(n-1)+14].date == selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[(n-1)+14].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+21].date"
                    v-bind:class="'day' + (n-1) + ' ' + (days[(n-1)+21].date == selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[(n-1)+21].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+28].date"
                    v-bind:class="'day' + (n-1) + ' ' + (days[(n-1)+28].date == selectedDate? 'selectedDate' : '')"
                    v-for="n in 7">
                  <span v-html="days[(n-1)+28].text"></span>
                </td>
              </tr>
              <tr>
                <td v-bind:data-date="days[(n-1)+35].date"
                    v-bind:class="'day' + (n-1) + ' ' + (days[(n-1)+35].date == selectedDate? 'selectedDate' : '')"
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
              <p>{{ schedule.content }}</p>
              <v-ons-button class="button button--outline smallBtn"
                            @click="openAddSchedule();">
                <v-ons-icon icon="fa-pencil" class="schedule_edit_icon"></v-ons-icon>
                編集
              </v-ons-button>
              <v-ons-button class="button button--outline smallBtn"
                            @click="alert('TODO');">
                <v-ons-icon icon="fa-copy" class="schedule_edit_icon"></v-ons-icon>
                コピー
              </v-ons-button>
            </div>
          </ons-list-item>
        </template>
        <v-ons-list-item v-if="selectedDate && selectedDateSchedules.length == 0">
          <div class="top list-item__top">
            予定はありません。
          </div>
        </v-ons-list-item>
      </v-ons-list>
    </div>
  </v-ons-page>
</template>

<script>
  import AddSchedule from './AddSchedule.vue';
  export default {
    beforeCreate() {
      // console.log('>>>>> Calendar#beforeCreate');
    },
    data() {
      // console.log(">>>>> Calendar#data()");
      var today = new Date();
      return {
        schedules: {},
        selectedDate: window.fn.dateFormat.format(today, 'yyyy-MM-dd'),
        selectedDateSchedules: [],
        currentYear: today.getFullYear(),
        currentMonth: today.getMonth()
      }
    },
    beforeMount() {
      // console.log('>>>>> Calendar#beforeMount');
      this.load();
    },
    computed: {
      currentYearMonthText: {
        get() { return this.currentYear + '年' + (this.currentMonth + 1) + '月'; }
      },
      prevMonthText: {
        get() { return (this.currentMonth == 0 ? 12 : this.currentMonth) + '月'; }
      },
      nextMonthText: {
        get() { return (this.currentMonth == 11 ? 1 : this.currentMonth + 2) + '月'; }
      },
      selectedDateText: {
        get() {
          return this.selectedDate?
            window.fn.dateFormat.format(new Date(this.selectedDate), "yyyy年M月d日(w)") : ""; }
      },
      days: { //スケジュールが入った日毎の配列
        get() {
          // console.log(">>>>> Calendar#computed");
          var dayArray = new Array(42); //6週分
          dayArray.fill({ date: '', text: '' });
          var firstDay = new Date(this.currentYear, this.currentMonth, 1);
          var dayArrayIdx = firstDay.getDay(); //1日の曜日
          var lastDate = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
          for (var d=0; d<lastDate; d++) {
            var dateText = this.currentYear + '-' + (('0' + (this.currentMonth + 1)).slice(-2))
              + '-' + (('0' + (d + 1)).slice(-2));
            dayArray[dayArrayIdx + d] = { date: dateText, text: d + 1 };
            if (this.schedules) {
              for (var s=0; s<this.schedules.length; s++) {
                if (dateText == this.schedules[s].schedule_date) {
                  dayArray[dayArrayIdx + d].text += '<br>' + this.schedules[s].title;
                }
              }
            }
          }
          return dayArray;
        }
      }
    },
    methods: {
      /**
       * APIをコールしてスケジュールデータを取得する
       */
      load() {
        // console.log(">>>>> Calendar#load()");
        var yearMonth = window.fn.dateFormat.format(new Date(), 'yyyyMM');
        this.$http.get('/api/schedules?month=' + yearMonth)
          .then((response)=>{
            this.schedules = response.data
            console.log(this.schedules);
          })
          .catch(error => {
            console.log(error);
            this.errored = true;
            if (error.response.status == 401) {
              window.location.href = "/login"; return;
            }
          })
          .finally(() => this.loading = false);
      },
      goPrevMonth() {
        if(this.currentMonth == 0) {
          this.currentYear--;
          this.currentMonth = 11;
        } else {
          this.currentMonth--;
        }
        this.selectedDate = null;
      },
      goNextMonth() {
        if(this.currentMonth == 11) {
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
        var evt = e || window.event;
        var target = $(evt.target || evt.srcElement);
        if (target.prop('tagName') == 'SPAN') {
          target = target.parent(); //タップする場所によってSPANかTDになるがTDで処理するため。
        }
        // jQueryのdata()メソッドはキャッシュしてしまうので使わない。
        // jQueryオブジェクト内の元のDOMオブジェクトのgetAttributeを使う。(キャッシュしないので)
        var td = target[0]; //jQueryオブジェクト内の元のDOMオブジェクト
        if (!td.getAttribute('data-date')) {
          return;
        }
        this.selectedDate = td.getAttribute('data-date');
        this.selectedDateSchedules = [];
        if (this.schedules) {
          for (var s=0; s<this.schedules.length; s++) {
            if (this.selectedDate == this.schedules[s].schedule_date) {
              this.selectedDateSchedules.push(this.schedules[s]);
            }
          }
        }
      },
      openAddSchedule() {
        this.$store.commit('navigator/push', {
          extends: AddSchedule,
          onsNavigatorOptions: {animation: 'lift'}
        });
      },
      formatTime(time) {
        return time.substring(0, 5);
      }
    }
  }
</script>

<style></style>