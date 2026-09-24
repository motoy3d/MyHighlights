<?php

use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PostAttachmentController;
use App\Http\Controllers\Api\PostCommentController;
use App\Http\Controllers\Api\PostCommentResponseController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PostResponseController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\PushController;
use App\Http\Controllers\Api\QuestionnaireController;
use App\Http\Controllers\Api\ScheduleCommentController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\ICalendarController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| 同一オリジンのSPA(resources/assets/js)から呼ばれる。
| auth:apiのガードはconfig/auth.phpでsanctumドライバに設定しており、
| Sanctumのstateful判定によりセッションクッキーで認証される。
|
*/

Route::middleware(['auth:api', 'team', 'log'])->group(function () {
    Route::get('posts/search_init', [PostController::class, 'searchInit']);
    // editはコントローラに実装が無いため塞ぐ(SPAからも呼んでいない)
    Route::resource('posts', PostController::class)->except(['edit']);
    Route::post('post_responses/{post_id}', [PostResponseController::class, 'store']);
    Route::post('post_comments/{post_id}', [PostCommentController::class, 'store']);
    Route::delete('post_comments/{post_id}/{comment_id}', [PostCommentController::class, 'destroy']);
    Route::post('post_comment_responses/{post_comment_id}', [PostCommentResponseController::class, 'store']);
    Route::delete('post_attachments/{post_attachment_id}', [PostAttachmentController::class, 'destroy']);

    // show/editはコントローラに実装が無いため塞ぐ(SPAからも呼んでいない)
    Route::resource('schedules', ScheduleController::class)->except(['show', 'edit']);
    Route::get('schedule_comments/{schedule_id}', [ScheduleCommentController::class, 'show']);
    Route::post('schedule_comments/{schedule_id}', [ScheduleCommentController::class, 'store']);
    Route::delete('schedule_comments/{schedule_id}/{comment_id}', [ScheduleCommentController::class, 'destroy']);

    Route::get('teams', [TeamController::class, 'show']);

    Route::get('me', [UserController::class, 'getMe']);
    Route::post('users/updateName', [UserController::class, 'updateName']);
    Route::post('users/updateNameKana', [UserController::class, 'updateNameKana']);
    Route::post('users/updateEmail', [UserController::class, 'updateEmail']);
    Route::post('users/updatePassword', [UserController::class, 'updatePassword']);
    Route::post('users/updateMailNotificationFlg', [UserController::class, 'updateMailNotificationFlg']);

    // create/editはコントローラ側がコメントアウトされているため塞ぐ
    Route::resource('members', MemberController::class)->except(['create', 'edit']);
    Route::post('questionnaires/answer', [QuestionnaireController::class, 'store']);
    Route::get('blog', [BlogController::class, 'index']);
    Route::get('ical/config', [ICalendarController::class, 'getConfig'])->name('ical.config');

    // Webプッシュ通知(#110)
    Route::get('push/config', [PushController::class, 'showConfig']);
    Route::put('push/preferences', [PushController::class, 'updatePreferences']);
    Route::post('push/subscriptions', [PushController::class, 'subscribe']);
    Route::delete('push/subscriptions', [PushController::class, 'unsubscribe']);
    Route::post('push/test', [PushController::class, 'test']);
    Route::post('push/recent', [PushController::class, 'recent']);
    // アプリ内のお知らせ一覧(🔔。#125)
    Route::get('notices', [NoticeController::class, 'index']);
    Route::get('notices/unseen', [NoticeController::class, 'unseen']);
    Route::post('notices/seen', [NoticeController::class, 'seen']);
    Route::post('notices/open', [NoticeController::class, 'open']);
});
