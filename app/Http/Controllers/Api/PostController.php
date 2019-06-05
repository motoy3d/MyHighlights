<?php

namespace App\Http\Controllers\Api;

use App\Category;
use App\Http\Controllers\ResizeImage;
use App\Jobs\PostNotificationJob;
use App\Post;
use App\PostAttachment;
use App\PostResponse;
use App\Questionnaire;
use App\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * Class PostController
 * 投稿データのAPI。
 * @package App\Http\Controllers\Api
 */
class PostController extends Controller
{
  use ResizeImage;
  /**
   * ユーザーのチームの投稿リストを返す。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
//    Log::info("session lifetime=" . \Illuminate\Support\Facades\Config::get('session.lifetime'));
    $perPageCount = env('TIMELINE_LOAD_POSTS', 10);  //1ページあたりの件数
//    Log::info('★perPageCount=' . $perPageCount . ', ' . env('TIMELINE_LOAD_POSTS'));
    Log::info("PostController#index");
    $teamId = Cookie::get('current_team_id');
    $posts = DB::table('posts')
      ->leftJoin('post_responses', function (JoinClause $join) {
        $join->on('posts.id', '=', 'post_responses.post_id');
        $join->where('post_responses.user_id', '=', Auth::id());
      })
      ->leftJoin('users as create_user', function (JoinClause $join) {
        $join->on('posts.created_id', '=', 'create_user.id');
      })
      ->leftJoin('users as update_user', function (JoinClause $join) {
        $join->on('posts.updated_id', '=', 'update_user.id');
      })
      ->select([
        'posts.*',
        'post_responses.read_flg',
        'post_responses.like_flg',
        'post_responses.star_flg',
        'create_user.name as created_name',
        'update_user.name as updated_name'])
      ->where('posts.team_id', $teamId)
      ->orderByDesc('posts.updated_at');

    // キーワード検索
    $keyword = $request->keyword;
    if ($keyword) { //キーワード検索パラメータがある場合、タイトルと本文から検索する //TODO コメントも検索
      $posts = $posts
        ->where(function($query) use($keyword) {
          $query->where('posts.title', 'LIKE', '%' . $keyword . '%')
            ->orWhere('posts.content', 'LIKE', '%' . $keyword . '%');
        });
    }
    // カテゴリー検索
    $categoryId = $request->category;
    if ($categoryId) {
      Log::info('★カテゴリー=' . $categoryId);
      $posts = $posts
        ->where('category_id', $categoryId);
    }
    // 未読検索
    $unread = $request->unread;
    if ($unread) {
      $posts = $posts
        ->whereNull('post_responses.read_flg');
    }

    // 未読数取得
    $unreadCount = DB::table('posts')
      ->whereRaw('id not in (select post_id from post_responses pr '
          . 'where pr.user_id=? and pr.read_flg = 1)', [Auth::id()])
      ->where('team_id', $teamId)
      ->whereRaw('created_at > (select created_at from members where user_id=? and team_id=?)',
          [Auth::id(), $teamId])
      ->count();
    $posts = $posts->simplePaginate($perPageCount);
    return Response::json(['posts' => $posts, 'unreadCount' => $unreadCount]);
  }

  /**
   * 新規登録画面を表示する時に必要なデータを返す
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function create()
  {
    $categories = Category::where('team_id', Cookie::get('current_team_id'))
      ->select(['id', 'name', 'order_no'])
      ->orderBy('order_no')->get();
    return Response::json(["categories" => $categories]);
  }

  /**
   * 新規投稿の登録。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    //TODO validate

    // アンケート登録
    $questionnaire = null;
    Log::info("questionnaire_selections:" . $request->questionnaire_selections);
    if ($request->questionnaire_title && $request->questionnaire_selections) {
      $selectionsTmp = json_decode($request->questionnaire_selections);
      $selections = [];
      foreach ($selectionsTmp as $item) {
        if ($item->text != '') {
          array_push($selections, $item);
        }
      }
      if (0 < count($selections)) {
        $questionnaire = Questionnaire::create([
          "title" => $request->questionnaire_title,
          "items" => json_encode($selections), // json形式 [{"text":"質問1"},{"text":"質問2"}]
          "created_id" => Auth::id(),
          "updated_id" => Auth::id()
        ]);
        Log::info('アンケート');
        Log::info($questionnaire);
      }
    }
    // 投稿の登録
    $post = Post::create([
      "team_id" => Cookie::get('current_team_id'),
      "title" => $request->title,
      "content" => $request->contents,
      "category_id" => $request->category_id,
      "questionnaire_id" => $questionnaire? $questionnaire->id : null,
      "notification_flg" => $request->notification_flg == 1? true : false,
      "created_id" => Auth::id(),
      "updated_id" => Auth::id()
    ]);
    // 添付ファイルの登録
    $this->saveAttachment($request, $post);
    // メール配信
    $this->sendMailAndLINE($post);

    return Response::json($post);
  }

  /**
   * id指定で１つの投稿を返す。
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($id)
  {
    // 投稿
    $post = DB::table('posts')
      ->leftJoin('users as create_user',
        'posts.created_id', '=', 'create_user.id')
      ->leftJoin('users as update_user',
        'posts.updated_id', '=', 'update_user.id')
      ->leftJoin('categories',
        'posts.category_id', '=', 'categories.id')
      ->select(['posts.*', 'create_user.name as created_name',
        'update_user.name as updated_name', 'categories.name as category_name'])
      ->where('posts.id',$id)
      ->where('posts.team_id', Cookie::get('current_team_id'))
      ->first();
    if (!$post) {// ヒットしない場合は404
      return response()->json(null, 404);
    }

    // 投稿の既読、いいね、スター　(ログインユーザーの行動)
    // 一度INSERTしてからSELECT
    // TODO firstOrNewでリファクタ
    $post_response = DB::table('post_responses')
      ->select('read_flg', 'like_flg', 'star_flg')
      ->where('user_id', Auth::id())
      ->where('post_id', $post->id)
      ->first();
    if (!$post_response) { //初回表示時はINSERT
      $post_response = PostResponse::create([
        "user_id" => Auth::id(),
        "post_id" => $id,
        "read_flg" => true,
        "like_flg" => false,
        "star_flg" => false,
        "created_id" => Auth::id(),
        "updated_id" => Auth::id()
      ]);
      $post_response = DB::table('post_responses')
        ->select('read_flg', 'like_flg', 'star_flg')
        ->where('user_id', Auth::id())
        ->where('post_id', $post->id)
        ->first();
    }

    // いいねリスト
    $likes = DB::table('post_responses')
      ->where('post_id', $id)->where('like_flg', 1)
      ->get();

    // 投稿添付ファイル
    $post_attachments = DB::table('post_attachments')
      ->where('post_id', $post->id)
      ->orderBy('id')
      ->get();

    // アンケート
    $questionnaire = null;
    if ($post->questionnaire_id) {
      $questionnaire = DB::table('questionnaires')
        ->where('id', $post->questionnaire_id)
        ->first();
      if ($questionnaire) {
        $questionnaire->items = json_decode($questionnaire->items);

        // ログインユーザーの回答
        $myAnswers = DB::table('questionnaire_answers')
          ->where('questionnaire_id', $post->questionnaire_id)
          ->where('user_id', Auth::id())
          ->orderBy('question_no')
          ->get();

        // $questionnaire->itemsは設問(選択肢)リスト
        for ($i=0; $i<count($questionnaire->items); $i++) {
          // 回答集計
          $answers = DB::table('questionnaire_answers')
            ->leftJoin('users',
              'questionnaire_answers.user_id', '=', 'users.id')
            ->select([
              'questionnaire_answers.answer',
              'users.name'
            ])
            ->where('questionnaire_id', $post->questionnaire_id)
            ->where('question_no', $i)
            ->orderBy('answer')->orderBy('questionnaire_answers.created_at')
            ->get();
          Log::info('回答');
          Log::info(json_encode($answers));
          $question = $questionnaire->items[$i];
          $answerCounts = ['◯' => 0, '△' => 0, '✕' => 0];
          foreach ($answers as $answer) {
            $answerCounts[$answer->answer] = $answerCounts[$answer->answer] + 1;
          }
          $question->usersAnswers = $answers;
          $question->answerCounts = $answerCounts;
          foreach ($myAnswers as $a) {
            if ($i == $a->question_no){
              $question->myAnswer = $a->answer;
              break;
            }
          }
        }
      }
    }

    // コメント&コメント添付
    $comments = DB::table('post_comments')
      ->leftJoin('users', 'post_comments.user_id', '=', 'users.id')
      ->leftJoin('members', function($join) {
        $join->on('post_comments.user_id', '=', 'members.user_id')
        ->where('team_id', Cookie::get('current_team_id'));
      })
      ->select(
        'post_comments.id', 'post_comments.comment_text', 'post_comments.created_at',
        'like_user_ids', 'post_comments.user_id', 'users.name', 'members.prof_img_filename')
      ->where('post_id', $post->id)
      ->orderByDesc('post_comments.created_at')
      ->get();
    for ($i=0; $i<count($comments); $i++) {
      $postCommentId = $comments[$i]->id;
      $commentAttachments = DB::table('post_comment_attachments')
        ->where('post_comment_id', '=', $postCommentId)
        ->get();
      $comments[$i]->attachments = $commentAttachments;
    }

    // カテゴリー一覧
    $categories = Category::where('team_id', Cookie::get('current_team_id'))
      ->select(['id', 'name', 'order_no'])
      ->orderBy('order_no')->get();

    //TODO コメントへのいいねリスト

    // １つにまとめる
    return Response::json([
      'post' => $post,
      'post_responses' => $post_response? $post_response : [],
      'post_attachments' => $post_attachments,
      'questionnaire' => $questionnaire,
      'comments' => $comments,
      'likes' => $likes,
      'categories' => $categories,
      'user' => Auth::user()
    ]);
  }

  /**
   * id指定で１つの投稿の更新。
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function update(Request $request, $id)
  {
    $post = Post::findOrFail($id);
    if (!$post || $post->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      return response()->json(null, 404);
    }

    //TODO validate
    $questionnaire = null;
    Log::info("added_questionnaire_selections:" . $request->added_questionnaire_selections);
    if ($request->added_questionnaire_selections && $request->added_questionnaire_selections != 'null') {
      $questionnaire = Questionnaire::find($post->questionnaire_id);
      // 追加の選択肢を結合
      $questionnaire->items = json_encode(
        array_merge(json_decode($questionnaire->items), json_decode($request->added_questionnaire_selections))
      );
      $questionnaire->updated_id = Auth::id();
      $count = $questionnaire->save();
      Log::info('アンケート更新:' . $count);
    }

    $post->title = $request->title;
    $post->content = $request->contents;
    $post->category_id = $request->category_id;
    $post->notification_flg = $request->notification_flg;
    $post->updated_id = Auth::id();
    $post->save();

    $this->saveAttachment($request, $post);
    return Response::json($post);
  }

  /**
   * 投稿を削除。
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    $post = Post::findOrFail($id);
    if (!$post || $post->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      return response()->json(null, 404);
    }
    // TODO 削除権限どうするか。投稿者＋管理者？
    DB::transaction(function () use ($post) {
      $count = Post::destroy($post->id);
      $result = ["deleted_count" => $count];
      // 関連テーブルデータ削除
      DB::table('post_attachments')->where('post_id', $post->id)->delete();
      DB::table('post_comments')->where('post_id', $post->id)->delete();
//TODO      DB::table('post_comment_attachments')->where('post_id', $post->id)->delete();
      DB::table('post_responses')->where('post_id', $post->id)->delete();
      if ($post->questionnaire_id != 0) {
        $questionnaire = Questionnaire::findOrFail($post->questionnaire_id);
        DB::table('questionnaire_answers')->where('questionnaire_id', $questionnaire->id)->delete();
        $questionnaire->delete();
      }

      //TODO post_attachmentsの添付ファイル実体削除
      //TODO post_comment_attachmentsの添付ファイル実体削除

      return Response::json($result);
    });
  }

  /**
   * 投稿検索画面を表示するときに必要なデータを返す。
   * @return \Illuminate\Http\JsonResponse
   */
  public function searchInit() {
    $categories = Category::where('team_id', Cookie::get('current_team_id'))
      ->select(['id', 'name', 'order_no'])
      ->orderBy('order_no')->get();
    return Response::json(["categories" => $categories]);
  }

