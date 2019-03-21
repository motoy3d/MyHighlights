<?php

namespace App\Jobs;

use App\Mail\PostNotification;
use App\Member;
use App\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ScheduleNotificationJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 3600; // タイムアウト設定１時間
  public $fromUser;
  public $schedule;
  public $scheduleComment;

  /**
   * コンストラクタ。投稿と投稿コメントで共用。$postには必ず値が入り、$postCommentには投稿コメント通知の場合のみ値が入る。
   * @param $fromUser
   * @param $schedule
   * @param $scheduleComment
   */
  public function __construct($fromUser, $schedule, $scheduleComment)
  {
    $this->fromUser = $fromUser;
    $this->schedule = $schedule;
    $this->scheduleComment = $scheduleComment;
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    $startTime = microtime(true);
    // 送信先取得（投稿のチームに所属していて、退会していなくて、メール通知オンのユーザーのアドレスリスト取得）
    $users = Member::select(['users.email'])
      ->join('users', 'users.id', '=', 'members.user_id')
      ->where('members.team_id', $this->schedule->team_id)
      ->whereNull('members.withdrawal_date')
      ->whereNull('users.withdrawal_date')
      ->where('users.mail_notification_flg', 1)
      ->get();
    Log::info('メール送信開始 ' . count($users) . '件');
    $i = 1;
    $team = Team::findOrFail($this->schedule->team_id);

    // タイトルと本文
    $date = Carbon::createFromFormat('Y-m-d', $this->schedule->schedule_date);
    $weekday = array( "日", "月", "火", "水", "木", "金", "土" );
    $title = $date->format('n/j')
      . '(' . $weekday[$date->format("w")] . ') '
      . $this->schedule->title
      . ($this->scheduleComment? ' へのコメント' : '');
    $content = '';
    if ($this->scheduleComment) {
      $content = $this->fromUser->name . "さんが「" . $this->schedule->title . "」にコメントしました。\n\n"
        . $this->scheduleComment->comment_text;
    } else {
      $content = $this->fromUser->name . "さんが予定を登録しました。\n\n" . $this->schedule->title;
    }
    Log::info('タイトル：' . $title);

    // 一人ずつ間隔を空けながら送信
    foreach ($users as $user) {
      Log::info('メール送信(' . $i++ . '/' . count($users) . ') ' . $user->email);
      try {
        if (!$user->email) {
          Log::info('メールアドレスなし.ユーザーID=' . $user->id);
          continue;
        }
        // メール送信実行
        Mail::to($user->email)->send(
          new PostNotification($this->fromUser, $title, $content, $team));
        sleep(2);
      } catch(\Exception $ex) {
        Log::error('メール送信エラー: ' . $ex->getMessage());
      }
    }
    $runningTime =  microtime(true) - $startTime;
    Log::info('メール送信処理時間: ' . $runningTime . ' [s]');
  }
}