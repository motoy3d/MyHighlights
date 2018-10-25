<?php

namespace App\Http\Controllers\Api;

use App\Category;
use App\Config;
use App\Post;
use App\PostAttachment;
use App\PostComment;
use App\PostCommentAttachment;
use App\PostResponse;
use App\Questionnaire;
use App\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
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
  /**
   * ユーザーのチームの投稿リストを返す。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    Log::info("session lifetime=" . \Illuminate\Support\Facades\Config::get('session.lifetime'));
    $perPageCount = env('TIMELINE_LOAD_POSTS', 10);  //1ページあたりの件数
    Log::info("PostController#index");
    Log::info("team_id=" . Auth::user());
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
      ->where('posts.team_id', Auth::user()->team_id)
      ->orderByDesc('posts.updated_at');
    $keyword = $request->keyword;
    if ($keyword) { //キーワード検索パラメータがある場合、タイトルと本文から検索する
      $posts = $posts
        ->where(function($query) use($keyword) {
          $query->where('posts.title', 'LIKE', '%' . $keyword . '%')
            ->orWhere('posts.content', 'LIKE', '%' . $keyword . '%');
        });
    }
    $posts = $posts->simplePaginate($perPageCount);
    return Response::json($posts);
  }

  /**
   * 新規登録画面を表示する時に必要なデータを返す
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function create()
  {
    $categories = Category::all()->sortBy('order_no');
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
    // アンケート
    $questionnaire = null;
    Log::info("questionnaire_selections:" . $request->questionnaire_selections);
    if ($request->questionnaire_title && $request->questionnaire_selections) {
      $questionnaire = Questionnaire::create([
        "title" => $request->questionnaire_title,
        "items" => $request->questionnaire_selections, // json形式 [{"text":"質問1"},{"text":"質問2"}]
        "created_id" => Auth::id(),
        "updated_id" => Auth::id()
      ]);
      Log::info('アンケート');
      Log::info($questionnaire);
    }
    $post = Post::create([
      "team_id" => Auth::user()->team_id,
      "title" => $request->title,
      "content" => $request->contents,
      "category_id" => $request->category_id,
      "questionnaire_id" => $questionnaire? $questionnaire->id : null,
      "notification_flg" => $request->notification_flg == 1? true : false,
      "created_id" => Auth::id(),
      "updated_id" => Auth::id()
    ]);
    if ($request->allFiles()) { //添付がある場合
      $files = $request->file('files');
      foreach ($files as $file) {
        $originalFilename = $file->getClientOriginalName();
        // ファイル保存
        $filePath = $file->storePublicly('public/post_attachment');
        // URLのために置換
        $filePath = str_replace('public/', 'storage/', $filePath);
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
      ->select(['posts.*', 'create_user.name as created_name', 'update_user.name as updated_name'])
      ->where('posts.id',$id)
      ->where('posts.team_id',Auth::user()->team_id)
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

        for ($i=0; $i<count($questionnaire->items); $i++) {
          // 回答集計
          $answerCounts = DB::table('questionnaire_answers')
            ->select(DB::raw('count(*) as answer_count, answer'))
            ->where('questionnaire_id', $post->questionnaire_id)
            ->where('question_no', $i)
            ->groupBy('answer')
            ->get();
          Log::info('回答');
          Log::info(json_encode($answerCounts));
          $question = $questionnaire->items[$i];
          foreach ($answerCounts as $answerCount) {
            $answer = $answerCount->answer;
            $question->$answer = $answerCount->answer_count;
          }
          foreach ($myAnswers as $a) {
            if ($i == $a->question_no){
              $question->myAnswer = $a->answer;break;
            };
          }
        }
      }
    }

    // コメント&コメント添付
    $comments = DB::table('post_comments')
      ->leftJoin('users', 'post_comments.user_id', '=', 'users.id')
      ->select(
        'post_comments.id', 'post_comments.comment_text', 'post_comments.created_at',
//        'post_comment_attachments.original_file_name',
//        'post_comment_attachments.file_path',
        'like_user_ids', 'post_comments.user_id', 'users.name')
      ->where('post_id', $post->id)
      ->orderByDesc('post_comments.id')
      ->get();
    for ($i=0; $i<count($comments); $i++) {
      $postCommentId = $comments[$i]->id;
      $commentAttachments = DB::table('post_comment_attachments')
        ->where('post_comment_id', '=', $postCommentId)
        ->get();
      $comments[$i]->attachments = $commentAttachments;
    }

    //TODO コメントへのいいねリスト

    // １つにまとめる
    return Response::json([
      'post' => $post,
      'post_responses' => $post_response? $post_response : [],
      'post_attachments' => $post_attachments,
      'questionnaire' => $questionnaire,
      'comments' => $comments,
      'likes' => $likes,
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
    //TODO validate
    $post = Post::findOrFail($id);
    if (!$post || $post->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      return response()->json(null, 404);
    }

    $post->title = $request->title;
    $post->content = $request->contents;
    $post->category_id = $request->category_id;
    $post->questionnaire_id = $request->questionnaire_id;
    $post->notification_flg = $request->notification_flg;
    $post->updated_id = Auth::id();
    $post = $post->save();
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
    if (!$post || $post->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
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
}
