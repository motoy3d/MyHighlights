<?php

namespace App\Support;

use App\Notifications\PushNotice;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 送ったプッシュ通知の記録(push_notices)と、最後にアプリを開いた時刻(users.push_seen_at)。
 *
 * 使うところ
 * - アイコンのバッジ(#123)：まだ見ていないお知らせ = push_seen_at より後に送った通知の数。
 *   送るときに通知に載せ(PushNotice の data.badge)、Service Worker がアイコンに出す
 * - #110 の「消えた通知から開く」：iPhone でアプリがバックグラウンドのときにタップが届かないので、
 *   前面に戻ったときにバックグラウンドの間に送った通知を返し、通知センターから消えたものを開く
 *   (resources/assets/js/deep-link.js)
 *
 * 古い行は、送るときにその人の KEEP_DAYS より前の分を消す(定期実行の仕組みを増やさない)。
 */
class PushNoticeLog
{
    /** 「消えた通知から開く」に返す件数 */
    public const RECENT_MAX = 20;

    /** 記録を残す日数。バッジもこれより前の通知は数えない */
    public const KEEP_DAYS = 30;

    public static function record(User $user, PushNotice $notice): void
    {
        $now = Carbon::now();
        DB::table('push_notices')->insert([
            'user_id' => $user->id,
            'nid' => $notice->nid,
            'tag' => $notice->tag,
            'url' => $notice->url,
            'created_at' => $now->format('Y-m-d H:i:s.v'),
        ]);
        DB::table('push_notices')
            ->where('user_id', $user->id)
            ->where('created_at', '<', $now->copy()->subDays(self::KEEP_DAYS)->format('Y-m-d H:i:s.v'))
            ->delete();
    }

    /**
     * 最近送った通知(古い順)。at は送った時刻のミリ秒
     *
     * @return array<int, array{nid: string, tag: string, url: string, at: int}>
     */
    public static function recent(User $user): array
    {
        return DB::table('push_notices')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::RECENT_MAX)
            ->get(['nid', 'tag', 'url', 'created_at'])
            ->reverse()
            ->map(fn ($row) => [
                'nid' => $row->nid,
                'tag' => $row->tag,
                'url' => $row->url,
                'at' => Carbon::parse($row->created_at)->getTimestampMs(),
            ])
            ->values()
            ->all();
    }

    /** まだ見ていないお知らせの数(最後にアプリを開いた後に送った通知の数) */
    public static function unseenCount(User $user): int
    {
        $seenAt = DB::table('users')->where('id', $user->id)->value('push_seen_at');

        return DB::table('push_notices')
            ->where('user_id', $user->id)
            ->when($seenAt, fn ($q) => $q->where('created_at', '>', $seenAt))
            ->count();
    }

    /** アプリを開いた(お知らせを見た)ことを記録する */
    public static function markSeen(User $user): void
    {
        DB::table('users')->where('id', $user->id)
            ->update(['push_seen_at' => Carbon::now()->format('Y-m-d H:i:s.v')]);
    }
}
