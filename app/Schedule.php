<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 予定テーブルのモデル
 * @package App
 */
class Schedule extends Model
{
  use HasFactory;

  protected $guarded = array('id');
}
