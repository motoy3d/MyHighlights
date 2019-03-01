<?php

namespace App\Mail;

use App\Team;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class PostNotification extends Mailable
{
  use Queueable, SerializesModels;

  public $fromUser;
  public $post;
  public $team;

  /**
   * Create a new message instance.
   *
   * @return void
   */
  public function __construct(User $fromUser, $post, $team)
  {
    $this->fromUser = $fromUser;
    $this->post = $post;
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
      ->subject($this->post->title . ' (' . $this->team->name . ')')
      ->text('emails.post_notification')
      ->with([
        'content' => $this->post->content,
        'app_name' => env('APP_NAME', 'Tsubasa↑UP'),
        'app_link' => env('APP_URL', 'https://tsubasa.smartj.mobi')
      ]);
  }
}
