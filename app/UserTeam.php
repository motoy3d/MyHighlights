<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * ユーザー・チームモデル
 * @package App
 */
class UserTeam extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'user_id', 'team_id', 'created_id', 'updated_id'
    ];

}
