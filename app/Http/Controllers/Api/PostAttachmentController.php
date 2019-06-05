<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Post;
use App\PostAttachment;
use App\PostResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class PostAttachmentController extends Controller
{
  /**
   * 添付ファイルの削除
   *
   * @param  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    // 正当性検証
    $postAttachment = PostAttachment::find($id);
    if (!$postAttachment) { // ヒットしない場合は404
      return response()->json(['message' => 'not found'], 404);
    }
    $post = Post::find($postAttachment->post_id);
    if (!$post || $post->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      return response()->json(['message' => 'not found'], 404);
    }

    // ファイルを物理削除後、post_attachmentsテーブルから削除
    $fileDeleted = File::delete($postAttachment->file_path);
    $dbDeleted = PostAttachment::destroy($id);
    Log::info("添付削除:$id ファイル削除($postAttachment->file_path):$fileDeleted  DB削除:$dbDeleted");
    return Response::json($dbDeleted);
  }
}
