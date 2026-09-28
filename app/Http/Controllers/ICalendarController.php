<?php

namespace App\Http\Controllers;

use App\Team;
use Carbon\Carbon;
use DateTimeZone;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Entity\TimeZone;
use Eluceo\iCal\Domain\ValueObject\Date as ICalDate;
use Eluceo\iCal\Domain\ValueObject\DateTime as ICalDateTime;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class ICalendarController
 * iCal出力コントローラ
 * @package App\Http\Controllers\Api
 */
class ICalendarController extends Controller
{
    /**
     * 指定チームのiCalを返す。
     */
    public function make(string $ical_id): Response
    {
        $months = (int) config('tsubasa.schedule_data_loading_months');
        $month = date('Ym');
        $fromDate = Carbon::createFromFormat('Ymd', $month . '01')
            ->addMonths($months * -1)->setTime(0, 0);  //nヶ月前の1日
        $toDate = Carbon::createFromFormat('Ymd', $month . '01')
            ->addMonths($months + 1)->addDays(-1)->setTime(0, 0);  //nヶ月後の月末
        Log::info("$fromDate - $toDate");

        $team = DB::table('teams')
            ->where('teams.ical_id', $ical_id)
            ->first();
        if (! $team) {
            return response('', 404);
        }

        $schedules = DB::table('schedules')
            ->select(['schedules.*'])
            ->where('schedules.team_id', '=', $team->id)
            ->whereBetween('schedules.schedule_date', [$fromDate, $toDate])
            ->orderBy('schedules.schedule_date')
            ->orderBy('schedules.time_from')
            ->get();

        $calendar = new Calendar();
        $calendar->setProductIdentifier('tsubasa.smartj.mobi');
        $calendar->setCalName($team->name);

        // 時刻付きの予定はローカルタイムで出力するため、VTIMEZONEを添える
        $timeZone = new DateTimeZone(config('app.timezone'));
        $calendar->addTimeZone(
            TimeZone::createFromPhpDateTimeZone($timeZone, $fromDate, $toDate)
        );

        foreach ($schedules as $schedule) {
            $calendar->addEvent($this->makeEvent($schedule, $timeZone));
        }

        $content = (string) (new CalendarFactory())->createCalendar($calendar);

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $team->name . '.ics"',
        ]);
    }

    /**
     * scheduleレコード1件をiCalのVEVENTに変換する。
     */
    private function makeEvent(object $schedule, DateTimeZone $timeZone): Event
    {
        $event = new Event();
        $event->setSummary((string) $schedule->title);

        // 開始時刻が未設定の予定は終日予定として扱う
        if (! $schedule->time_from) {
            $date = Carbon::parse($schedule->schedule_date, $timeZone);

            return $event->setOccurrence(new SingleDay(new ICalDate($date)));
        }

        $start = Carbon::parse($schedule->schedule_date . ' ' . $schedule->time_from, $timeZone);

        // 終了時刻が未設定の場合は開始時刻と同じにする
        $end = $schedule->time_to
            ? Carbon::parse($schedule->schedule_date . ' ' . $schedule->time_to, $timeZone)
            : $start->copy();

        return $event->setOccurrence(new TimeSpan(
            new ICalDateTime($start, true),
            new ICalDateTime($end, true)
        ));
    }

    /**
     * 現在のチームのiCal購読URLを返す。
     */
    public function getConfig(): JsonResponse
    {
        $team = Team::findOrFail(Cookie::get('current_team_id'));

        return response()->json([
            'ical_url' => config('app.url') . '/ical/' . $team->ical_id,
        ]);
    }
}
