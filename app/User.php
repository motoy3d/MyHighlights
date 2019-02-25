<?php

namespace App;

use App\Notifications\CustomResetPassword;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * ユーザーモデル
 * @package App
 */
class User extends Authenticatable
{
  use HasApiTokens, Notifiable;

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = [
    'name', 'name_kana', 'email', 'password', 'team_id', 'member_id', 'created_id', 'updated_id'
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
}
