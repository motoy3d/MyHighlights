<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 切り替え当夜のスモークテスト用アカウントを作る/消す。
 *
 * 当夜は本番DBをフルで取り直すため、フェーズ2で使っていた検証用アカウントは消える。
 * 取り込み後にこれで作り直し、切り替え後に消す。
 *
 *   php artisan smoke:account create --password='...'
 *   php artisan smoke:account delete
 *
 * 実チームには一切触れない。検証専用のチームを2つ作り(チーム切り替えの確認用)、
 * そこに投稿・予定・カテゴリを入れる。検証中の投稿が実メンバーのタイムラインに
 * 見えたり、通知ジョブが実メンバー宛に積まれたりしないようにするため。
 *
 * delete はこのコマンドが作ったチーム(名前が「【検証】」で始まり created_id が
 * 検証ユーザー)に属するものだけを、添付ファイルごと消す。
 */
class SmokeAccount extends Command
{
    protected $signature = 'smoke:account {action : create|delete}
                            {--email=browser-test@example.invalid}
                            {--password= : create 時に必須}
                            {--since= : delete 時、この日時以降に置かれたDB参照の無い添付ファイルも消す(既定: 検証ユーザーの作成日時)}';

    protected $description = '切り替え当夜のスモークテスト用アカウントと検証専用チームを作成/削除する';

    private const TEAM_PREFIX = '【検証】';

    public function handle(): int
    {
        $email = $this->option('email');
        $existing = DB::table('users')->where('email', $email)->value('id');

        return match ($this->argument('action')) {
            'create' => $this->create($email, $existing),
            'delete' => $this->delete($email, $existing),
            default => (function () { $this->error('action は create か delete'); return self::FAILURE; })(),
        };
    }

