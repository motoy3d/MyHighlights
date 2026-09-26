<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * 予定の変更をプッシュで知らせるべきかの判定(#110 設計書 §4)。
 *
 * 日付・開始時刻・終了時刻・終日・タイトルのいずれかが変わったときだけ通知する。
 * メモ欄(content)やカテゴリだけの修正では通知しない。
 *
 * 画面から届く値とDBから読んだ値は形が揃っていない
 * (例: 時刻は '09:30' と '09:30:00'、終日は 'true' と 1、未入力は 'null' / '' / null)
 * ため、比べる前に正規化する。
 */
class ScheduleChange
{
    /** 比べる項目 */
    public const FIELDS = ['schedule_date', 'time_from', 'time_to', 'allday_flg', 'title'];

    /**
     * 比べる項目だけを正規化して取り出す。
     *
     * @param  array<string, mixed>  $attributes  Schedule の属性(getAttributes()/getOriginal() など)
     * @return array{schedule_date: ?string, time_from: ?string, time_to: ?string, allday_flg: bool, title: string}
     */
    public static function normalize(array $attributes): array
    {
        return [
            'schedule_date' => self::normalizeDate($attributes['schedule_date'] ?? null),
            'time_from' => self::normalizeTime($attributes['time_from'] ?? null),
            'time_to' => self::normalizeTime($attributes['time_to'] ?? null),
            'allday_flg' => self::normalizeBool($attributes['allday_flg'] ?? null),
            'title' => trim((string) ($attributes['title'] ?? '')),
        ];
    }

    /**
     * 通知に値する変更があるか。
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function isSignificant(array $before, array $after): bool
    {
        return self::normalize($before) !== self::normalize($after);
    }

    /** 日付を Y-m-d にそろえる。解釈できなければ文字列のまま比べる */
    public static function normalizeDate(mixed $value): ?string
    {
        if (self::isEmpty($value)) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return trim((string) $value);
        }
    }

    /** 時刻を H:i にそろえる('9:30' '09:30' '09:30:00' はすべて '09:30')。空は null */
    public static function normalizeTime(mixed $value): ?string
    {
        if (self::isEmpty($value)) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }
        $value = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $value, $m)) {
            return sprintf('%02d:%s', (int) $m[1], $m[2]);
        }

        return $value;
    }

    /** 1 / '1' / true / 'true' を真とする。'false' や '0' を真にしないよう (bool) キャストは使わない */
    public static function normalizeBool(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on'], true);
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || (is_string($value) && (trim($value) === '' || trim($value) === 'null'));
    }
}
