<?php

namespace App\Jobs;

use App\Member;
use App\Notifications\PushNotice;
use App\Post;
use App\PostComment;
use App\Schedule;
use App\ScheduleComment;
use App\Support\PushRecipients;
use App\Support\PushRollout;
use App\Support\NoticeLog;
use App\Team;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Webプッシュ通知を送る(#110)。
 *
 * メール送信ジョブ(PostNotificationJob 等)は1通ごとに待つので、
 * 同じジョブにするとプッシュが遅れる。そのため別のジョブにしている。
 * 送信は WebPushChannel が購読ごとにまとめて送り(flush)、
 * 無効になった購読(404/410)はチャネル側で削除される。
 *
 * ジョブにはIDなどの値だけを持たせ、実行時に読み直す。
 * (削除された予定は読み直せないので、削除時点の内容を snapshot に持たせる)
 */
class PushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const TYPE_NEW_POST = 'new_post';
    public const TYPE_POST_COMMENT = 'post_comment';
    public const TYPE_SCHEDULE_CHANGE = 'schedule_change';
    public const TYPE_SCHEDULE_DELETED = 'schedule_deleted';
    public const TYPE_SCHEDULE_COMMENT = 'schedule_comment';

    public $timeout = 300;

    /**
     * @param  array{id?: int, team_id?: int, title?: string, schedule_date?: string}|null  $snapshot
     *         予定の削除時のみ。削除前の予定の内容
     */
    public function __construct(
        public string $type,
        public int $teamId,
        public int $actorId,
        public ?int $postId = null,
        public ?int $commentId = null,
        public ?int $scheduleId = null,
        public ?array $snapshot = null,
    ) {
        // 未設定(null)なら既定のキュー。config/tsubasa.php の push_queue を参照
        $this->onQueue(config('tsubasa.push_queue'));
    }

    // 生成用 ---------------------------------------------------------

    public static function newPost(Post $post, int $actorId): self
    {
        return new self(self::TYPE_NEW_POST, (int) $post->team_id, $actorId, postId: (int) $post->id);
    }

    public static function postComment(Post $post, PostComment $comment, int $actorId): self
    {
        return new self(self::TYPE_POST_COMMENT, (int) $post->team_id, $actorId,
            postId: (int) $post->id, commentId: (int) $comment->id);
    }

    public static function scheduleChanged(Schedule $schedule, int $actorId): self
    {
        return new self(self::TYPE_SCHEDULE_CHANGE, (int) $schedule->team_id, $actorId,
            scheduleId: (int) $schedule->id);
    }

    /**
     * @param  array{id: int, team_id: int, title: string, schedule_date: string}  $snapshot
     */
    public static function scheduleDeleted(array $snapshot, int $actorId): self
    {
        return new self(self::TYPE_SCHEDULE_DELETED, (int) $snapshot['team_id'], $actorId,
            scheduleId: (int) $snapshot['id'], snapshot: $snapshot);
    }

    public static function scheduleComment(Schedule $schedule, ScheduleComment $comment, int $actorId): self
    {
        return new self(self::TYPE_SCHEDULE_COMMENT, (int) $schedule->team_id, $actorId,
            scheduleId: (int) $schedule->id, commentId: (int) $comment->id);
    }

    // 実行 -----------------------------------------------------------

    public function handle(PushRecipients $recipients): void
    {
        if (PushRollout::isDisabledForEveryone()) {
            return;
        }

        $notice = $this->notice();
        if (! $notice) {
            // 実行までの間に投稿・予定・コメントが削除された
            Log::info("プッシュ通知: 対象が見つからないため送らない type={$this->type}");
            return;
        }

        // 宛先の全員のお知らせ一覧(🔔)に記録する(スマホの通知を使っていない人も一覧で見られる。#125)。
        // 記録してから送るので、通知に載せるアイコンの数(data.badge)にこの通知も含まれる
        $users = $this->recipients($recipients);
        foreach ($users as $user) {
            try {
                NoticeLog::record($user, $notice, $this->type, $this->teamId);
            } catch (\Throwable $e) {
                Log::error('お知らせの記録エラー user_id=' . $user->id . ': ' . $e->getMessage());
            }
        }

        // プッシュ通知は、購読している端末がある人にだけ送る
        $users->load('pushSubscriptions');
        $subscribers = $users->filter(fn (User $user) => $user->pushSubscriptions->isNotEmpty())->values();

        Log::info("プッシュ通知: type={$this->type} 宛先{$users->count()}人 送信{$subscribers->count()}人 tag={$notice->tag}");
        foreach ($subscribers as $user) {
            try {
                Notification::sendNow($user, $notice);
            } catch (\Throwable $e) {
                // 1人の失敗で他の人に届かなくならないようにする
                Log::error('プッシュ通知の送信エラー user_id=' . $user->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * 宛先(購読の有無は問わない)。
     *
     * @return Collection<int, User>
     */
    public function recipients(PushRecipients $recipients): Collection
    {
        return match ($this->type) {
            self::TYPE_NEW_POST => ($post = Post::find($this->postId))
                ? $recipients->forNewPost($post, $this->actorId) : new Collection,
            self::TYPE_POST_COMMENT => ($post = Post::find($this->postId))
                ? $recipients->forPostComment($post, $this->actorId) : new Collection,
            self::TYPE_SCHEDULE_CHANGE, self::TYPE_SCHEDULE_DELETED
                => $recipients->forScheduleChange($this->teamId, $this->actorId),
            self::TYPE_SCHEDULE_COMMENT => ($schedule = Schedule::find($this->scheduleId))
                ? $recipients->forScheduleComment($schedule, $this->actorId) : new Collection,
            default => new Collection,
        };
    }

    /**
     * 送る通知の中身。対象が既に無い場合は null。
     */
    public function notice(): ?PushNotice
    {
        $team = Team::find($this->teamId);
        if (! $team) {
            return null;
        }
        $title = (string) $team->name;

        switch ($this->type) {
            case self::TYPE_NEW_POST:
                $post = Post::find($this->postId);
                if (! $post) {
                    return null;
                }
                return new PushNotice($title,
                    "{$this->actorName()}さんが投稿しました：{$post->title}",
                    'post-' . $post->id,
                    self::postUrl($this->teamId, (int) $post->id));

            case self::TYPE_POST_COMMENT:
                $post = Post::find($this->postId);
                $comment = PostComment::find($this->commentId);
                if (! $post || ! $comment) {
                    return null;
                }
                return new PushNotice($title,
                    "{$this->actorName()}さんが「{$post->title}」にコメントしました：{$comment->comment_text}",
                    'post-' . $post->id,
                    self::postUrl($this->teamId, (int) $post->id));

            case self::TYPE_SCHEDULE_CHANGE:
                $schedule = Schedule::find($this->scheduleId);
                if (! $schedule) {
                    return null;
                }
                return new PushNotice($title,
                    '予定が変更されました：' . self::monthDay($schedule->schedule_date) . ' ' . $schedule->title,
                    'schedule-' . $schedule->id,
                    self::scheduleUrl($this->teamId, (int) $schedule->id, self::ymd($schedule->schedule_date)));

            case self::TYPE_SCHEDULE_DELETED:
                $snapshot = $this->snapshot ?? [];
                if (empty($snapshot['schedule_date'])) {
                    return null;
                }
                // 予定はもう無いので、カレンダーでその日を開くリンクにする
                return new PushNotice($title,
                    '予定が削除されました：' . self::monthDay($snapshot['schedule_date']) . ' ' . ($snapshot['title'] ?? ''),
                    'schedule-' . $this->scheduleId,
                    self::calendarUrl($this->teamId, self::ymd($snapshot['schedule_date'])));

            case self::TYPE_SCHEDULE_COMMENT:
                $schedule = Schedule::find($this->scheduleId);
                $comment = ScheduleComment::find($this->commentId);
                if (! $schedule || ! $comment) {
                    return null;
                }
                return new PushNotice($title,
                    "{$this->actorName()}さんが予定「" . self::monthDay($schedule->schedule_date) . " {$schedule->title}」にコメントしました：{$comment->comment_text}",
                    'schedule-' . $schedule->id,
                    self::scheduleUrl($this->teamId, (int) $schedule->id, self::ymd($schedule->schedule_date)));
        }

        return null;
    }

    /**
     * 操作した人の表示名。メールの通知と同じく、そのチームでのメンバー名を使う。
     */
    public function actorName(): string
    {
        $member = Member::query()
            ->where('user_id', $this->actorId)
            ->where('team_id', $this->teamId)
            ->orderByRaw('withdrawal_date is not null')
            ->first();
        if ($member && $member->name) {
            return (string) $member->name;
        }

        return (string) (User::find($this->actorId)?->name ?? '');
    }

    // リンク(設計書 §7.3.1) ---------------------------------------------

    public static function postUrl(int $teamId, int $postId): string
    {
        return "/home?launcher=true&team={$teamId}&post={$postId}";
    }

    public static function scheduleUrl(int $teamId, int $scheduleId, string $date): string
    {
        return "/home?launcher=true&team={$teamId}&schedule={$scheduleId}&date={$date}";
    }

    public static function calendarUrl(int $teamId, string $date): string
    {
        return "/home?launcher=true&team={$teamId}&date={$date}";
    }

    private static function ymd(mixed $date): string
    {
        return Carbon::parse($date)->format('Y-m-d');
    }

    /** 9/27 の形 */
    private static function monthDay(mixed $date): string
    {
        return Carbon::parse($date)->format('n/j');
    }
}
