<template>
  <div>
    <!-- Main content area with bottom navigation -->
    <v-main>
      <component :is="tabs[index].page"></component>
    </v-main>
    
    <!-- Bottom Navigation - replaces v-ons-tabbar -->
    <v-bottom-navigation
      v-model="index"
      color="primary"
      fixed
      grow
      app
    >
      <v-btn
        v-for="(tab, i) in tabs"
        :key="i"
        :value="i"
        height="100%"
      >
        <span>{{ tab.label }}</span>
        <v-badge
          v-if="tab.badge"
          :content="tab.badge"
          color="red"
          overlap
        >
          <v-icon>fa-{{ tab.icon }}</v-icon>
        </v-badge>
        <v-icon v-else>fa-{{ tab.icon }}</v-icon>
      </v-btn>
    </v-bottom-navigation>
  </div>
</template>

<script>
import Timeline from './Timeline.vue';
import Calendar from './Calendar.vue';
import Blog from './Blog.vue';
import Members from './Members.vue';
import Settings from './Settings.vue';
import Cookies from 'js-cookie';

// Just a linear interpolation formula
const lerp = (x0, x1, t) => parseInt((1 - t) * x0 + t * x1, 10);

export default {
  data () {
    return {
      animationOptions: {},
      topPosition: 0
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
      set(index) {
        this.$store.commit('tabbar/setIndex', index)
      }
    },
    tabs: {
      get() {
        let tabsTemp =
        [
          {
            label: 'タイムライン',
            icon: 'align-justify',
            page: Timeline,
            badge: this.$store.state.timeline.unreadCount
          },
          {
            label: 'カレンダー',
            icon: 'calendar-alt',
            page: Calendar
          },
          {
            label: 'ブログ',
            icon: 'rss',
            page: Blog
          },
          {
            label: 'メンバー',
            icon: 'users',
            page: Members
          },
          {
            label: '設定',
            icon: 'cog',
            page: Settings
          }
        ];
        if(Cookies.get('current_team_id') != 36) {
          tabsTemp.splice(2, 1);
        }
        // console.log('tabsTemp------------------');
        // console.log(tabsTemp);
        return tabsTemp;
      }
    }
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
</style>
