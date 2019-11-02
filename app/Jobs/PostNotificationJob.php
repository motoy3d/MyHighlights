<?php

namespace App\Jobs;

use App\Mail\PostNotification;
use App\Member;
use App\Team;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 投稿、コメント等の通知を行う。通知方法はメールまたはLINE。
 * Class PostNotificationJob
 * @package App\Jobs
 */
class PostNotificationJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  const LINE_NOTIFY_SEND_URL = 'https://notify-api.line.me/api/notify';
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
    $this->sendLINE();
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

  /**
   * LINEメッセージを送信する。
   */
  private function sendLINE()
  {
    $startTime = microtime(true);
    // 送信先取得（投稿のチームに所属していて、退会していなくて、LINE通知オンのユーザーのアドレスリスト取得）
    $lineUsers = Member::select(['users.id', 'line_access_token'])
      ->join('users', 'users.id', '=', 'members.user_id')
      ->where('members.team_id', $this->post->team_id)
      ->whereNull('members.withdrawal_date')
      ->whereNull('users.withdrawal_date')
      ->where('users.line_notification_flg', 1)
      ->get();
    Log::info('★LINE送信開始 ' . count($lineUsers) . '件');
    $team = Team::findOrFail($this->post->team_id);

    // タイトルと本文
    $title = $this->post->title;
    $message = '';
    if ($this->postComment) {
      $message = $this->fromMember->name . "さんが" . '「' . $title . "」にコメントしました。\n\n"
        . $this->postComment->comment_text;
    } else {
      $message = $this->fromMember->name . "さんが投稿しました。\n「" . $title . "」\n" . $this->post->content;
    }
    if ($this->hasAttachment) {
      $message .= PHP_EOL . '(添付あり)';
    }
    Log::info('タイトル：' . $title);

    // 一人ずつ間隔を空けながら送信
    $totalCount = count($lineUsers);
    $no = 1;
    foreach ($lineUsers as $user) {
      Log::info('LINE送信(' . $no++ . '/' . $totalCount . ') ' . $user->id);
      try {
        if (!$user->line_access_token) {
          Log::error('access_tokenなし.ユーザーID=' . $user->id);
          continue;
        }
        // LINE送信実行
//        if (1 < count($user->teams())) {  //複数チームに所属している場合はチーム名を入れる。
//          $message = '[' . $team->name . '] ' . $message;
//        }
        $this->postLINE($user, $message);
        sleep(1);
      } catch(\Exception $ex) {
        Log::error('LINE送信エラー1: ' . $ex->getMessage());
//        // エラー発生したら10秒置いて１回だけリトライ
//        try {
//          sleep(10);
//          $this->postLINE($user, $message);
//        } catch(\Exception $ex) {
//          Log::error('LINE送信エラー2: ' . $ex->getMessage());
//        }
      }
    }
    $runningTime =  microtime(true) - $startTime;
    Log::info('LINE送信処理時間: ' . $runningTime . ' [s]');
  }

  /**
   * LINEへの送信のためにAPIにPOSTする。
   * @param $user
   * @param string $message
   */
  private function postLINE($user, string $message): void
  {
    $client = new Client();
    $client->post(self::LINE_NOTIFY_SEND_URL, [
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Authorization' => 'Bearer ' . $user->line_access_token
      ],
      'form_params' => [
        'message' => $message
      ]
    ]);
  }
}
