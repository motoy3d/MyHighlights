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
window.fn = {};

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

// text内のURLをaタグに変換して返す。
window.fn.replaceATag =
  function(text) {
    const exp = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
    const span = $('<span />').text(text);
    return span.text().replace(exp, "<a href='$1' target='_blank'>$1</a>");
  };

import Vue from 'vue';
import Vuex from 'vuex';
import storeLike from './store.js';
import VueOnsen from 'vue-onsenui';
Vue.use(Vuex);
Vue.use(VueOnsen);

const moment = require('moment');
require('moment/locale/ja');
Vue.use(require('vue-moment'), {
  moment
});

Vue.filter('truncate', function(value, len, omission) {
  var length = len ? parseInt(len, 10) : 70;
  var ommision = omission ? omission.toString() : '...';
  if(value.length <= length) {
    return value;
  }
  else {
    return value.substring(0, length) + ommision;
  }
});
console.warn('>>>>>>>> アプリ起動');

import AppNavigator from './components/AppNavigator.vue';
var vm = new Vue({
  el: '#app',
  render: h => h(AppNavigator),
  store: new Vuex.Store(storeLike),
  beforeCreate() {
    // Shortcut for Material Design
    Vue.prototype.md = this.$ons.platform.isAndroid();
    Vue.prototype.moment = moment;

    // iPhone X系用レイアウト自動調整
    const html = document.documentElement;
    if (this.$ons.platform.isIPhoneX()) {
      html.setAttribute('onsflag-iphonex-portrait', '');
      html.setAttribute('onsflag-iphonex-landscape', '');
    }
    // this.$ons.disableAutoStatusBarFill();
  }
});
