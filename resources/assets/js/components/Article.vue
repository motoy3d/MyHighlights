<template>
  <!-- ■■■■■■■■■■■■■■ 投稿詳細 ■■■■■■■■■■■■■■ -->
  <v-ons-page class="bg-white">
    <!-- ツールバー -->
    <v-ons-toolbar class="navbar">
      <div class="left ml-5">
        <v-ons-toolbar-button @click="popPage();">
          <v-ons-icon icon="fa-chevron-left" size="24px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
      <div class="right mr-5">
        <!-- 削除や編集は画面内に直接表示したほうが使いやすい? -->
        <v-ons-toolbar-button onclick="alert('投稿編集画面')">
          <v-ons-icon icon="fa-pencil" size="24px"></v-ons-icon>
        </v-ons-toolbar-button>
      </div>
    </v-ons-toolbar>
    <!-- メインコンテンツ -->
    <div class="page__background" style="background-color: white;"></div>
    <v-ons-row class="space">
      <v-ons-col>
        <div class="entry_title_row">
          <p class="entry_title">3/11(日)練習場所変更</p>
          <p class="updated_at">3.10 15:39　片岡瑛太(父)</p>
        </div>
        <div class="entry_content">
	      <span>3/11(日)の練習場所を変更いたします。<br>
	      土手AM　→　日吉南PM<br><br>よろしくお願いします。<br>
	      </span>
        </div>
      </v-ons-col>
    </v-ons-row>
    <!-- アンケート -->
    <v-ons-row class="space">
      <v-ons-col>
        <p class="bold"><v-ons-icon icon="fa-bookmark" class="black"></v-ons-icon>
          6/9(土)ルーキーリーグ出欠確認</p>
        <!-- ActionSheetで入力するので不要
              <div class="mt-5">
                <v-ons-button class="smallBtn button--outline" onclick="showQuestionnaireModal();">
                  アンケートに回答する</v-ons-button>
              </div>-->
        <div class="mt-5">
          <table class="quetionnaire_table">
            <tr>
              <td>回答候補１</td>
              <td class="quetionnaire_results">○10 △0 ✕1</td>
              <td class="quetionnaire_btn">
                <v-ons-button class="smallBtn button--quiet" onclick="showQuestionnaireActionSheet();">
                  回答
                </v-ons-button>
              </td>
            </tr>
            <tr>
              <td>回答候補２</td>
              <td>○10 △0 ✕1</td>
              <td class="quetionnaire_btn">
                <v-ons-button class="smallBtn button--quiet" onclick="showQuestionnaireActionSheet();">
                  回答
                </v-ons-button>
              </td>
            </tr>
          </table>
        </div>
      </v-ons-col>
    </v-ons-row>
    <v-ons-row class="space">
      <v-ons-col>
        <!-- ハート -->
        <div class="center">
          <v-ons-icon icon="fa-heart-o" class="heart" onclick="toggleHeart(this);">
            <span class="heart_text">いいね</span>
            <span class="heart-count">1</span>
          </v-ons-icon>
          <v-ons-icon icon="fa-star-o" class="star" onclick="toggleStar(this);">
            <span class="star_text">お気に入り</span>
          </v-ons-icon>
        </div>
        <!-- コメント -->
        <div>
          <p>
	        <textarea class="textarea comment_textarea"
                    rows="3" placeholder="コメント"></textarea>
          </p>
          <div class="upload-btn-wrapper">
            <v-ons-button class="smallBtn button--outline">添付ファイル</v-ons-button>
            <input type="file" name="myfile" />
          </div>
          <div class="mt-20">
            <v-ons-button class="mt-10">コメントする</v-ons-button>
          </div>
        </div>
      </v-ons-col>
    </v-ons-row>
    <v-ons-row class="space">
      <v-ons-col>
        <div class="mt-20 ml-15">
          <hr class="mt-15">
          <div><span class="bold">3.11(日) 22:39　田中大志(父)</span></div>
          <span>了解しました！<br>よろしくお願いします。</span>
          <div>
            <v-ons-icon icon="fa-thumbs-up" class="like_off" onclick="toggleLike(this)"></v-ons-icon>
            <span class="like-count">2</span>
          </div>
        </div>

        <div class="mt-20 ml-15">
          <hr class="mt-15">
          <div><span class="bold">3.11(日) 22:39　矢野明子(母)</span></div>
          <span>了解です〜👌</span>
          <div>
            <v-ons-icon icon="fa-thumbs-up" class="like_off" onclick="toggleLike(this)"></v-ons-icon>
            <span class="like-count"></span>
          </div>
          <hr class="mt-20">
        </div>
      </v-ons-col>
    </v-ons-row>

    <!-- アンケート回答画面Modal ※ActionSheetで回答入力するので不要 -->
    <v-ons-modal var="quetionnaireAnswerModal">
      <form id="quetionnaireAnswerForm" action="#" method="POST">
        <div class="quetionnaire_container p-10">
          <div class="row">
            <div class="col">
              <h4>6/9(土)ルーキーリーグ出欠確認</h4>
              <div class="mt-20">
                <p>回答候補１</p>
                <v-ons-radio name="selection" input-id="selection-0"></v-ons-radio>
                <label for="selection-0" class="center">◯</label>
                <v-ons-radio name="selection" input-id="selection-1"></v-ons-radio>
                <label for="selection-1" class="center">△</label>
                <v-ons-radio name="selection" input-id="selection-2"></v-ons-radio>
                <label for="selection-2" class="center">✕</label>
                <p>回答候補２</p>
                <v-ons-radio name="selection" input-id="selection-0"></v-ons-radio>
                <label for="selection-0" class="center">◯</label>
                <v-ons-radio name="selection" input-id="selection-1"></v-ons-radio>
                <label for="selection-1" class="center">△</label>

                <table class="quetionnaire_table">
                  <tr>
                    <td>回答候補１</td>
                    <td>
                      <v-ons-select>
                        <option></option>
                        <option>◯</option>
                        <option>△</option>
                        <option>✕</option>
                      </v-ons-select>
                    </td>
                  </tr>
                  <tr>
                    <td>回答候補２</td>
                    <td>
                      <v-ons-select>
                        <option></option>
                        <option>◯</option>
                        <option>△</option>
                        <option>✕</option>
                      </v-ons-select>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="space">
              <v-ons-button class="plr-30" onclick="hideQuestionnaireModal();">OK</v-ons-button>
            </div>
            <div class="space">
              <v-ons-button class="bg-gray" onclick="hideQuestionnaireModal();">閉じる</v-ons-button>
            </div>
          </div>
        </div>
      </form>
    </v-ons-modal>
  </v-ons-page>
