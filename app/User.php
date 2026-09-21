<?php

namespace App;

use App\Notifications\CustomResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * ユーザーモデル
 * @package App
 */
class User extends Authenticatable
{
  use HasApiTokens, HasFactory, HasPushSubscriptions, Notifiable;

  /**
   * プッシュ通知の種類と既定値(#110)。
   * users.push_prefs がNULLの場合やキーが欠けている場合はこの値を使う。
   */
  public const PUSH_PREF_DEFAULTS = [
    'new_post' => true,           // 新しい投稿
    'comment_on_mine' => true,    // 自分の投稿・自分がコメントした投稿へのコメント
    'comment_on_others' => false, // その他の投稿へのコメント
    'schedule_change' => true,    // 予定の変更・中止
    'schedule_comment' => true,   // 予定へのコメント(届くのは予定を作った人と指導者だけ)
  ];

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'name', 'name_kana', 'email', 'password',
    'mail_notification_flg', 'team_id', 'member_id',
    'created_id', 'updated_id'
  ];

  /**
   * The attributes that should be hidden for arrays.
   *
   * @var array
   */
  protected $hidden = [
      'password', 'remember_token',
  ];

  /**
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'push_prefs' => 'array',
    ];
  }

  /**
   * ユーザーの所属しているチーム一覧を返す
   * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
   */
  public function teams() {
    // https://readouble.com/laravel/5.5/ja/eloquent-relationships.html#has-many-through
    return $this->hasManyThrough('App\Team', 'App\Member',
      'user_id', 'id', 'id', 'team_id')
      ->whereNull('members.withdrawal_date')
      ->orderBy('teams.id');
  }

  /**
   * パスワード再設定メールの送信
   *
   * @param  string  $token
   * @return void
   */
  public function sendPasswordResetNotification($token) {
    $this->notify(new CustomResetPassword($token));
  }

  /**
   * プッシュ通知の種類ごとの設定を、既定値を補って返す。
   * 未知のキー(将来削除した種類など)は返さない。
   *
   * @return array<string, bool>
   */
  public function pushPreferences(): array
  {
    $saved = is_array($this->push_prefs) ? $this->push_prefs : [];
    $prefs = [];
    foreach (self::PUSH_PREF_DEFAULTS as $key => $default) {
      $prefs[$key] = array_key_exists($key, $saved) ? (bool) $saved[$key] : $default;
    }
    return $prefs;
  }

  /**
   * 指定した種類のプッシュ通知を受け取る設定か。
   */
  public function wantsPush(string $key): bool
  {
    return $this->pushPreferences()[$key] ?? false;
  }
}
