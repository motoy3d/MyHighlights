<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * パスワード再設定メール送信コントローラ。
 *
 * Laravel 6でIlluminate\Foundation\Auth\SendsPasswordResetEmailsが
 * フレームワークから削除されたため、必要な処理のみを実装している。
 */
class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('auth.passwords.email');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
