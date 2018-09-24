<?php

namespace App\Http\Controllers\Api;

use App\Category;
use App\Http\Controllers\Controller;
use App\Schedule;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * Class ScheduleController
 * 予定データのAPI。
 * @package App\Http\Controllers\Api
 */
class ScheduleController extends Controller
{
  /**
   * 指定年月の前後nヶ月分の予定一覧を返す。
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function index(Request $request)
  {
    $months = env('SCHEDULE_DATA_LOADING_MONTHS', 6);
    //TODO validate
    $month = $request->month;
    $fromDate = Carbon::createFromFormat('Ymd', $month . '01')
      ->addMonths($months * -1)->setTime(0, 0);  //nヶ月前の1日
    $toDate = Carbon::createFromFormat('Ymd', $month . '01')
      ->addMonths($months + 1)->addDays(-1)->setTime(0, 0);;  //nヶ月後の月末
    Log::info("$fromDate - $toDate");

    $schedules = DB::table('schedules')
      ->select(['schedules.*'])
      ->where('schedules.team_id', Auth::user()->team_id)
      ->whereBetween('schedules.schedule_date', [$fromDate, $toDate])
      ->orderBy('schedules.schedule_date')
      ->orderBy('schedules.time_from')
      ->get();
    return Response::json($schedules);
  }

  /**
   * 新規登録画面を表示する時に必要なデータを返す
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function create()
  {
    $categories = Category::all(['id', 'name', 'order_no']);
    return Response::json([
      'categories' => $categories
    ]);
  }

  /**
   * 新規スケジュール登録
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    //TODO validate
    $schedule = Schedule::create([
      "team_id" => Auth::user()->team_id,
      "schedule_date" => $request->schedule_date,
      "title" => $request->title,
      "allday_flg" => $request->allday_flg,
      "time_from" => $request->time_from,
      "time_to" => $request->time_to,
      "category_id" => $request->category_id,
      "content" => $request->contents,
      "notification_flg" => $request->notification_flg,
      "created_id" => Auth::id(),
      "updated_id" => Auth::id()
    ]);
    return Response::json($schedule);
  }

  /**
   * スケジュールの更新
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function update(Request $request, $id)
  {
    //TODO validate
    $schedule = Schedule::findOrFail($id);
    if (!$schedule || $schedule->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      // ヒットしない場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }

    $schedule->schedule_date = $request->schedule_date;
    $schedule->title = $request->title;
    $schedule->allday_flg = $request->allday_flg;
    $schedule->time_from = $request->time_from;
    $schedule->time_to = $request->time_to;
    $schedule->category_id = $request->category_id;
    $schedule->content = $request->contents;
    $schedule->notification_flg = $request->notification_flg;
    $schedule->updated_id = Auth::id();
    $schedule = $schedule->save();
    return Response::json($schedule);
  }

  /**
   * スケジュールを削除
   *
   * @param  int  $id
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy($id)
  {
    $schedule = Schedule::findOrFail($id);
    if (!$schedule || $schedule->team_id != Auth::user()->team_id) { //チームIDが別の場合は404
      return response()->json([
        'message' => 'not found',
      ], 404);
    }
    $count = Schedule::destroy($id);
    $result = ["deleted_count" => $count];
    return Response::json($result);
  }
}