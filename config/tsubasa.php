<?php

return [

    /*
    |--------------------------------------------------------------------------
    | アプリ固有の設定
    |--------------------------------------------------------------------------
    |
    | 以前はコントローラから直接env()を呼んでいたが、config:cacheを実行すると
    | env()がnullを返すためconfigへ移した。値は.envで上書きできる。
    |
    */

    // 予定/iCalで読み込む前後の月数
    'schedule_data_loading_months' => (int) env('SCHEDULE_DATA_LOADING_MONTHS', 12),

    // タイムラインの1ページあたりの投稿数
    'timeline_load_posts' => (int) env('TIMELINE_LOAD_POSTS', 10),

    // LINE Notify連携
    'line_notify' => [
        'client_id' => env('LINE_NOTIFY_CLIENT_ID'),
        'client_secret' => env('LINE_NOTIFY_CLIENT_SECRET'),
        'callback_uri' => env('LINE_NOTIFY_CALLBACK_URI'),
    ],

];
