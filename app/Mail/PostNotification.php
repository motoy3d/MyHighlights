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

  /**
   * Create a new message instance.
   *
   * @return void
   */
  public function __construct(User $fromUser, $post)
  {
    $this->fromUser = $fromUser;
    $this->post = $post;
  }

  /**
   * Build the message.
   *
   * @return $this
   */
  public function build()
  {
    $team = Team::findOrFail($this->post->team_id);
    return $this
      ->subject('[' . $team->name . '] ' . $this->post->title)
      ->view('emails.post_notification')
      ->with([
        'content' => $this->post->content,
        'app_name' => env('APP_NAME', 'つばさ⬆UP'),
        'app_link' => env('APP_URL')
      ]);
  }
}
