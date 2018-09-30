export default {
  modules: {
    navigator: {
      strict: true,
      namespaced: true,
      state: {
        stack: [],
        options: {}
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
        }
      }
    },

    splitter: {
      strict: true,
      namespaced: true,
      state: {
        open: false
      },
      mutations: {
        toggle(state, shouldOpen) {
          if (typeof shouldOpen === 'boolean') {
            state.open = shouldOpen;
          } else {
            state.open = !state.open;
          }
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

    timeline: {
      strict: true,
      namespaced: true,
      state: {
        posts: [],
        loading: false
      },
      mutations: {
        set(state, posts) {
          console.log('store.js#timeline/set '  + posts);
          state.posts = posts;
        },
        setLoading(state, isLoading) {
          state.loading = isLoading;
        }
      },
      actions: {
        loadTimeline(context, $http) {
          context.commit('setLoading', true);
          console.log('store.js#timeline/loadTimeline');
          $http.get('/api/posts')
            .then((response)=>{
              context.commit('set', response.data.data);
            })
            .catch(error => {
              console.log(error);
              if (error.response.status == 401) {
                window.location.href = "/login"; return;
              }
            })
          .finally(() => context.commit('setLoading', false))
          ;
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
    }
  }
};
