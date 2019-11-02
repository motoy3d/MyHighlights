<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Post;
use App\PostComment;
use App\PostCommentResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class PostCommentResponseController extends Controller
{
  /**
   * 投稿コメントに対するいいねのオンオフをpost_comment_responsesテーブルに保存する。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    $postComment = PostComment::findOrFail($request->post_comment_id);
    $post = Post::findOrFail($postComment->post_id);
    if (!$post || $post->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $postCommentResponseResult = null;
    $postCommentResponse = DB::table('post_comment_responses')
      ->where('user_id', '=', Auth::id())
      ->where('post_comment_id', '=', $request->post_comment_id)
      ->first();
    if ($postCommentResponse) {
      $postCommentResponseModel = PostCommentResponse::findOrFail($postCommentResponse->id);
      if (isset($request->like_flg)) { //いいね
        $postCommentResponseModel->like_flg = $request->like_flg;
        $postCommentResponseModel->save();
      }
      $postCommentResponseResult = $postCommentResponseModel;
    } else {
      if (isset($request->like_flg)) {
        $postCommentResponseResult = PostCommentResponse::create([
          "user_id" => Auth::id(),
          "post_comment_id" => $request->post_comment_id,
          "read_flg" => true,
          "like_flg" => $request->like_flg? true : false,
          "created_id" => Auth::id(),
          "updated_id" => Auth::id()
        ]);
      }
    }
    // コメントへのいいね数の更新
    $postComment->like_count = $postComment->like_count + ($request->like_flg? 1 : -1);
    $postComment->save();

    return Response::json($postCommentResponseResult);
  }
}
