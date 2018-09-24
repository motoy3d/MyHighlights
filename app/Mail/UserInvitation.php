<?php

namespace App\Mail;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UserInvitation extends Mailable
{
  use Queueable, SerializesModels;

  public $fromUser;
  public $newUser;
  public $password;

  /**
   * Create a new message instance.
   *
   * @return void
   */
  public function __construct(User $fromUser, User $newUser, $password)
  {
    $this->fromUser = $fromUser;
    $this->newUser = $newUser;
    $this->password = $password;
  }

  /**
   * Build the message.
   *
   * @return $this
   */
  public function build()
  {
    $url = 'https://tsubasa.smartj.mobi/users/register?data=' . $this->password;
    Log::info('build. name=' . $this->fromUser->name);
    return $this
      ->subject($this->fromUser->name . 'さんからマイハイライトへ招待されました。')
      ->view('emails.user_invitation')
      ->with([
        'name' => $this->newUser->name,
        'team_name' => '横浜SCつばさ',
        'invitation_link' => $url
      ]);
  }
}