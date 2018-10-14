<template>
  <v-ons-page>
    <v-ons-toolbar class="navbar">
      <div class="left">
        <img src="/img/appicon2.png" class="logo">
      </div>
      <div class="center navbartitle">
        <v-ons-icon icon="fa-blog" size="20px"></v-ons-icon> ブログ
      </div>
    </v-ons-toolbar>
    <div class="page__background" style="background-color: white;"></div>
    <section v-if="errored">
      <p>ごめんなさい。エラーになりました。時間をおいてアクセスしてくださいm(_ _)m</p>
    </section>
    <section v-else>
      <div v-if="loading" class="progress-div">
        <v-ons-progress-circular indeterminate class="progress-circular"></v-ons-progress-circular>
      </div>
      <template v-else>
        <v-ons-row>
          <v-ons-col>
            <!--<v-ons-page :infinite-scroll="loadMore">-->
              <v-ons-list>
                <v-ons-list-item modifier="chevron">
                  <div>記事タイトル</div>
                </v-ons-list-item>
              </v-ons-list>
            <!--</v-ons-page>-->
          </v-ons-col>
        </v-ons-row>
      </template>
    </section>
  </v-ons-page>
</template>

<script>
  var blogRssUrl = 'http://rssblog.ameba.jp/tsubasa36th/rss20.xml';
  export default {
    beforeCreate() {
      this.loading = true;
      let self = this;

      // $.ajax(
      //   {
      //     url: blogRssUrl,
      //     type: 'GET',
      //     cache: false,
      //     dataType: 'xml',
      //     timeout: 7000,
      //
      //     success: function(res, status)
      //     {
      //       if (status === 'success')
      //       {
      //         // responseText から取得して、xml 形式に変換する必要があり
      //         var xmlText = res["responseText"];
      //         var xml = $.parseXML(xmlText);
      //
      //         var row = 0;
      //         var data = [];
      //         var nodeName;
      //
      //         $(xml).find('item').each(function()
      //         {
      //           data[row] = {};
      //           $(this).children().each(function()
      //           { 			// 子要素を取得
      //             nodeName = $(this)[0].nodeName; 			// 要素名
      //             data[row][nodeName] = {}; 						// 初期化
      //             attributes = $(this)[0].attributes; 	// 属性を取得
      //             for (var i in attributes)
      //             {
      //               data[row][nodeName][attributes[i].name] = attributes[i].value; // 属性名 = 値
      //             }
      //             data[row][nodeName]['text'] = $(this).text();
      //           });
      //           row++;
      //
      //         });
      //
      //         for (i in data)
      //         {
      //
      //           //時間を整形
      //           var date = data[i]["dc:date"].text;
      //           date = date.slice(0,9);
      //           date = date.replace( /-/g , "." ) ;
      //
      //           //5件を出力
      //           if(i < 6)
      //           {
      //             self.entries.push({
      //               'title': data[i].title.text,
      //               'link': data[i].link.text,
      //               'date': date
      //             });
      //             console.log(data[i].title.text);
      //             // $('#blog-rss').append('<li><a href="'+ data[i].link.text + '" target="_blank"><span class="date">'+ date + '</span><span class="title">'+ data[i].title.text +'</span></a></li>');
      //           }
      //         }
      //
      //       }
      //     }
      //
      //   });


      this.$http.get(blogRssUrl)
        .then((response)=> {
          alert(response.data);
          // $(response.data).find("item").each(function () {
          //   var el = $(this);
          //   console.log(el.find("link").text());
          //   console.log(el.find("title").text());
          //
          // });
        })
        .catch(error => {
          this.errored = true;
          console.log(error);
        })
        .finally(() => this.loading = false)
      ;
    },
    data() {
      return {
        entries: [],
        loading: false,
        errored: false
      };
    },
    methods: {
    }
  };
</script>

<style></style>