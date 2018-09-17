window._ = require('lodash');

/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */
window.$ = window.jQuery = require('jquery');

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */
import axios from 'axios';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
Vue.prototype.$http = axios;

/**
 * Next we will register the CSRF Token as a common header with Axios so that
 * all outgoing HTTP requests automatically have it attached. This is just
 * a simple convenience so we don't have to attach every token manually.
 */

let token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
  axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
  console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// require('./calendar');

import Vue from 'vue';
import Vuex from 'vuex';
import storeLike from './store.js';
import VueOnsen from 'vue-onsenui';
Vue.use(Vuex);
Vue.use(VueOnsen);

import AppNavigator from './components/AppNavigator.vue';
var vm = new Vue({
  el: '#app',
  render: h => h(AppNavigator),
  store: new Vuex.Store(storeLike),
  beforeCreate() {
    // Shortcut for Material Design
    Vue.prototype.md = this.$ons.platform.isAndroid();

    // Set iPhoneX flag based on URL
    if (window.location.search.match(/iphonex/i)) {
      document.documentElement.setAttribute('onsflag-iphonex-portrait', '');
      document.documentElement.setAttribute('onsflag-iphonex-landscape', '');
    }
  }
});

//Enterでサブミットしない。class="allow_submit"の場合はサブミットする。のはずだが動作しない。
/*
$(function() {
  $(document).on("keypress", "input:not(.allow_submit)", function(event) {
    return event.which !== 13;
  });
  $('.menulist').on('click', function(event) {
    $('.menulist').find(".current_menu_icon").each(function(index, icon) {
      $(icon).addClass("hidden");
    });
    var onsIcon = event.target.firstElementChild;
    $(onsIcon).removeClass("hidden");
  });
});
document.addEventListener('init', function(event) {
  var page = event.target;
  if (page.id == "calendar") {
    new TnCalendar('tnCalendar').create();
  }
});
*/
window.fn = {};
window.fn.openMenu = function() {
  menu.open();
};
window.fn.load = function(page) {
  content.load(page).then(menu.close.bind(menu));
};
window.fn.openPage = function(page, animation) {
  window.homeNavi.pushPage(page, {animation:animation});
};

// スライドメニューのカレントアイコン設定
$('.menulist').on('click', function(event) {
  $('.menulist').find(".current_menu_icon").each(function(index, icon) {
    $(icon).addClass("hidden");
  });
  var onsIcon = event.target.firstElementChild;
  $(onsIcon).removeClass("hidden");
});

window.fn.dateFormat =
  {
    _fmt : {
      h: function(date) { return date.getHours(); },
      mm: function(date) { return ('0' + date.getMinutes()).slice(-2); },
      dd: function(date) { return ('0' + date.getDate()).slice(-2); },
      d: function(date) { return date.getDate(); },
      yyyy: function(date) { return date.getFullYear() + ''; },
      w: function(date) {return ["日", "月", "火", "水", "木", "金", "土"][date.getDay()]; },
      MM: function(date) { return ('0' + (date.getMonth() + 1)).slice(-2); },
      M: function(date) { return date.getMonth() + 1; }
    },
    _priority : ["h", "mm", "dd", "d",　"yyyy", "w", "MM", "M"],
    format: function(date, format){
      return this._priority.reduce(
        (res, fmt) => res.replace(fmt, this._fmt[fmt](date)), format)
    }
  };