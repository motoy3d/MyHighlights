export default {
  modules: {
    navigator: {
      strict: true,
      namespaced: true,
      state: {
        stack: [],
        options: {},
        user: {},
        currentTeamId: null
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
        index: 0
      },
      mutations: {
        set(state, index) {
          state.index = index;
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
        loading: false,
        searchKeyword: null,
        searchCategoryId: null,
        unreadCount: null
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
        },
        setSearchKeyword(state, keyword) {
          state.searchKeyword = keyword;
        },
        setSearchCategoryId(state, categoryId) {
          state.searchCategoryId = categoryId;
        },
        setUnreadCount(state, unreadCount) {
          // console.log('1 unreadCount========' + unreadCount);
          state.unreadCount = unreadCount? unreadCount : null;
          // console.log('2 state.unreadCount========' + state.unreadCount);
        }
      },
      actions: {
        load(context, $http) {
          context.commit('setLoading', true);
          let api = '/api/posts';
          let paramFlg = false;
          if (context.state.searchKeyword) {
            api += '?keyword=' + context.state.searchKeyword; paramFlg = true;
          }
          if (context.state.searchCategoryId) {
            api += (paramFlg? '&' : '?') + 'category=' + context.state.searchCategoryId;
          }
          console.log('posts URL=' + api);
          $http.get(api)
            .then((response)=>{
              // console.log('nextPageUrl=' + response.data.posts.next_page_url);
              let nextUrl = response.data.posts.next_page_url;
              if (nextUrl && context.state.searchKeyword) {
                nextUrl += '&keyword=' + context.state.searchKeyword;
              }
              context.commit('set', response.data.posts.data);
              context.commit('setUnreadCount', response.data.unreadCount);
              context.commit('setNextPageUrl', nextUrl);
              context.commit('setLoading', false);
            })
            .catch(error => {
              console.log(error);
              if (error.response.status == 401) {
                window.location.href = "/login"; return;
              }
              context.commit('setLoading', false);
            })
            //iOS10以下?でfinallyの付近でundefinedエラーになる
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
              let nextUrl = response.data.posts.next_page_url;
              if (nextUrl && context.state.searchKeyword) {
                nextUrl += '&keyword=' + context.state.searchKeyword;
              }
              if (nextUrl && context.state.searchCategoryId) {
                nextUrl += '&category=' + context.state.searchCategoryId;
              }
              context.commit('add', response.data.posts.data);
              context.commit('setNextPageUrl', nextUrl);
              param.done();
            })
            .catch(error => {
              console.log(error);
              if (error.response.status == 401) {
                window.location.href = "/login"; return;
              }
              param.done();
            })
            // .finally(() => param.done())
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
          console.log('スケジュール読み込み');
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
              context.commit('setLoading', false)
            })
            .catch(error => {
              console.log(error);
              if (error.response.status === 401) {
                window.location.href = "/login";
              }
              context.commit('setLoading', false);
            })
            // .finally(() => context.commit('setLoading', false))
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
    },

    // メンバー一覧画面
    members: {
      strict: true,
      namespaced: true,
      state: {
        loading: false,
        members: null
      },
      mutations: {
        setMembers(state, members) {
          state.members = members;
        },
        setLoading(state, isLoading) {
          state.loading = isLoading;
        }
      },
      actions: {
        load(context, $http) {
          console.log('members/load');
          context.commit('setLoading', true);

          $http.get('/api/members')
            .then((response)=>{
              context.commit('setMembers', response.data);
              context.commit('setLoading', false);
            })
            .catch(error => {
              console.log(error);
              if (error.response.status == 401) {window.location.href = "/login"; return;}
              context.commit('setLoading', false);
            });
          // .finally(() => this.loading = false);
        }
      }
    },

    // メンバー編集画面
    edit_member: {
      strict: true,
      namespaced: true,
      state: {
        loading: false,
        member_id: null
      },
      mutations: {
        setMemberId(state, member_id) {
          state.member_id = member_id;
        },
        setLoading(state, isLoading) {
          state.loading = isLoading;
        }
      },
      actions: {
        load(context, $http) {
          console.log('members/load');
          context.commit('setLoading', true);

          $http.get('/api/members')
              .then((response)=>{
                context.commit('setMembers', response.data);
                context.commit('setLoading', false);
              })
              .catch(error => {
                console.log(error);
                if (error.response.status == 401) {window.location.href = "/login"; return;}
                context.commit('setLoading', false);
              });
          // .finally(() => this.loading = false);
        }
      }
    }

  }
};
