<?php

namespace App\Http\Controllers\Api;

use App\Post;
use App\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
    Log::info("PostController#index");
    Log::info("team_id=" . Auth::user());
    $posts = DB::table('posts')
      ->leftJoin('post_response', function (JoinClause $join) {
        $join->on('posts.id', '=', 'post_response.post_id');
        $join->where('post_response.user_id', '=', Auth::id());
      })
      ->select(['posts.*', 'post_response.read_flg', 'post_response.like_flg',
        'post_response.star_flg'])
      ->where('posts.team_id', Auth::user()->team_id)
      ->orderByDesc('posts.updated_at')
      ->simplePaginate(2);
    return Response::json($posts);
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
    $post = Post::create([
      "team_id" => Auth::user()->team_id,
      "title" => $request->title,
      "content" => $request->contents,
      "category_id" => $request->category_id,
      "quetionnaire_id" => 0, //TODO アンケート
      "notification_flg" => $request->notification_flg == 1? true : false,
      "created_id" => Auth::id(),
      "updated_id" => Auth::id()
    ]);
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
      ->select(['posts.*', 'users.name as updated_user'])
      ->where('posts.id',$id)
      ->where('posts.team_id',Auth::user()->team_id)
      ->first();
    if (!$post) {
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }

    // 投稿へのいいね、スター
    $post_response = DB::table('post_response')
      ->select('read_flg', 'like_flg', 'star_flg')
      ->where('user_id', Auth::id())
      ->where('post_id', $post->id)
      ->first();

    // 投稿添付ファイル
    $post_attachements = DB::table('post_attachements')
      ->where('post_id', $post->id)
      ->orderBy('id')
      ->get();

    //TODO アンケート
    $quetionnaire = null;
    if ($post->quetionnaire_id) {
      $quetionnaire = DB::table('quetionnaire')
        ->where('id', $post->quetionnaire_id)
        ->first();
    }

    // コメント
    $comments = DB::table('post_comments')
      ->leftJoin('users', 'post_comments.user_id', '=', 'users.id')
//TODO 添付      ->leftJoin('post_comment_attachements', 'post_comments.id', '=', 'post_comment_attachements.post_comment_id')
      ->select('comment_text', 'post_comments.created_at',
        'like_user_ids', 'user_id', 'users.name')
      ->where('post_id', $post->id)
      ->orderByDesc('post_comments.id')
      ->get();

    //TODO コメントへのいいねリスト

    // １つにまとめる
    return Response::json([
      'post' => $post,
      'post_response' => $post_response,
      'post_attachements' => $post_attachements,
      'quetionnaire' => $quetionnaire,
      'comments' => $comments
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
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
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
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $count = Post::destroy($id);
    $result = ["deleted_count" => $count];
    return Response::json($result);
  }
}
