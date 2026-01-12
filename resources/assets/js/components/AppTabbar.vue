<template>
  <div class="el-tabbar-container">
    <!-- Main content area -->
    <div class="el-tabbar-content">
      <component :is="tabs[index].page"></component>
    </div>
    
    <!-- Bottom Navigation using Element UI Tabs -->
    <el-tabs
      v-model="activeTab"
      type="card"
      class="el-bottom-tabs"
      @tab-click="handleTabClick"
    >
      <el-tab-pane
        v-for="(tab, i) in tabs"
        :key="i"
        :name="String(i)"
      >
        <span slot="label">
          <el-badge
            v-if="tab.badge"
            :value="tab.badge"
            type="danger"
            class="el-tab-badge"
          >
            <i :class="`fa fa-${tab.icon}`"></i>
            <span class="tab-label">{{ tab.label }}</span>
          </el-badge>
          <span v-else>
            <i :class="`fa fa-${tab.icon}`"></i>
            <span class="tab-label">{{ tab.label }}</span>
          </span>
        </span>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script>
import Timeline from './Timeline.vue';
import Calendar from './Calendar.vue';
import Blog from './Blog.vue';
import Members from './Members.vue';
import Settings from './Settings.vue';
import Cookies from 'js-cookie';

export default {
  data () {
    return {
    };
  },

  methods: {
    handleTabClick(tab) {
      this.index = parseInt(tab.name);
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
    activeTab: {
      get() {
        return String(this.index);
      },
      set(val) {
        this.index = parseInt(val);
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
        return tabsTemp;
      }
    }
  }
};
</script>

<style scoped>
.el-tabbar-container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.el-tabbar-content {
  flex: 1;
  overflow: auto;
  padding-bottom: 60px; /* Space for fixed tabs */
}

.el-bottom-tabs {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: #fff;
  box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
  z-index: 1000;
}

.el-bottom-tabs >>> .el-tabs__header {
  margin: 0;
}

.el-bottom-tabs >>> .el-tabs__nav {
  width: 100%;
  display: flex;
}

.el-bottom-tabs >>> .el-tabs__item {
  flex: 1;
  text-align: center;
  padding: 10px;
  height: 60px;
  line-height: 20px;
}

.tab-label {
  display: block;
  font-size: 12px;
  margin-top: 4px;
}

.el-bottom-tabs >>> .el-tabs__item i {
  font-size: 20px;
  display: block;
  margin-bottom: 4px;
}

.el-tab-badge >>> .el-badge__content {
  font-size: 10px;
}
</style>
