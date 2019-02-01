<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Auth\Notifications\ResetPassword;

class CustomResetPassword extends Notification
{
  use Queueable;

  public $token;
  /**
   * Create a new notification instance.
   *
   * @param string $token
   * @return void
   */
  public function __construct($token)
  {
    $this->token = $token;
  }

  /**
   * Get the notification's delivery channels.
   *
   * @param  mixed  $notifiable
   * @return array
   */
  public function via($notifiable)
  {
    return ['mail'];
  }

  /**
   * Get the mail representation of the notification.
   *
   * @param  mixed  $notifiable
   * @return \Illuminate\Notifications\Messages\MailMessage
   */
  public function toMail($notifiable)
  {
      return (new MailMessage)
          ->from(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME'))
          ->subject('横浜SCつばさアプリ　パスワード再設定')
          ->line('下のボタンをクリックしてパスワードを再設定してください。')
          ->action('パスワード再設定', url(config('app.url')
            .route('password.reset', $this->token, false)))
          ->line('もし心当たりがない場合は、本メールは破棄してください。');  }

  /**
   * Get the array representation of the notification.
   *
   * @param  mixed  $notifiable
   * @return array
   */
  public function toArray($notifiable)
  {
    return [
    ];
  }
}