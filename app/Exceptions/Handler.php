<?php

namespace App\Exceptions;

use App\Log;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
      dd($exception);
//ALBを使う場合      $sourceIpKey = env('APP_ENV') == 'production' ? 'HTTP_X_FORWARDED_FOR' : 'REMOTE_ADDR';
      $method = array_key_exists('REQUEST_METHOD', $_SERVER) ? $_SERVER['REQUEST_METHOD'] : '';
      $path = array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : '';
      $content = '';
      if ($path) {
        $content = $method . ' ' . $path;
      }

      Log::create([
        'log_timestamp' => DB::raw('now()'),
        'level' => 'error',
        'user_agent' => array_key_exists('HTTP_USER_AGENT', $_SERVER) ? $_SERVER['HTTP_USER_AGENT'] : '',
        'ipaddress' => array_key_exists('REMOTE_ADDR', $_SERVER) ? $_SERVER['REMOTE_ADDR'] : '',
        'content' => $content,
        'error_message' => $exception->getMessage(),
        'error_trace' => $exception->getTraceAsString(),
        'user_id' => Auth::id(),
        'created_id' => Auth::id(),
        'updated_id' => Auth::id()
      ]);
      parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }
}
