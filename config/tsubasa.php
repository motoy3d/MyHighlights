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

    // /api/* のレート制限（1分あたりのリクエスト数）。
    //
    // 本番は既定の60のまま運用する。緩めるのは移行のテスト時だけ。
    // ブラウザ自動テストを通しで回すと1つのIPからの合算で60を超え、
    // アプリ側に429のハンドリングが無いため画面が
    // 「ごめんなさい。エラーになりました」になって原因が分かりにくい。
    //
    // 【重要】切り替え当夜は .env から API_RATE_LIMIT を消すこと。
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 60),

    // 添付ファイル1件あたりの上限(KB)。
    // php.ini の upload_max_filesize / post_max_size もこれ以上にしておくこと。
    'attachment_max_kb' => (int) env('ATTACHMENT_MAX_KB', 20480),

];