    private function create(string $email, ?int $existing): int
    {
        $pass = $this->option('password');
        if (!$pass) {
            $this->error('--password を指定すること');
            return self::FAILURE;
        }
        if ($existing) {
            $this->delete($email, $existing);
        }

        $now = now();
        $uid = DB::table('users')->insertGetId([
            'name' => 'ブラウザテスト', 'name_kana' => 'ブラウザテスト',
            'email' => $email, 'password' => Hash::make($pass),
            'mail_notification_flg' => 0,
            'created_id' => 0, 'updated_id' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('users')->where('id', $uid)->update(['created_id' => $uid, 'updated_id' => $uid]);

        $teamIds = [];
        foreach (['A', 'B'] as $i => $suffix) {
            $tid = DB::table('teams')->insertGetId([
                'name' => self::TEAM_PREFIX . '切替検証' . $suffix,
                'color' => $i === 0 ? '#1e88e5' : '#43a047',
                'plan_id' => 0,
                'ical_id' => (string) Str::uuid(),
                'blog_rss' => null,
                'created_id' => $uid, 'updated_id' => $uid,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $teamIds[] = $tid;
            DB::table('members')->insert([
                'user_id' => $uid, 'team_id' => $tid,
                'name' => 'ブラウザテスト', 'name_kana' => 'ブラウザテスト',
                'type' => '1', 'admin_flg' => 1, 'backno' => 99,
                'prof_img_filename' => 'noimage.png',
                'created_id' => $uid, 'updated_id' => $uid,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $catIds = [];
            foreach (['練習', '試合', '大会', 'その他'] as $n => $cat) {
                $catIds[] = DB::table('categories')->insertGetId([
                    'team_id' => $tid, 'name' => $cat, 'order_no' => $n + 1,
                    'created_id' => $uid, 'updated_id' => $uid,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            // タイムラインと検索が空にならない程度の投稿
            for ($k = 1; $k <= 5; $k++) {
                DB::table('posts')->insert([
                    'team_id' => $tid,
                    'title' => "検証用の投稿 {$suffix}-{$k}",
                    'content' => "切り替え検証のための投稿です。実チームのデータではありません。({$k})",
                    'category_id' => $catIds[$k % count($catIds)],
                    'questionnaire_id' => null, 'notification_flg' => 0, 'comment_count' => 0,
                    'created_id' => $uid, 'updated_id' => $uid,
                    'created_at' => $now->copy()->subDays($k), 'updated_at' => $now->copy()->subDays($k),
                ]);
            }
            // カレンダーが空にならない程度の予定(時間あり / 終日 / 開始のみ)
            foreach ([
                ['練習', 0, '09:30:00', '12:00:00', 2],
                ['合宿', 1, null, null, 7],
                ['ミーティング', 0, '18:00:00', null, 14],
            ] as [$title, $allday, $from, $to, $days]) {
                DB::table('schedules')->insert([
                    'team_id' => $tid, 'schedule_date' => $now->copy()->addDays($days)->toDateString(),
                    'title' => "検証用: {$title}", 'allday_flg' => $allday,
                    'time_from' => $from, 'time_to' => $to,
                    'content' => '切り替え検証のための予定です。', 'notification_flg' => 0,
                    'created_id' => $uid, 'updated_id' => $uid,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $this->line("  team {$tid}: " . self::TEAM_PREFIX . "切替検証{$suffix} (投稿5 / 予定3 / カテゴリ4)");
        }
        $this->info("作成: user_id={$uid} {$email}  実チームには所属していない");
        return self::SUCCESS;
    }

    private function delete(string $email, ?int $existing): int
    {
        if (!$existing) {
            $this->info("{$email} は存在しない");
            return self::SUCCESS;
        }
        $teamIds = DB::table('teams')->where('created_id', $existing)
            ->where('name', 'like', self::TEAM_PREFIX . '%')->pluck('id')->all();

        // 実チームに何か残していないか(このコマンドの外で作られた場合の保険)
        $stray = DB::table('posts')->where('created_id', $existing)->whereNotIn('team_id', $teamIds)->count()
            + DB::table('post_comments')->where('created_id', $existing)->count()
            + DB::table('schedules')->where('created_id', $existing)->whereNotIn('team_id', $teamIds)->count();
        if ($stray > 0) {
            $this->error("検証チーム外に検証ユーザーのデータが {$stray} 件ある。先に画面から削除すること");
            return self::FAILURE;
        }

        $postIds = DB::table('posts')->whereIn('team_id', $teamIds)->pluck('id')->all();
        $commentIds = DB::table('post_comments')->whereIn('post_id', $postIds)->pluck('id')->all();
        $files = array_merge(
            DB::table('post_attachments')->whereIn('post_id', $postIds)->pluck('file_path')->all(),
            DB::table('post_comment_attachments')->whereIn('post_comment_id', $commentIds)->pluck('file_path')->all(),
        );
        DB::transaction(function () use ($existing, $teamIds, $postIds, $commentIds) {
            DB::table('post_comment_attachments')->whereIn('post_comment_id', $commentIds)->delete();
            DB::table('post_comment_responses')->whereIn('post_comment_id', $commentIds)->delete();
            DB::table('post_comments')->whereIn('post_id', $postIds)->delete();
            DB::table('post_attachments')->whereIn('post_id', $postIds)->delete();
            DB::table('post_responses')->whereIn('post_id', $postIds)->delete();
            $qIds = DB::table('posts')->whereIn('id', $postIds)->whereNotNull('questionnaire_id')->pluck('questionnaire_id')->all();
            DB::table('questionnaire_answers')->whereIn('questionnaire_id', $qIds)->delete();
            DB::table('questionnaires')->whereIn('id', $qIds)->delete();
            DB::table('posts')->whereIn('id', $postIds)->delete();
            $sIds = DB::table('schedules')->whereIn('team_id', $teamIds)->pluck('id')->all();
            DB::table('schedule_comments')->whereIn('schedule_id', $sIds)->delete();
            DB::table('schedules')->whereIn('id', $sIds)->delete();
            DB::table('categories')->whereIn('team_id', $teamIds)->delete();
            DB::table('members')->whereIn('team_id', $teamIds)->orWhere('user_id', $existing)->delete();
            DB::table('teams')->whereIn('id', $teamIds)->delete();
            DB::table('users')->where('id', $existing)->delete();
        });
        $removed = 0;
        foreach ($files as $rel) {
            $path = storage_path('app/public/' . preg_replace('#^storage/#', '', $rel));
            if (is_file($path) && @unlink($path)) $removed++;
        }
        // 画面から投稿を消すとDB行は消えるがファイルは残る(アプリの仕様)。
        // 検証ユーザーの作成以降に置かれた、DBから参照されていないファイルを掃除する
        // create/delete を繰り返した場合は前のアカウントの分が残るので --since で遡れる
        $since = strtotime($this->option('since') ?: (DB::table('users')->where('id', $existing)->value('created_at') ?? 'now'));
        $orphans = 0;
        foreach (['post_attachment' => 'post_attachments', 'comment_attachment' => 'post_comment_attachments'] as $dir => $table) {
            foreach (glob(storage_path("app/public/{$dir}/*")) ?: [] as $path) {
                if (!is_file($path) || filemtime($path) < $since) continue;
                $name = basename($path);
                if (DB::table($table)->where('file_path', 'like', "%{$name}")->exists()) continue;
                if (@unlink($path)) $orphans++;
            }
        }
        $this->info("削除: user_id={$existing} {$email} / 検証チーム " . count($teamIds) . " / 投稿 " . count($postIds) . " / 添付ファイル {$removed} / 参照の無い残骸ファイル {$orphans}");
        return self::SUCCESS;
    }
}
