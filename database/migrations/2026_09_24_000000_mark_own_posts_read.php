<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 自分の投稿を既読にする(#123)。一度だけ流す。
 *
 * 既読は投稿の詳細を開いたときに付くが、投稿した直後はタイムラインに戻るだけなので、
 * これまで自分の投稿は自分の未読として残っていた(タブの未読件数が増えていた)。
 * 投稿時に既読を付けるようにした(PostController::store)ので、過去の分をここで直す。
 *
 * - 対象：投稿した人の post_responses がまだ無い投稿だけ。
 *   既にある行(いいね・スターだけ付けた行など)は触らない
 * - 何度流しても同じ結果になる(無い分だけ入れる)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'INSERT INTO post_responses'
            . ' (user_id, post_id, read_flg, like_flg, star_flg, created_id, created_at, updated_id, updated_at)'
            . ' SELECT p.created_id, p.id, 1, 0, 0, p.created_id, NOW(), p.created_id, NOW()'
            . ' FROM posts p'
            . ' WHERE p.created_id IS NOT NULL'
            . ' AND NOT EXISTS (SELECT 1 FROM post_responses r WHERE r.user_id = p.created_id AND r.post_id = p.id)'
        );
    }

    /**
     * このマイグレーションで入れた行だけを見分けられない(投稿者が自分で詳細を開いて付いた既読と
     * 区別できない)ので、巻き戻しでは何もしない。
     */
    public function down(): void
    {
    }
};
