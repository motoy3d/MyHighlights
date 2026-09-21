<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * 空(0バイト)のアップロードファイルを拒否するバリデーションルール。
 *
 * iPhoneのホーム画面アプリ(PWA)などで、写真を選んだ後にアプリが
 * バックグラウンドへ回るとファイルの中身を読めなくなり、0バイトの
 * ファイルが送られてくることがある(#45)。これを受け付けると壊れた
 * 添付として保存されてしまうため、422で弾いて選び直してもらう。
 *
 * Laravel標準の min:1 は「1KB以上」の意味になり、数バイトの正常な
 * テキストファイルまで弾いてしまうため使わない。
 */
class NotEmptyFile implements ValidationRule
{
    public const MESSAGE = '空のファイル（0バイト）は添付できません。もう一度選び直してください。';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // getSize() は読めない場合 false を返すことがあるので、それも空として扱う
        if ($value instanceof UploadedFile && (int) $value->getSize() === 0) {
            $fail(self::MESSAGE);
        }
    }
}
