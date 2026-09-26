<?php

namespace App\Support;

use App\Notifications\PushNotice;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 利用者ごとのお知らせの記録(表 notices)。
 *
 * 使うところ
 * - アプリ内のお知らせ一覧(🔔。#125)：届いた通知を後から確認し、目的の画面へ移る
 * - 🔔とホーム画面のアイコンの数(#123/#125)：まだ開いていない通知の数
 * - #110 の「消えた通知から開く」：iPhone でアプリがバックグラウンドのときにタップが届かないので、
 *   前面に戻ったときにバックグラウンドの間に届いた通知を返す(resources/assets/js/deep-link.js)
 *
 * プッシュ通知の宛先の全員に記録する(購読の有無は問わない。スマホの通知を使っていない人も一覧で見られる)。
 * 古い行は、記録するときにその人の KEEP_DAYS より前の分を消す(定期実行の仕組みを増やさない)。
 */
class NoticeLog
{
    /** 「消えた通知から開く」に返す件数 */
    public const RECENT_MAX = 20;

    /** お知らせ一覧に出す件数 */
    public const LIST_MAX = 100;

    /** 記録を残す日数。一覧も数もこれより前の通知は扱わない */
    public const KEEP_DAYS = 30;

    public static function record(User $user, PushNotice $notice, string $type, ?int $teamId): void
    {
        $now = Carbon::now();
        DB::table('notices')->insert([
            'user_id' => $user->id,
            'team_id' => $teamId,
            'type' => $type,
            'nid' => $notice->nid,
            'tag' => $notice->tag,
            'title' => mb_substr($notice->title, 0, 255),
            'body' => mb_substr($notice->body, 0, 500),
            'url' => $notice->url,
            'created_at' => $now->format('Y-m-d H:i:s.v'),
        ]);
        DB::table('notices')
            ->where('user_id', $user->id)
            ->where('created_at', '<', $now->copy()->subDays(self::KEEP_DAYS)->format('Y-m-d H:i:s.v'))
            ->delete();
    }

    /**
     * 最近届いて、まだ開いていない通知(古い順)。アプリがバックグラウンドから戻ったときに、
     * その間に届いた通知を帯で知らせるのに使う(#125 §3.2)。at は届いた時刻のミリ秒。
     * 帯に出すので、題名(チーム名)と本文も返す
     *
     * @return array<int, array{nid: string, tag: string, url: string, team_id: ?int, title: string, body: string, at: int}>
     */
    public static function recent(User $user): array
    {
        return DB::table('notices')
            ->where('user_id', $user->id)
            ->whereNull('opened_at')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::RECENT_MAX)
            ->get(['nid', 'tag', 'url', 'team_id', 'title', 'body', 'created_at'])
            ->reverse()
            ->map(fn ($row) => [
                'nid' => $row->nid,
                'tag' => $row->tag,
                'url' => $row->url,
                'team_id' => $row->team_id === null ? null : (int) $row->team_id,
                'title' => $row->title,
                'body' => $row->body,
                'at' => Carbon::parse($row->created_at)->getTimestampMs(),
            ])
            ->values()
            ->all();
    }

    /**
     * お知らせ一覧(新しい順)
     *
     * @return array<int, array<string, mixed>>
     */
    public static function list(User $user): array
    {
        return DB::table('notices')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now()->subDays(self::KEEP_DAYS)->format('Y-m-d H:i:s.v'))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(self::LIST_MAX)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'nid' => $row->nid,
                'type' => $row->type,
                'team_id' => $row->team_id === null ? null : (int) $row->team_id,
                'title' => $row->title,
                'body' => $row->body,
                'url' => $row->url,
                'opened' => $row->opened_at !== null,
                'created_at' => Carbon::parse($row->created_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * まだ開いていない通知の数。🔔とホーム画面のアイコンの数(#125)。
     * 一覧を開いただけでは減らず、1 件ずつ開く(タップ・その投稿を開く)と減る
     */
    public static function unopenedCount(User $user): int
    {
        return DB::table('notices')
            ->where('user_id', $user->id)
            ->whereNull('opened_at')
            ->where('created_at', '>=', Carbon::now()->subDays(self::KEEP_DAYS)->format('Y-m-d H:i:s.v'))
            ->count();
    }

    /** その通知を開いたにする(本人の分だけ) */
    public static function openByNid(User $user, string $nid): void
    {
        self::markOpened(DB::table('notices')->where('user_id', $user->id)->where('nid', $nid));
    }

    /** その投稿・予定についての通知をまとめて開いたにする(投稿の詳細を開いたとき) */
    public static function openByTag(User $user, string $tag): void
    {
        self::markOpened(DB::table('notices')->where('user_id', $user->id)->where('tag', $tag));
    }

    /** その投稿についてのお知らせを全員の一覧から消す(投稿を削除したとき) */
    public static function forgetTag(string $tag): void
    {
        DB::table('notices')->where('tag', $tag)->delete();
    }

    /** すべて開いたにする */
    public static function openAll(User $user): void
    {
        self::markOpened(DB::table('notices')->where('user_id', $user->id));
    }

    private static function markOpened($query): void
    {
        $query->whereNull('opened_at')->update(['opened_at' => Carbon::now()->format('Y-m-d H:i:s.v')]);
    }
}
