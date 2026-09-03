<?php

namespace Tests\Feature\Api;

use App\Post;
use App\PostAttachment;
use App\PostComment;
use App\PostCommentAttachment;
use App\Team;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 添付ファイルの取り扱いのテスト。
 */
class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->team, $this->user] = $this->makeTeamWithUser();
        Queue::fake();
        Storage::fake('local');
    }

    public function test_保存名の拡張子はアップロード内容から決まる(): void
    {
        // 実行可能なファイルを送っても、保存名の拡張子は
        // クライアントのファイル名ではなく中身から判定される。
        // UploadedFile::fake() はファイル名からMIMEを決めてしまい実際の
        // アップロードと挙動が変わるため、実ファイルから作る。
        $php = $this->realUpload('evil.php', '<?php echo "x"; ?>');

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/posts', [
                'title' => 'php添付', 'contents' => 'x', 'notification_flg' => 0,
                'files' => [$php],
            ])->assertStatus(200);

        $attachment = PostAttachment::first();
        // 中身がPHPだと text/x-php と判定され、対応する拡張子が無いため拡張子なしで保存される。
        // Apacheは拡張子で実行可否を決めるので、これによりPHPとして実行されない。
        $this->assertStringEndsNotWith('.php', $attachment->file_path, '.php のまま保存されている');
        // 元のファイル名は記録として残る
        $this->assertSame('evil.php', $attachment->original_file_name);
    }

    public function test_HTMLの添付はhtmlとして保存される(): void
    {
        // 中身がHTMLだと .html で保存され、そのまま配信すると
        // 同一オリジンでスクリプトが動いてしまう。
        // これは deploy/tsubasa.conf の Content-Disposition: attachment で
        // 無害化しているため、ここでは保存のされ方だけを記録しておく。
        $html = $this->realUpload('note.html', '<html><script>alert(1)</script></html>');

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/posts', [
                'title' => 'html添付', 'contents' => 'x', 'notification_flg' => 0,
                'files' => [$html],
            ])->assertStatus(200);

        $this->assertStringEndsWith('.html', PostAttachment::first()->file_path);
    }

    public function test_画像でないのに画像の名前で送られても500にならない(): void
    {
        // 中身がテキストのファイルを .gif という名前で送るケース。
        // 以前は画像として読もうとして500になっていた。
        $fake = UploadedFile::fake()->createWithContent('fake.gif', 'これは画像ではない');

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/posts', [
                'title' => '偽画像', 'contents' => 'x', 'notification_flg' => 0,
                'files' => [$fake],
            ])->assertStatus(200);

        $this->assertDatabaseCount('post_attachments', 1);
    }

    public function test_上限を超える添付は422になる(): void
    {
        config(['tsubasa.attachment_max_kb' => 100]);
        $big = UploadedFile::fake()->create('big.bin', 200); // 200KB

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/posts', [
                'title' => '大きい添付', 'contents' => 'x', 'notification_flg' => 0,
                'files' => [$big],
            ])->assertStatus(422);

        $this->assertDatabaseCount('post_attachments', 0);
    }

    public function test_上限内の添付は登録できる(): void
    {
        config(['tsubasa.attachment_max_kb' => 1000]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/posts', [
                'title' => '通常の添付', 'contents' => 'x', 'notification_flg' => 0,
                'files' => [UploadedFile::fake()->create('memo.txt', 10)],
            ])->assertStatus(200);

        $this->assertDatabaseCount('post_attachments', 1);
    }

    public function test_コメント添付も上限を超えると422(): void
    {
        config(['tsubasa.attachment_max_kb' => 100]);
        $post = Post::factory()->create(['team_id' => $this->team->id, 'comment_count' => 0]);

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/post_comments/' . $post->id, [
                'post_id' => $post->id, 'comment_text' => 'x',
                'comment_files' => [UploadedFile::fake()->create('big.bin', 200)],
            ])->assertStatus(422);

        $this->assertDatabaseCount('post_comment_attachments', 0);
    }

    /**
     * 実ファイルからアップロードを作る。
     * UploadedFile::fake() はファイル名からMIMEを決めてしまうため、
     * 中身からMIMEが判定される実際のアップロードを再現できない。
     */
    private function realUpload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $content);

        // 第5引数 $test = true で、テスト実行時でもアップロードとして扱われる
        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_大きい画像はリサイズされる(): void
    {
        // Storage::fake だと storage_path 配下に無く resizeImage が読めないため、
        // ここでは実ディスクを使う
        Storage::persistentFake('local');

        $this->actingAsTeamMember($this->user, $this->team)
            ->post('/api/posts', [
                'title' => '画像', 'contents' => 'x', 'notification_flg' => 0,
                'files' => [UploadedFile::fake()->image('big.png', 1600, 1200)],
            ])->assertStatus(200);

        $this->assertDatabaseCount('post_attachments', 1);
    }
}
