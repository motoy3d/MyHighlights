<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ICalendarController;
use App\Http\Controllers\LineNotifyController;
use App\Http\Controllers\QuestionnaireCsvController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| webミドルウェアグループが適用される。認証系のミドルウェアは
| Laravel 11以降コントローラのコンストラクタで指定できなくなったため、
| ここでルート単位に指定している。
|
*/

Route::middleware(['log'])->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'login']);

        // パスワード再設定
        Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])
            ->name('password.request');
        Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
            ->name('password.email');
        Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])
            ->name('password.reset');
        Route::post('password/reset', [ResetPasswordController::class, 'reset']);
    });

    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('withdrawal', WithdrawalController::class)->name('withdrawal');

    Route::middleware('auth')->group(function () {
        Route::get('/', [HomeController::class, 'index']);
        Route::get('/home', [HomeController::class, 'index'])->name('home');
    });

    Route::get('/ical/{ical_id}', [ICalendarController::class, 'make'])->name('ical');

    Route::get('questionnaire_download/{questionnaire_id}', QuestionnaireCsvController::class)
        ->name('questionnaire_download');

    Route::get('goto_line_auth', [LineNotifyController::class, 'redirectToProvider']);
    Route::post('line_auth', [LineNotifyController::class, 'handleProviderCallback']);
    Route::get('line_auth', [LineNotifyController::class, 'authError']);
});
