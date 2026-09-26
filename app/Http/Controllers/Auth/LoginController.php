<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * ログインコントローラ。
 *
 * Laravel 6でIlluminate\Foundation\Auth\AuthenticatesUsersが
 * フレームワークから削除されたため、必要な処理のみを実装している。
 * guestミドルウェアの指定はroutes/web.php側で行う。
 */
class LoginController extends Controller
{
    /**
     * ログイン後の遷移先。
     */
    protected string $redirectTo = '/home';

    /**
     * ロックまでのログイン試行回数。
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * ロックする時間(秒)。
     */
    private const DECAY_SECONDS = 60;

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            $this->username() => 'required|string',
            'password' => 'required|string',
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt($this->credentials($request), $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                $this->username() => [__('auth.failed')],
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        // セッション固定化攻撃対策
        $request->session()->regenerate();

        return redirect()->intended($this->redirectTo);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * ログイン条件にusers.withdrawal_date is nullを追加する。
     *
     * @return array<string, mixed>
     */
    protected function credentials(Request $request): array
    {
        return array_merge(
            $request->only($this->username(), 'password'),
            ['withdrawal_date' => null]
        );
    }

    protected function username(): string
    {
        return 'email';
    }

    /**
     * 短時間に大量のログイン試行があった場合に弾く。
     */
    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            $this->username() => [__('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey($request)),
            ])],
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower((string) $request->input($this->username())) . '|' . $request->ip()
        );
    }
}
