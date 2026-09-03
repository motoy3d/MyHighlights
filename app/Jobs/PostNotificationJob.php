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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 投稿、コメント等の通知メールを送る。
 * Class PostNotificationJob
 * @package App\Jobs
 */
class PostNotificationJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public $timeout = 3600; // タイムアウト設定１時間
  public $fromMember;
  public $post;
  public $postComment;
  public $hasAttachment;

  /**
   * コンストラクタ。投稿と投稿コメントで共用。$postには必ず値が入り、
   * $postCommentには投稿コメント通知の場合のみ値が入る。
   * @param $fromMember
   * @param $post
   * @param $postComment
   * @param $hasAttachment
   */
  public function __construct($fromMember, $post, $postComment, $hasAttachment)
  {
    $this->fromMember = $fromMember;
    $this->post = $post;
    $this->postComment = $postComment;
    $this->hasAttachment = $hasAttachment;
  }

  /**
   * Execute the job.
   *
   * @return void
   */
  public function handle()
  {
    $this->sendMail();
  }

  /**
   * メールを送信する。
   */
  private function sendMail()
  {
    $startTime = microtime(true);
    // 送信先取得（投稿のチームに所属していて、退会していなくて、メール通知オンのユーザーのアドレスリスト取得）
    $mailUsers = Member::select(['users.email'])
      ->join('users', 'users.id', '=', 'members.user_id')
      ->where('members.team_id', $this->post->team_id)
      ->whereNull('members.withdrawal_date')
      ->whereNull('users.withdrawal_date')
      ->where('users.mail_notification_flg', 1)
      ->get();
    Log::info('メール送信開始 ' . count($mailUsers) . '件');
    $team = Team::findOrFail($this->post->team_id);

    // タイトルと本文
    $title = $this->post->title . ($this->postComment? ' へのコメント' : '');
    $content = '';
    if ($this->postComment) {
      $content = $this->fromMember->name . "さんがコメントしました。\n\n" . $this->postComment->comment_text;
    } else {
      $content = $this->fromMember->name . "さんが投稿しました。\n\n" . $this->post->content;
    }
    if ($this->hasAttachment) {
      $content .= PHP_EOL . '(添付あり)';
    }
    Log::info('タイトル：' . $title);

    // 一人ずつ間隔を空けながら送信
    $totalCount = count($mailUsers);
    $no = 1;
    foreach ($mailUsers as $user) {
      Log::info('メール送信(' . $no++ . '/' . $totalCount . ') ' . $user->email);
      try {
        if (!$user->email) {
          Log::info('メールアドレスなし.ユーザーID=' . $user->id);
          continue;
        }
        // メール送信実行
        Mail::to($user->email)->send(
          new PostNotification($this->fromMember, $title, $content, $team));
        sleep(1);
      } catch(\Exception $ex) {
        Log::error('メール送信エラー: ' . $ex->getMessage());
      }
    }
    $runningTime =  microtime(true) - $startTime;
    Log::info('メール送信処理時間: ' . $runningTime . ' [s]');
  }

}
