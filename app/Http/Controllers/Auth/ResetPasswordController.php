<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * パスワード再設定コントローラ。
 *
 * Laravel 6でIlluminate\Foundation\Auth\ResetsPasswordsが
 * フレームワークから削除されたため、必要な処理のみを実装している。
 */
class ResetPasswordController extends Controller
{
    /**
     * 再設定後の遷移先。
     */
    protected string $redirectTo = '/home';

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.passwords.reset', [
            'token' => $token,
            'email' => $request->input('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));

                // 再設定後はそのままログイン状態にする(旧ResetsPasswordsと同じ挙動)
                Auth::login($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect($this->redirectTo)->with('status', __($status));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
