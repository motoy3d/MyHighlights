<?php

  namespace App\Console\Commands\CybozeImport;

  use App\Post;
  use App\PostComment;
  use App\User;
  use Illuminate\Console\Command;
  use Illuminate\Support\Facades\DB;

  class PostImporter extends Command
  {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cybozu:post_import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cybozuから投稿（掲示板）データをインポート';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
      parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $filepath = fopen(storage_path('app/') . 'cybozu/post.csv', 'r');
      $posts = [];
      $comments = [];
      fgetcsv($filepath); //ヘッダ行
      while ($row = fgetcsv($filepath)) {
        $id = $row[0];
        $title = $row[1];
        $content = $row[2];
        $create_username = $row[3];
        $created_at = str_replace('/', '-', $row[4]) . ':00';
        $update_username = $row[5];
        $updated_at = str_replace('/', '-', $row[6]) . ':00';
        $comment = str_replace('--------------------------------------------------',
          '----------------------------------------', $row[7]);
        $this->info($created_at . ' ' . $title);

        // 作成・更新ユーザー名からユーザーIDを取得
        $create_user = User::where(
          'name', str_replace_first(' ', '', $create_username))->first();
        $created_id = $create_user? $create_user->id : -1;  //-1は退会者
        $update_user = User::where(
          'name', str_replace_first(' ', '', $update_username))->first();
        $updated_id = $update_user? $update_user->id : -1;  //-1は退会者

        $post = new Post();
        $post->fill([
          'team_id' => 100,
          'title' => $title,
          'content' => $content,
          'category_id' => 6, // CSVにないので「その他」で登録
          'questionnaire_id' => 0,
          'notification_flg' => 0,
          'comment_count' => 0,
          'created_id' => $created_id,
          'created_at' => $created_at,
          'updated_id' => $updated_id,
          'updated_at' => $updated_at
        ]);
        // 古い順に配列に入れる
        array_unshift($posts, $post);
        array_unshift($comments, $comment);
      }
      // DB登録
      DB::transaction(function () use ($posts, $comments) {
        for ($idx=0; $idx<count($posts); $idx++) {
          $post = $posts[$idx];
          $post->save();

          $comment = $comments[$idx];
          if (trim($comment)) {
            PostComment::create([
              'post_id' => $post->id,
              'user_id' => 0,
              'comment_text' => $comment,
              'created_id' => 0,
              'updated_at' => $post->created_at,
              'updated_id' => 0,
              'updated_at' => $post->updated_at
            ]);
          }
        }
      });
    }
  }
