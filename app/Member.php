<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * メンバーモデル
 * @package App
 */
class Member extends Model
{
  use HasFactory, SoftDeletes;

  protected $guarded = array('id');
}
