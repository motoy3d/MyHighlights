<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * メンバーモデル
 * @package App
 */
class Member extends Model
{
  use SoftDeletes;

  protected $guarded = array('id');
}
