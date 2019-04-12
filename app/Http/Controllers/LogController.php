<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LogController extends Controller
{
  /**
   * POST /logging
   *
   * @param Request $request
   * @return
   */
  public function store(Request $request)
  {
    $path = $request->path();
    $this->storeLogs($path);
    return;
  }
  /**
   * ログを保存する
   *
   * @param string string $content
   * @return Log $log
   */
  public function storeLogs($content)
  {
    $log = Log::create([
      'log_timestamp' => DB::raw('now()'),
      'level' => 'info',
      'user_agent' => $_SERVER['HTTP_USER_AGENT'],
      'ipaddress' => $_SERVER['REMOTE_ADDR'],
      'content' => $content,
      'user_id' => Auth::id(),
      'created_id' => Auth::id(),
      'updated_id' => Auth::id()
    ]);
    return $log;
  }
}
