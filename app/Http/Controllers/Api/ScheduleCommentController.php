<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Schedule;
use App\ScheduleComment;
use App\ScheduleCommentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class ScheduleCommentController extends Controller
{
  /**
   * 予定に対するコメントをschedule_commentテーブルに保存する。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    $schedule = Schedule::findOrFail($request->schedule_id);
    if (!$schedule || $schedule->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $scheduleCommentResult = ScheduleComment::create([
      "schedule_id" => $request->schedule_id,
      "user_id" => Auth::id(),
      "comment_text" => $request->comment_text,
      "created_id" => Auth::id(),
      "updated_id" => Auth::id()
    ]);

    if ($request->allFiles()) { // 添付がある場合
      $files = $request->file('comment_files');
      foreach ($files as $file) {
        $originalFilename = $file->getClientOriginalName();
        // ファイル保存
        $filePath = $file->storePublicly('public/schedule_comment_attachment');
        // URLのために置換
        $filePath = str_replace('public/', 'storage/', $filePath);
        $scheduleCommentAttachment = ScheduleCommentAttachment::create([
          "schedule_comment_id" => $scheduleCommentResult->id,
          "original_file_name" => $originalFilename,
          "file_path" => $filePath,
          "file_type" => strtolower(substr($originalFilename,strrpos($originalFilename, '.') + 1)),
          "created_id" => Auth::id(),
          "updated_id" => Auth::id()
        ]);
      }
    }

    // コメント数の更新
    $schedule->comment_count = $schedule->comment_count + 1;
    $schedule->save();
    return Response::json($scheduleCommentResult);
  }

  /**
   * 予定に対するコメントをschedule_commentテーブルから削除する。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy(Request $request)
  {
    $schedule = Schedule::findOrFail($request->schedule_id);
    if (!$schedule || $schedule->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $scheduleComment = ScheduleComment::findOrFail($request->comment_id);
    if (!$scheduleComment || $scheduleComment->user_id != Auth::id()) { //コメントしたユーザーIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $count = ScheduleComment::destroy($request->comment_id);
    $schedule->comment_count = $schedule->comment_count - 1;
    $schedule->save();
    return Response::json($count);
  }
}
