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
  public $teamName;
  public $password;

  /**
   * UserInvitation constructor.
   * @param User $fromUser 登録操作をしたユーザー
   * @param User $newUser 追加するユーザー
   * @param $teamName
   * @param $password
   */
  public function __construct(User $fromUser, User $newUser, $teamName, $password)
  {
    $this->fromUser = $fromUser;
    $this->newUser = $newUser;
    $this->teamName = $teamName;
    $this->password = $password;
  }

  /**
   * Build the message.
   *
   * @return $this
   */
  public function build()
  {
    Log::info('build. name=' . $this->fromUser->name);
    $viewName = 'emails.user_invitation';
    if (!$this->password) {
      $viewName = 'emails.user_invitation_add_team';
    }
    return $this
      ->subject($this->fromUser->name .
        'さんから、横浜SCつばさ用アプリ「' . config('app.name') . '」へ招待されました')
      ->view($viewName)
      ->with([
        'name' => $this->newUser->name,
        'app_name' => config('app.name'),
        'team_name' => $this->teamName,
        'site_link' => config('app.url'),
        'email' => $this->newUser->email,
        'password' => $this->password
      ]);
  }
}
