<?php

namespace App\Http\Controllers;

use App\Team;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

/**
 * Class ICalendarController
 * iCal出力コントローラ
 * @package App\Http\Controllers\Api
 */
class ICalendarController extends Controller
{
  /**
   * 指定チームのiCalを返す。
   * @return string icalコンテンツ
   */
  public function make($ical_id)
  {
    $months = env('SCHEDULE_DATA_LOADING_MONTHS', 6);
    $month = date('Ym');
    $fromDate = Carbon::createFromFormat('Ymd', $month . '01')
      ->addMonths($months * -1)->setTime(0, 0);  //nヶ月前の1日
    $toDate = Carbon::createFromFormat('Ymd', $month . '01')
      ->addMonths($months + 1)->addDays(-1)->setTime(0, 0);  //nヶ月後の月末
    Log::info("$fromDate - $toDate");

    $calendar = new \Eluceo\iCal\Component\Calendar('tsubasa.smartj.mobi');

    $team = DB::table('teams')
      ->where('teams.ical_id', $ical_id)
      ->first();
    if (!$team) {
      return null;
    }
    $calendar->setName($team->name);
    $schedules = DB::table('schedules')
      ->select(['schedules.*'])
      ->where('schedules.team_id', '=', $team->id)
      ->whereBetween('schedules.schedule_date', [$fromDate, $toDate])
      ->orderBy('schedules.schedule_date')
      ->orderBy('schedules.time_from')
      ->get();

    // iCal作成
    foreach($schedules as $schedule) {
      $event = new \Eluceo\iCal\Component\Event();
      $event->setUseTimezone(true);
      $event
        ->setDtStart(new Carbon($schedule->schedule_date))
        ->setDtEnd(new Carbon($schedule->schedule_date))
        ->setNoTime(true)
        ->setSummary($schedule->title)
      ;
      $calendar->addComponent($event);
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $team->name . '.ics"');
    return $calendar->render();
  }

  /**
   * 指定チームのiCalを返す。
   * @return JsonResponse
   */
  public function getConfig()
  {
    $team = Team::findOrFail(Auth::user()->team_id);
    if (!$team) {
      return response()->json(['message' => 'not found',], 404);
    }
    return Response::json(['ical_url' => env('APP_URL') . '/ical/' . $team->ical_id]);
  }
}
