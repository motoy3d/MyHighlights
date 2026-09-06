<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 切り替え当夜のスモークテスト用アカウントを作る/消す。
 *
 * 当夜は本番DBをフルで取り直すため、フェーズ2で使っていた検証用アカウントは消える。
 * 取り込み後にこれで作り直し、切り替え後に消す。
 *
 *   php artisan smoke:account create --password='...'
 *   php artisan smoke:account delete
 *
 * 投稿数の多いチーム上位2つに所属させる(チーム切り替えの確認用)。
 * 通知は切ってあるが、このアカウントで作った投稿の通知ジョブは
 * チームの実メンバー宛に積まれるので、ワーカー起動前に jobs を空にすること。
 */
class SmokeAccount extends Command
{
    protected $signature = 'smoke:account {action : create|delete}
                            {--email=browser-test@example.invalid}
                            {--password= : create 時に必須}
                            {--teams= : 所属させるチームID(カンマ区切り)。省略時は投稿数上位2チーム}';

    protected $description = '切り替え当夜のスモークテスト用アカウントを作成/削除する';

    public function handle(): int
    {
        $email = $this->option('email');
        $existing = DB::table('users')->where('email', $email)->value('id');

        if ($this->argument('action') === 'delete') {
            if (!$existing) {
                $this->info("{$email} は存在しない");
                return self::SUCCESS;
            }
            $posts = DB::table('posts')->where('created_id', $existing)->count();
            if ($posts > 0) {
                $this->error("このアカウントの投稿が {$posts} 件残っている。先に画面から削除すること(通知ジョブが実メンバー宛に積まれるため)");
                return self::FAILURE;
            }
            DB::table('post_responses')->where('created_id', $existing)->delete();
            DB::table('questionnaire_answers')->where('created_id', $existing)->delete();
            DB::table('members')->where('user_id', $existing)->delete();
            DB::table('users')->where('id', $existing)->delete();
            $this->info("削除: user_id={$existing} {$email}");
            return self::SUCCESS;
        }

        if ($this->argument('action') !== 'create') {
            $this->error('action は create か delete');
            return self::FAILURE;
        }
        $pass = $this->option('password');
        if (!$pass) {
            $this->error('--password を指定すること');
            return self::FAILURE;
        }
        if ($existing) {
            DB::table('members')->where('user_id', $existing)->delete();
            DB::table('users')->where('id', $existing)->delete();
        }

        $now = now();
        $uid = DB::table('users')->insertGetId([
            'name' => 'ブラウザテスト', 'name_kana' => 'ブラウザテスト',
            'email' => $email, 'password' => Hash::make($pass),
            'mail_notification_flg' => 0,   // 自分宛の通知は切る
            'created_id' => 0, 'updated_id' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('users')->where('id', $uid)->update(['created_id' => $uid, 'updated_id' => $uid]);

        $teams = $this->option('teams')
            ? array_map('intval', explode(',', $this->option('teams')))
            : DB::table('posts')->select('team_id', DB::raw('COUNT(*) c'))
                ->groupBy('team_id')->orderByDesc('c')->limit(2)->pluck('team_id')->all();

        foreach ($teams as $tid) {
            DB::table('members')->insert([
                'user_id' => $uid, 'team_id' => $tid,
                'name' => 'ブラウザテスト', 'name_kana' => 'ブラウザテスト',
                'type' => '1', 'admin_flg' => 0, 'backno' => 99,
                'prof_img_filename' => 'noimage.png',
                'created_id' => $uid, 'updated_id' => $uid,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $name = DB::table('teams')->where('id', $tid)->value('name');
            $this->line("  team {$tid}: {$name}");
        }
        $this->info("作成: user_id={$uid} {$email}");
        return self::SUCCESS;
    }
}