  /**
   * 添付ファイルを保存する。
   * @param Request $request
   * @param $post
   */
  private function saveAttachment(Request $request, $post): void
  {
    if ($request->allFiles()) { //添付がある場合
      $files = $request->file('files');
      foreach ($files as $file) {
        $originalFilename = $file->getClientOriginalName();
        // ファイル保存
        $filePath = $file->storePublicly('public/post_attachment');
        // 画像リサイズ
        $extensions = ['jpg','JPG','jpeg','JPEG','png','PNG','gif','GIF','bmp','BMP'];
        if (in_array($file->getClientOriginalExtension(), $extensions)) {
          $this->resizeImage($filePath);
        }
        // URLのために置換
        $filePath = str_replace('public/', 'storage/', $filePath);
        Log::info("saveAttachmnt");
        Log::info($post);
        $postAttachment = PostAttachment::create([
          "post_id" => $post->id,
          "original_file_name" => $originalFilename,
          "file_path" => $filePath,
          "file_type" => substr($originalFilename, strrpos($originalFilename, '.') + 1),
          "created_id" => Auth::id(),
          "updated_id" => Auth::id()
        ]);
      }
    }
  }

  /**
   * 投稿時の通知メールまたはLINEをグループ内の各メンバーに送る。（通知設定オンのメンバーのみ）
   * ここではジョブの登録のみ行う。通知の処理実行はサーバ側で起動したジョブ実行ワーカーが行う。
   * sudo php artisan queue:work --timeout=3600 --sleep=120
   * @param $post
   */
  private function sendMailAndLINE($post) {
    $startTime = microtime(true);
    $fromUser = User::findOrFail(Auth::id());
    $this->dispatch(new PostNotificationJob($fromUser, $post, null));
    $runningTime =  microtime(true) - $startTime;
    Log::info('メール/LINE送信キュー入れ処理時間: ' . $runningTime . ' [s]');
  }

}
