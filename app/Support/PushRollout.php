<?php

namespace App\Support;

use App\User;

/**
 * Webプッシュ通知の段階的な公開(#110 設計書 §8)。
 *
 * config('tsubasa.push_enabled_emails')(.env の PUSH_ENABLED_EMAILS)で対象者を決める。
 *   ''  : 誰にも公開しない
 *   '*' : 全員に公開する
 *   それ以外: カンマ区切りのメールアドレス(前後の空白は無視、大文字小文字は区別しない)
 *
 * 画面にスイッチを出すかどうかだけでなく、購読の受け付けと送信の宛先にも使う。
 * 公開前に何かの拍子で購読されても、対象外の利用者には何も送らないため。
 */
class PushRollout
{
    /**
     * 指定した利用者にプッシュ通知を公開しているか。
     */
    public static function isEnabledFor(?User $user): bool
    {
        if (! $user || ! $user->email) {
            return false;
        }
        if (self::isEnabledForEveryone()) {
            return true;
        }

        return in_array(mb_strtolower(trim($user->email)), self::emails(), true);
    }

    /**
     * 誰にも公開していないか。送信処理で宛先を調べる前に打ち切るのに使う。
     */
    public static function isDisabledForEveryone(): bool
    {
        return ! self::isEnabledForEveryone() && self::emails() === [];
    }

    public static function isEnabledForEveryone(): bool
    {
        return trim(self::raw()) === '*';
    }

    /**
     * 設定値のメールアドレス一覧(小文字化済み)。'*' の場合は空配列。
     *
     * @return array<int, string>
     */
    public static function emails(): array
    {
        $raw = trim(self::raw());
        if ($raw === '' || $raw === '*') {
            return [];
        }

        $emails = [];
        foreach (explode(',', $raw) as $email) {
            $email = mb_strtolower(trim($email));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    private static function raw(): string
    {
        return (string) config('tsubasa.push_enabled_emails', '');
    }
}
