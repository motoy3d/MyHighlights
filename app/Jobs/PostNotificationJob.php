<?php

namespace App\Jobs;

use App\Mail\PostNotification;
use App\Member;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PostNotificationJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 3600; // タイムアウト設定１時間
  public $fromUser;
  public $post;

  /**
   * Create a new job instance.
   *
   * @return void
   */
  public function __construct($fromUser, $post)
  {
    $this->fromUser = $fromUser;
    $this->post = $post;
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    $startTime = microtime(true);
    // 投稿のチームに所属していて、退会していなくて、メール通知オンのユーザーのアドレスリスト取得
    $users = Member::select(['users.email'])
      ->join('users', 'users.id', '=', 'members.user_id')
      ->where('members.team_id', $this->post->team_id)
      ->whereNull('members.withdrawal_date')
      ->whereNull('users.withdrawal_date')
      ->where('users.mail_notification_flg', 1)
      ->get();
    Log::info('メール送信開始 ' . count($users) . '件');
    $i = 1;
    foreach ($users as $user) {
      Log::info('メール送信(' . $i++ . '/' . count($users) . ') ' . $user->email);
      try {
        if (!$user->email) {
          Log::info('メールアドレスなし.ユーザーID=' . $user->id);
          continue;
        }
        Mail::to($user->email)->send(new PostNotification($this->fromUser, $this->post));
        sleep(2);
      } catch(Exception $ex) {
        Log::error('メール送信エラー: ' . $ex->getMessage());
      }
    }
    $runningTime =  microtime(true) - $startTime;
    Log::info('メール送信処理時間: ' . $runningTime . ' [s]');
  }
}
