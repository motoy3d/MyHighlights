<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PostNotificationJob;
use App\Jobs\ScheduleNotificationJob;
use App\Schedule;
use App\ScheduleComment;
use App\ScheduleCommentAttachment;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class ScheduleCommentController extends Controller
{

  /**
   * 指定スケジュールのコメントリストを返す
   * @param $schedule_id
   * @return \Illuminate\Http\JsonResponse
   */
  public function show($schedule_id) {
    $schedule = Schedule::findOrFail($schedule_id);
    if (!$schedule || $schedule->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      return response()->json(['message' => 'not found',], 404);
    }
    $scheduleComments = DB::table('schedule_comments')
      ->leftJoin('users as create_user',
        'schedule_comments.created_id', '=', 'create_user.id')
      ->select(['schedule_comments.*', 'create_user.name as created_name'])
      ->where('schedule_comments.schedule_id', $schedule_id)
      ->orderBy('schedule_comments.created_at', 'desc')
      ->get();
    return Response::json($scheduleComments);
  }

  /**
   * 予定に対するコメントをschedule_commentテーブルに保存する。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request, $schedule_id)
  {
    $schedule = Schedule::findOrFail($schedule_id);
    if (!$schedule || $schedule->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
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

    // 未実装
//    if ($request->allFiles()) { // 添付がある場合
//      $files = $request->file('comment_files');
//      foreach ($files as $file) {
//        $originalFilename = $file->getClientOriginalName();
//        // ファイル保存
//        $filePath = $file->storePublicly('public/schedule_comment_attachment');
//        // URLのために置換
//        $filePath = str_replace('public/', 'storage/', $filePath);
//        $scheduleCommentAttachment = ScheduleCommentAttachment::create([
//          "schedule_comment_id" => $scheduleCommentResult->id,
//          "original_file_name" => $originalFilename,
//          "file_path" => $filePath,
//          "file_type" => strtolower(substr($originalFilename,strrpos($originalFilename, '.') + 1)),
//          "created_id" => Auth::id(),
//          "updated_id" => Auth::id()
//        ]);
//      }
//    }
    $schedule->save();
    // 通知
    Log::info('コメント通知：' . $request->comment_notification_flg);
    if ($request->comment_notification_flg === 'true' && $request->comment_text) {
      Log::info('コメント通知実行');
      $startTime = microtime(true);
      $fromUser = User::findOrFail(Auth::id());
      $this->dispatch(new ScheduleNotificationJob($fromUser, $schedule, $scheduleCommentResult));
      $runningTime =  microtime(true) - $startTime;
      Log::info('メール/LINE送信キュー入れ処理時間: ' . $runningTime . ' [s]');
    }
    return Response::json($scheduleCommentResult);
  }

  /**
   * 予定に対するコメントをschedule_commentテーブルから削除する。
   *
   * @param $schedule_id
   * @param $comment_id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($schedule_id, $comment_id)
  {
    $schedule = Schedule::findOrFail($schedule_id);
    if (!$schedule || $schedule->team_id != Cookie::get('current_team_id')) { //チームIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $scheduleComment = ScheduleComment::findOrFail($comment_id);
    if (!$scheduleComment || $scheduleComment->user_id != Auth::id()) { //コメントしたユーザーIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $count = ScheduleComment::destroy($comment_id);
    return Response::json($count);
  }
}
