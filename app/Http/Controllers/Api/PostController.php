<?php

namespace App\Http\Controllers\Api;

use App\Category;
use App\Config;
use App\Post;
use App\PostAttachment;
use App\PostCommentAttachment;
use App\PostResponse;
use App\Quetionnaire;
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
      ->leftJoin('users', function (JoinClause $join) {
        $join->on('posts.updated_id', '=', 'users.id');
      })
      ->select([
        'posts.*',
        'post_responses.read_flg',
        'post_responses.like_flg',
        'post_responses.star_flg',
        'users.name as updated_name'])
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

    Log::info("quetionnaire_selections:" . $request->quetionnaire_selections);
    if ($request->quetionnaire_title && $request->quetionnaire_selections) {
      $quetionnaire = Quetionnaire::create([
        "title" => $request->quetionnaire_title,
        "items" => $request->quetionnaire_selections, // json形式 ["a","b","c"]
        "created_id" => Auth::id(),
        "updated_id" => Auth::id()
      ]);
    }
    Log::info('アンケート');
    Log::info($quetionnaire);
    $post = Post::create([
      "team_id" => Auth::user()->team_id,
      "title" => $request->title,
      "content" => $request->contents,
      "category_id" => $request->category_id,
      "quetionnaire_id" => $quetionnaire? $quetionnaire->id : null,
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
      ->leftJoin('users', 'posts.updated_id', '=', 'users.id')
      ->select(['posts.*', 'users.name as updated_name'])
      ->where('posts.id',$id)
      ->where('posts.team_id',Auth::user()->team_id)
      ->first();
    if (!$post) {// ヒットしない場合は404
      return response()->json(null, 404);
    }

    // 投稿の既読、いいね、スター　(ログインユーザーの行動)
    // 一度INSERTしてからSELECT
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

    //TODO アンケート
    $quetionnaire = null;
    if ($post->quetionnaire_id) {
      $quetionnaire = DB::table('quetionnaires')
        ->where('id', $post->quetionnaire_id)
        ->first();
      if ($quetionnaire) {
        $quetionnaire->items = json_decode($quetionnaire->items);
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
      'quetionnaire' => $quetionnaire,
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
    $post->quetionnaire_id = $request->quetionnaire_id;
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
    $count = Post::destroy($id);
    $result = ["deleted_count" => $count];
    return Response::json($result);
  }
}
