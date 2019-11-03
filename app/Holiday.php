<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 祝日モデル
 * @package App
 */
class Holiday extends Model
{
  protected $guarded = array('id');
}