</template>

<script>
  export default {
    methods: {
      // var fromPage = null;
      // document.addEventListener('init', function(event) {
      //   var page = event.target;
      //   fromPage = page.data.fromPage;
      //   console.log("init. fromPage=" + fromPage);
      // });
      popPage() {
        // var navi = null;
        // if(fromPage == "timeline") {
        //   navi = homeNavi;
        // }
        // else if(fromPage == "notification") {
        //   navi = notificationNavi;
        // }
        // navi.popPage({animation:'simpleslide'});
        console.log("popPage");
        this.$store.commit('navigator/pop');
      },
      toggleHeart(heartIcon) {
        if($(heartIcon).attr("icon") == "fa-heart-o") {
          $(heartIcon).attr("icon", "fa-heart");
        } else {
          $(heartIcon).attr("icon", "fa-heart-o");
        }
      },
      toggleStar(starIcon) {
        if($(starIcon).attr("icon") == "fa-star-o") {
          $(starIcon).attr("icon", "fa-star");
        } else {
          $(starIcon).attr("icon", "fa-star-o");
        }
      },
      toggleLike(likeIcon) {
        if($(likeIcon).hasClass("like_off")) {
          $(likeIcon).removeClass("like_off");
          $(likeIcon).addClass("like_on");
        } else {
          $(likeIcon).removeClass("like_on");
          $(likeIcon).addClass("like_off");
        }
      },
      showQuestionnaireModal() {
        var modal = document.querySelector('ons-modal');
        modal.show();
      },
    
      hideQuestionnaireModal() {
        var modal = document.querySelector('ons-modal');
        modal.hide();
      },
      showQuestionnaireActionSheet() {
        ons.openActionSheet({
          title: '回答',
          cancelable: true,
          buttons: ['◯', '△', '✕', 'キャンセル']
        });
    
      }
    }
  };
</script>

<style></style>