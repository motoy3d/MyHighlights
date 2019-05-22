<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResizeImage;
use App\Jobs\PostNotificationJob;
use App\Post;
use App\PostComment;
use App\PostCommentAttachment;
use App\PostResponse;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Intervention\Image\Facades\Image;

class PostCommentController extends Controller
{
  use ResizeImage;
  /**
   * 投稿に対するコメントをpost_commentテーブルに保存する。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    $post = Post::findOrFail($request->post_id);
    if (!$post || $post->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
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
Log::info("public_path=" . public_path() . ', storage_path=' . storage_path());
    if ($request->allFiles()) { //添付がある場合
//      $allowedfileExtension=['pdf','jpg','jpeg','png','gif','xlsx','docx'];
      $files = $request->file('comment_files');
      foreach ($files as $file) {
        $originalFilename = $file->getClientOriginalName();
//        $extension = $file->getClientOriginalExtension();
//        if (!in_array($extension,$allowedfileExtension)) {
//
//        }
        // ファイル保存
        $filePath = $file->storePublicly('public/comment_attachment');
        // 画像リサイズ
        $extensions = ['jpg','JPG','jpeg','JPEG','png','PNG','gif','GIF','bmp','BMP'];
        if (in_array($file->getClientOriginalExtension(), $extensions)) {
          $this->resizeImage($filePath);
        }

        // URLのために置換
        $filePath = str_replace('public/', 'storage/', $filePath);
        $postCommentAttachment = PostCommentAttachment::create([
          "post_comment_id" => $postCommentResult->id,
          "original_file_name" => $originalFilename,
          "file_path" => $filePath,
          "file_type" => strtolower(substr($originalFilename,strrpos($originalFilename, '.') + 1)),
          "created_id" => Auth::id(),
          "updated_id" => Auth::id()
        ]);
      }
    }

    // コメント数の更新
    $post->comment_count = $post->comment_count + 1;
    $post->save();

    // 通知
    Log::info('コメント通知：' . $request->comment_notification_flg);
    if ($request->comment_notification_flg === 'true' && $request->comment_text) {
      Log::info('コメント通知実行');
      $startTime = microtime(true);
      $fromUser = User::findOrFail(Auth::id());
      $this->dispatch(new PostNotificationJob($fromUser, $post, $postCommentResult));
      $runningTime =  microtime(true) - $startTime;
      Log::info('メール/LINE送信キュー入れ処理時間: ' . $runningTime . ' [s]');
    }
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
    if (!$post || $post->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $postComment = PostComment::findOrFail($request->comment_id);
    if (!$postComment || $postComment->user_id != Auth::id()) { //コメントしたユーザーIDが別の場合は404
      // ヒットしない場合は404
      return response()->json(['message' => 'not found'], 404);
    }
    $count = PostComment::destroy($request->comment_id);
    $post->comment_count = $post->comment_count - 1;
    $post->save();
    return Response::json($count);
  }
}
