<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * メンバーを別のアカウントに付け替えるときに、そのチームでの過去の書き込みも移す(#124)。
 *
 * 付け替えは主に「同じ人がアカウントを二重に持っている」ときに、今使っているアカウントへ寄せるために使う。
 * 投稿者名などは members(user_id, team_id) と突き合わせて表示するので、移さないと元のアカウントの書き込みは
 * 投稿者名が消え、アンケートの集計からも抜ける。
 *
 * 移すのは、そのチームの中のものだけ(他のチームでの書き込みは元のアカウントのまま)。
 * - 投稿・予定：作成者と更新者(created_id / updated_id)
 * - 投稿・予定へのコメント：書いた人(user_id)と作成者・更新者
 * - 添付ファイル・アンケート：作成者
 * - 既読・いいね・スター(投稿とコメント)：移す先に同じ投稿(コメント)の行が無いものだけ。
 *   一意制約(user_id, post_id)があり、両方にある場合は移す先の行を正とする(いいねの数も変わらない)
 * - アンケートの回答：移す先がまだ答えていないアンケートの分だけ(二重に数えないため)
 * 移さなかった行は消さずに元のアカウントのまま残す(付け替え前と同じく、表示や集計には出ない)。
 *
 * 呼び出し側でトランザクションを張ること。
 */
class AccountRelinker
{
    public static function moveTeamHistory(int $teamId, int $fromUserId, int $toUserId): void
    {
        if ($fromUserId === $toUserId) {
            return;
        }

        $teamPostIds = DB::table('posts')->where('team_id', $teamId)->select('id');
        $teamScheduleIds = DB::table('schedules')->where('team_id', $teamId)->select('id');
        $teamCommentIds = DB::table('post_comments')->whereIn('post_id', $teamPostIds)->select('id');
        $teamQuestionnaireIds = DB::table('posts')->where('team_id', $teamId)
            ->whereNotNull('questionnaire_id')->select('questionnaire_id');

        // 投稿・予定の作成者と更新者
        foreach (['posts', 'schedules'] as $table) {
            foreach (['created_id', 'updated_id'] as $column) {
                DB::table($table)->where('team_id', $teamId)->where($column, $fromUserId)
                    ->update([$column => $toUserId]);
            }
        }

        // コメント(書いた人は user_id で表示している)
        $comments = [
            'post_comments' => ['post_id', $teamPostIds],
            'schedule_comments' => ['schedule_id', $teamScheduleIds],
        ];
        foreach ($comments as $table => [$parent, $parentIds]) {
            foreach (['user_id', 'created_id', 'updated_id'] as $column) {
                DB::table($table)->whereIn($parent, $parentIds)->where($column, $fromUserId)
                    ->update([$column => $toUserId]);
            }
        }

        // 添付ファイル・アンケートの作成者
        DB::table('post_attachments')->whereIn('post_id', $teamPostIds)
            ->where('created_id', $fromUserId)->update(['created_id' => $toUserId]);
        DB::table('post_comment_attachments')->whereIn('post_comment_id', $teamCommentIds)
            ->where('created_id', $fromUserId)->update(['created_id' => $toUserId]);
        DB::table('questionnaires')->whereIn('id', $teamQuestionnaireIds)
            ->where('created_id', $fromUserId)->update(['created_id' => $toUserId]);

        // 既読・いいね・スター。移す先に行がある投稿(コメント)は動かさない。
        // MariaDB は更新する表を同じ文の副問い合わせで読めないので、移す先の分を先に読んでおく
        $responses = [
            'post_responses' => ['post_id', $teamPostIds],
            'post_comment_responses' => ['post_comment_id', $teamCommentIds],
        ];
        foreach ($responses as $table => [$key, $keyIds]) {
            $taken = DB::table($table)->where('user_id', $toUserId)->whereIn($key, $keyIds)->pluck($key)->all();
            DB::table($table)->where('user_id', $fromUserId)->whereIn($key, $keyIds)
                ->whereNotIn($key, $taken)
                ->update(['user_id' => $toUserId]);
        }

        // アンケートの回答。移す先がまだ答えていないアンケートの分だけ
        $answered = DB::table('questionnaire_answers')->where('user_id', $toUserId)
            ->whereIn('questionnaire_id', $teamQuestionnaireIds)->distinct()->pluck('questionnaire_id')->all();
        DB::table('questionnaire_answers')->where('user_id', $fromUserId)
            ->whereIn('questionnaire_id', $teamQuestionnaireIds)
            ->whereNotIn('questionnaire_id', $answered)
            ->update(['user_id' => $toUserId, 'created_id' => $toUserId, 'updated_id' => $toUserId]);
    }
}
