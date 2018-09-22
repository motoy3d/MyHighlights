<template>
  <v-ons-page>
    <v-ons-tabbar
      position="auto"
      swipeable
      :tabs="tabs"
      :index.sync="index"
    ></v-ons-tabbar>
    <!--<v-ons-tabbar position="auto"-->
      <!--swipeable-->
      <!--:modifier="md ? 'autogrow white-content' : ''"-->
      <!--:on-swipe="md ? onSwipe : null"-->
      <!--:tabbar-style="swipeTheme"-->
      <!--:tabs="tabs"-->
      <!--:index.sync="index"-->
    <!--&gt;</v-ons-tabbar>-->
  </v-ons-page>
</template>

<script>
import Timeline from './Timeline.vue';
import Calendar from './Calendar.vue';
import Members from './Members.vue';
import Settings from './Settings.vue';

// Just a linear interpolation formula
const lerp = (x0, x1, t) => parseInt((1 - t) * x0 + t * x1, 10);
// RGB colors
const red = [244, 67, 54];
const blue = [30, 136, 229];
const purple = [103, 58, 183];

export default {
  data () {
    return {
      // colors: red,
      animationOptions: {},
      topPosition: 0,
      tabs: [
        {
          label: 'タイムライン',
          icon: 'fa-rss',
          page: Timeline,
          badge: 3
        },
        {
          label: 'カレンダー',
          icon: 'fa-calendar',
          page: Calendar
        },
        {
          label: 'メンバー',
          icon: 'fa-users',
          page: Members
        },
        {
          label: '設定',
          icon: 'fa-cog',
          page: Settings
        }
      ]
    };
  },

  methods: {
    onSwipe(index, animationOptions) {
      // Apply the same transition as ons-tabbar
      this.animationOptions = animationOptions;

      // Interpolate colors and top position
      const a = Math.floor(index), b = Math.ceil(index), ratio = index % 1;
      this.colors = this.colors.map((c, i) => lerp(this.tabs[a].theme[i], this.tabs[b].theme[i], ratio));
      this.topPosition = lerp(this.tabs[a].top || 0, this.tabs[b].top || 0, ratio);
    }
  },

  computed: {
    index: {
      get() {
        return this.$store.state.tabbar.index;
      },
      set(newValue) {
        this.$store.commit('tabbar/set', newValue)
      }
    },
    title() {
      return this.md ? 'Onsen UI' : this.tabs[this.index].title || this.tabs[this.index].label;
    },
    // swipeTheme() {
    //   return this.md && {
    //     backgroundColor: `rgb(${this.colors.join(',')})`,
    //     transition: `all ${this.animationOptions.duration || 0}s ${this.animationOptions.timing || ''}`
    //   }
    // },
    // swipePosition() {
    //   return this.md && {
    //     top: this.topPosition + 'px',
    //     transition: `all ${this.animationOptions.duration || 0}s ${this.animationOptions.timing || ''}`
    //   }
    // }
  }
};
</script>

<style>
/*!* Custom 'white-content' modifier *!*/

/*.page--material .toolbar--white-content__center,*/
/*.page--material .toolbar-button--white-content,*/
/*.page--material :checked + .tabbar--white-content__button {*/
  /*color: red;*/
/*}*/

/*.page--material .tabbar--white-content__button {*/
  /*!*color: rgba(255, 255, 255, 0.7);*!*/
  /*color: blue;*/
/*}*/

/*.page--material .tabbar--white-content__border {*/
  /*background-color: white;*/
/*}*/
</style>
