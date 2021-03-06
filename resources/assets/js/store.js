export default {
  modules: {
    navigator: {
      strict: true,
      namespaced: true,
      state: {
        stack: [],
        options: {},
        user: {},
        currentTeamId: null,
        currentTeamName: null
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
        },
        setCurrentTeamId(state, currentTeamId) {
          state.currentTeamId = currentTeamId;
        },
        setCurrentTeamName(state, currentTeamName) {
          state.currentTeamName = currentTeamName;
        },
      }
    },

    tabbar: {
      strict: true,
      namespaced: true,
      state: {
        index: 0,
        tabs: []
      },
      mutations: {
        setIndex(state, index) {
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
        searchUnread: null,
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
        setSearchUnread(state, unread) {
          state.searchUnread = unread;
        },
        setUnreadCount(state, unreadCount) {
          // console.log('1 unreadCount========' + unreadCount);
          if (0 <= unreadCount) {
            state.unreadCount = unreadCount? unreadCount : null;
          }
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
          if (context.state.searchUnread) {
            api += (paramFlg? '&' : '?') + 'unread=' + context.state.searchUnread;
          }
          console.log('posts URL=' + api);
          $http.get(api)
            .then((response)=>{
              // console.log('nextPageUrl=' + response.data.posts.next_page_url);
              let nextUrl = response.data.posts.next_page_url;
              if (nextUrl && context.state.searchKeyword) {
                nextUrl += '&keyword=' + context.state.searchKeyword;
              }
              if (nextUrl && context.state.searchCategoryId) {
                nextUrl += '&category=' + context.state.searchCategoryId;
              }
              if (nextUrl && context.state.searchUnread) {
                nextUrl += '&unread=' + context.state.searchUnread;
              }
              context.commit('set', response.data.posts.data);
              context.commit('setUnreadCount', response.data.unreadCount);
              context.commit('setNextPageUrl', nextUrl);
              context.commit('setLoading', false);
            })
            .catch(error => {
              console.log(error);
              if (error.response.status == 401) {window.location.href = "/login"; return;}
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
              if (nextUrl && context.state.searchUnread) {
                nextUrl += '&unread=' + context.state.searchUnread;
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
      },
      actions: {
        //TODO 実装中
        // load(context, $http) {
        //   context.commit('setLoading', true);
        //   let post_id = context.state.article.post_id;
        //   $http.get('/api/posts/' + post_id)
        //       .then((response)=>{
        //         this.post = response.data.post;
        //         this.post_responses = response.data.post_responses;
        //         this.post_attachments = response.data.post_attachments;
        //         this.questionnaire = response.data.questionnaire;
        //         this.comments = response.data.comments;
        //         this.likes = response.data.likes;
        //         this.likes_count = this.likes? this.likes.length : 0;
        //         this.user = response.data.user;
        //         this.loading = false;
        //       })
        //       .catch(error => {
        //         console.log(error);
        //         this.errored = true;
        //         if (error.response.status == 401) {
        //           window.location.href = "/login"; return;
        //         }
        //         this.loading = false;
        //       })
        //   // .finally(() => this.loading = false)
        //   ;
        // }
      }
    },

    // カレンダー画面
    calendar: {
      strict: true,
      namespaced: true,
      state: {
        loading: false,
        schedules: null,
        holidays: null
      },
      mutations: {
        set(state, data) {
          state.schedules = data.schedules;
          state.holidays = data.holidays;
        },
        setLoading(state, isLoading) {
          state.loading = isLoading;
        }
      },
      actions: {
        load(context, $http) {
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

    // 予定作成画面
    add_schedule: {
      strict: true,
      namespaced: true,
      state: {
        selectedDate: null
      },
      mutations: {
        setSelectedDate(state, selectedDate) {
          state.selectedDate = selectedDate;
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
          // console.log('members/load');
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
          // console.log('members/load');
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
