<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Post;
use App\PostComment;
use App\PostResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class PostCommentController extends Controller
{
  /**
   * 投稿に対するコメントをpost_commentテーブルに保存する。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    $post = Post::findOrFail($request->post_id);
    if (!$post || $post->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $postCommentResult = PostComment::create([
      "post_id" => $request->post_id,
      "user_id" => Auth::id(),
      "comment_text" => $request->comment_text,
      "created_id" => Auth::id(),
      "updated_id" => Auth::id()
    ]);
    $post->comment_count = $post->comment_count + 1;
    $post->save();
    return Response::json($postCommentResult);
  }

  /**
   * 投稿に対するコメントをpost_commentテーブルから削除する。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy(Request $request)
  {
    $post = Post::findOrFail($request->post_id);
    if (!$post || $post->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $postComment = PostComment::findOrFail($request->comment_id);
    if (!$postComment || $postComment->user_id != Auth::id()) { //コメントしたユーザーIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $count = PostComment::destroy($request->comment_id);
    $post->comment_count = $post->comment_count - 1;
    $post->save();
    return Response::json($count);
  }

}
