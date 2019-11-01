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
   * Create a new message instance.
   *
   * @return void
   */
  public function __construct($fromMember, $title, $content, $team)
  {
    $this->fromMember = $fromMember;
    $this->title = $title;
    $this->content = $content;
    $this->team = $team;
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
        'app_name' => env('APP_NAME', 'Tsubasa⬆︎UP'),
        'app_link' => env('APP_URL', 'https://tsubasa.smartj.mobi')
      ]);
  }
}
