export default {
  modules: {
    navigator: {
      strict: true,
      namespaced: true,
      state: {
        stack: [],
        options: {},
        user: {}
      },
      mutations: {
        push(state, page) {
          state.stack.push(page);
        },
        pop(state) {
          if (state.stack.length > 1) {
            state.stack.pop();
          }
        },
        replace(state, page) {
          state.stack.pop();
          state.stack.push(page);
        },
        reset(state, page) {
          state.stack = [page || state.stack[0]];
        },
        options(state, newOptions = {}) {
          state.options = newOptions;
        },
        setUser(state, user) {
          state.user = user;
        }
      }
    },

    tabbar: {
      strict: true,
      namespaced: true,
      state: {
        index: 0,
        timeline_badge: 0
      },
      mutations: {
        set(state, index) {
          state.index = index;
        },
        setTimelineBadge(state, count) {
          state.timeline_badge = count;
        }
      }
    },

    // タイムライン
    timeline: {
      strict: true,
      namespaced: true,
      state: {
        posts: [],
        nextPageUrl: null,
        loading: false
      },
      mutations: {
        set(state, posts) {
          state.posts = posts;
        },
        add(state, morePosts) {
          state.posts = state.posts.concat(morePosts);
        },
        setNextPageUrl(state, url) {
          state.nextPageUrl = url;
        },
        setLoading(state, isLoading) {
          state.loading = isLoading;
        }
      },
      actions: {
        load(context, $http) {
          context.commit('setLoading', true);
          $http.get('/api/posts')
            .then((response)=>{
              context.commit('set', response.data.data);
              context.commit('setNextPageUrl', response.data.next_page_url);
              context.commit('setLoading', false);
            })
            // .catch(error => {
            //   console.log(error);
            //   if (error.response.status == 401) {
            //     window.location.href = "/login"; return;
            //   }
            // })
            // .finally(() => context.commit('setLoading', false))
            // .finally(function() {return context.commit('setLoading', false);})
            ;
        },
        loadMore(context, param) {
          if (!context.state.nextPageUrl) {
            return;
          }
          param.http.get(context.state.nextPageUrl)
            .then((response)=>{
              context.commit('add', response.data.data);
              context.commit('setNextPageUrl', response.data.next_page_url);
            })
            .catch(error => {
              console.log(error);
              if (error.response.status == 401) {
                window.location.href = "/login"; return;
              }
            })
            .finally(() => param.done())
          ;
        }
      }
    },

    // 投稿画面(新規/編集)
    post: {
      strict: true,
      namespaced: true,
      state: {
        post_id: null,
        loading: false
      },
      mutations: {
        setPostId(state, post_id) {
          state.post_id = post_id;
        },
        setLoading(state, isLoading) {
          state.loading = isLoading;
        }
      }
    },

    // 記事画面
    article: {
      strict: true,
      namespaced: true,
      state: {
        post_id: null,
        loading: false
      },
      mutations: {
        setPostId(state, post_id) {
          state.post_id = post_id;
        },
        setLoading(state, isLoading) {
          state.loading = isLoading;
        }
      }
    },

    // カレンダー画面
    calendar: {
      strict: true,
      namespaced: true,
      state: {
        loading: false,
        schedules: null
      },
      mutations: {
        set(state, schedules) {
          state.schedules = schedules;
        },
        setLoading(state, isLoading) {
          state.loading = isLoading;
        }
      },
      actions: {
        load(context, $http) {
          console.log('calendar/load');
          context.commit('setLoading', true);
          var yearMonth = window.fn.dateFormat.format(new Date(), 'yyyyMM');
          $http.get('/api/schedules?month=' + yearMonth)
            .then((response)=>{
              context.commit('set', response.data);
            })
            .catch(error => {
              console.log(error);
              if (error.response.status === 401) {
                window.location.href = "/login";
              }
            })
            .finally(() => context.commit('setLoading', false))
          ;
        }
      }
    },

    // 予定編集画面
    edit_schedule: {
      strict: true,
      namespaced: true,
      state: {
        schedule: null,
        loading: false
      },
      mutations: {
        setSchedule(state, schedule) {
          state.schedule = schedule;
        },
        setLoading(state, isLoading) {
          state.loading = isLoading;
        }
      }
    }
  }
};
