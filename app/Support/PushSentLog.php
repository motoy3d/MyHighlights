<?php

namespace App\Support;

use App\Notifications\PushNotice;
use App\User;
use Illuminate\Support\Facades\Cache;

/**
 * 利用者ごとに、最近送った通知の控え（#110）。
 *
 * iPhone のホーム画面アプリは、バックグラウンドのときに通知をタップしても、タップが Service Worker に届かない
 * （WebKit bug 268797）。そこでアプリは前面に戻ったとき、この控えのうち通知センターから消えたものを
 * タップされた通知とみなして開く（resources/assets/js/deep-link.js）。
 * 控えを端末（Service Worker）で持たないのは、iOS では Service Worker が保存したものをアプリの画面から
 * 読めなかったため（2026-09-22 実機で確認）。
 *
 * 数日で要らなくなる一時的な情報なので、テーブルは作らずキャッシュに置く。
 */
class PushSentLog
{
    public const MAX = 20;

    public const TTL_SECONDS = 3 * 24 * 60 * 60;

    public static function record(User $user, PushNotice $notice): void
    {
        $now = (int) floor(microtime(true) * 1000);
        $list = array_values(array_filter(self::recent($user), fn (array $x) => $x['tag'] !== $notice->tag));
        $list[] = ['tag' => $notice->tag, 'url' => $notice->url, 'at' => $now];
        Cache::put(self::key($user), array_slice($list, -self::MAX), self::TTL_SECONDS);
    }

    /**
     * @return array<int, array{tag: string, url: string, at: int}> 古い順。at はミリ秒
     */
    public static function recent(User $user): array
    {
        $list = Cache::get(self::key($user), []);

        return is_array($list) ? $list : [];
    }

    private static function key(User $user): string
    {
        return 'push-sent:' . $user->id;
    }
}
