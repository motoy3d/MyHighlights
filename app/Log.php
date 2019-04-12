<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
  protected $fillable = [
    'log_timestamp',
    'level',
    'content',
    'user_id',
    'ipaddress',
    'user_agent',
    'error_message',
    'created_id',
    'updated_id',
  ];
}
