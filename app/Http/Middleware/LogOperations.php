<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Controllers\LogController;

class LogOperations
{
  /**
   * 処理時にログをDBに保存する
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  public function handle($request, Closure $next)
  {
    $response = $next($request);
    $path = $_SERVER['REQUEST_URI'];
    $logCtrl = new LogController();
    $logCtrl->storeLogs($path);
    return $response;
  }
}
