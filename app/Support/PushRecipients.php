<?php

namespace App\Support;

use App\Member;
use App\Post;
use App\PostComment;
use App\Schedule;
use App\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * プッシュ通知の宛先を決める(#110 設計書 §4)。
 *
 * どの種類でも、次をすべて満たす利用者だけが宛先になる。
 *   - 対象のチームに在籍している(members.withdrawal_date が NULL、論理削除されていない)
 *   - 利用者自身が退会していない(users.withdrawal_date が NULL)
 *   - 操作した本人ではない
 *   - 段階的な公開の対象である(PushRollout)
 *   - その種類の通知をオンにしている(User::wantsPush)
 *
 * 購読(端末)を持っているかどうかはここでは見ない。
 * 購読の無い利用者には WebPushChannel が何も送らない。
 */
class PushRecipients
{
    /** 指導者(監督・コーチ)の members.type */
    public const MEMBER_TYPE_STAFF = '2';

    /**
     * 新しい投稿：チームの全員(new_post)。
     *
     * @return Collection<int, User>
     */
    public function forNewPost(Post $post, int $actorId): Collection
    {
        return $this->filterByPreference(
            $this->activeTeamUsers((int) $post->team_id, $actorId),
            fn () => 'new_post'
        );
    }

    /**
     * 投稿へのコメント。
     *   投稿者と、その投稿にコメントした人 → comment_on_mine
     *   それ以外のチームの全員             → comment_on_others
     *
     * @return Collection<int, User>
     */
    public function forPostComment(Post $post, int $actorId): Collection
    {
        $involved = $this->postInvolvedUserIds($post);

        return $this->filterByPreference(
            $this->activeTeamUsers((int) $post->team_id, $actorId),
            fn (User $user) => in_array((int) $user->id, $involved, true)
                ? 'comment_on_mine'
                : 'comment_on_others'
        );
    }

    /**
     * 予定の変更・中止：チームの全員(schedule_change)。
     *
     * @return Collection<int, User>
     */
    public function forScheduleChange(int $teamId, int $actorId): Collection
    {
        return $this->filterByPreference(
            $this->activeTeamUsers($teamId, $actorId),
            fn () => 'schedule_change'
        );
    }

    /**
     * 予定へのコメント：予定を作った人と指導者(schedule_comment)。
     * 予定へのコメントの大半は出欠連絡なので、全員には送らない。
     *
     * @return Collection<int, User>
     */
    public function forScheduleComment(Schedule $schedule, int $actorId): Collection
    {
        $teamId = (int) $schedule->team_id;
        $targetIds = Member::query()
            ->where('team_id', $teamId)
            ->whereNull('withdrawal_date')
            ->where('type', self::MEMBER_TYPE_STAFF)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($schedule->created_id) {
            $targetIds[] = (int) $schedule->created_id;
        }

        return $this->filterByPreference(
            $this->activeTeamUsers($teamId, $actorId, array_values(array_unique($targetIds))),
            fn () => 'schedule_comment'
        );
    }

    /**
     * 投稿に「関わっている」利用者のID(投稿者と、その投稿にコメントした人)。
     *
     * @return array<int, int>
     */
    public function postInvolvedUserIds(Post $post): array
    {
        $ids = PostComment::query()
            ->where('post_id', $post->id)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($post->created_id) {
            $ids[] = (int) $post->created_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * チームに在籍していて退会していない利用者(操作した本人を除く)。
     * 段階的な公開の対象外の利用者もここで除く。
     *
     * @param  array<int, int>|null  $onlyUserIds  指定した場合はこの中から選ぶ
     * @return Collection<int, User>
     */
    public function activeTeamUsers(int $teamId, int $actorId, ?array $onlyUserIds = null): Collection
    {
        if (PushRollout::isDisabledForEveryone()) {
            return new Collection;
        }
        if ($onlyUserIds !== null && $onlyUserIds === []) {
            return new Collection;
        }

        // Member は SoftDeletes なので、論理削除された行はこのサブクエリに含まれない
        $memberUserIds = Member::query()
            ->select('user_id')
            ->where('team_id', $teamId)
            ->whereNull('withdrawal_date');

        $query = User::query()
            ->whereIn('id', $memberUserIds)
            ->whereNull('withdrawal_date')
            ->where('id', '!=', $actorId)
            ->orderBy('id');
        if ($onlyUserIds !== null) {
            $query->whereIn('id', $onlyUserIds);
        }

        // チームの人数は多くても百数十人なので、公開対象の判定はPHP側で行う
        // (メールアドレスの大文字小文字の扱いをDBの照合順序に依存させないため)
        return $query->get()
            ->filter(fn (User $user) => PushRollout::isEnabledFor($user))
            ->values();
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  callable(User): string  $prefKeyFor  利用者ごとに見る設定のキー
     * @return Collection<int, User>
     */
    private function filterByPreference(Collection $users, callable $prefKeyFor): Collection
    {
        return $users
            ->filter(fn (User $user) => $user->wantsPush($prefKeyFor($user)))
            ->values();
    }
}
