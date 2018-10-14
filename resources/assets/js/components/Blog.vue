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
                <v-ons-list-item v-for="(entry,index) in entries" :key="entry.link"
                                 modifier="chevron" tappable @click="openEntry(entry.link)">
                  <div class="row">
                    <p class="date">{{ entry.date }}</p>
                    <p class="title">{{ entry.title }}</p>
                  </div>
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


      this.$http.get('api/blog')
        .then((response)=> {
          console.log(response.data);
          this.entries = response.data;
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
      openEntry(link) {
        location.href = link;
      }
    }
  };
</script>

<style>
  .row {
    width: 92%;
  }
  .title {
    font-size: 18px;
    text-align:left;
    margin: 8px 0px;
  }
  .date {
    color: grey;
    font-size: 13px;
    text-align: left;
    margin: 0;
  }
</style>