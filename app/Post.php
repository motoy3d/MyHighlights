<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 投稿モデル
 * @package App
 */
class Post extends Model
{
  use HasFactory;

  protected $guarded = array('id');
}
