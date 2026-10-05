<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PostNotification extends Mailable
{
  use Queueable, SerializesModels;

  public $fromMember;
  public $title;
  public $content;
  public $team;
  /**
   * 投稿を直接開くリンク(#9)。予定の通知など、リンクが無い場合は null。
   * テキストメールなので、ビューでは & が &amp; にならないようエスケープせずに出している。
   * サーバで組み立てたURL以外を入れないこと。
   */
  public $postLink;

  /**
   * Create a new message instance.
   *
   * @return void
   */
  public function __construct($fromMember, $title, $content, $team, $postLink = null)
  {
    $this->fromMember = $fromMember;
    $this->title = $title;
    $this->content = $content;
    $this->team = $team;
    $this->postLink = $postLink;
  }

  /**
   * Build the message.
   *
   * @return $this
   */
  public function build()
  {
    return $this
      ->subject($this->title . ' (' . $this->team->name . ')')
      ->text('emails.post_notification')
      ->with([
        'content' => $this->content,
        'post_link' => $this->postLink,
        'app_name' => config('app.name'),
        'app_link' => config('app.url')
      ]);
  }
}
