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
            })
            .catch(error => {
              console.log(error);
              if (error.response.status == 401) {
                window.location.href = "/login"; return;
              }
            })
            .finally(() => context.commit('setLoading', false))
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
    }
  }
};
