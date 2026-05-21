<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class BidiText
{
    public static function auto(?string $value): HtmlString
    {
        return self::wrap($value, 'auto');
    }

    public static function ltr(?string $value): HtmlString
    {
        return self::wrap($value, 'ltr', true);
    }

    private static function wrap(?string $value, string $dir, bool $noWrap = false): HtmlString
    {
        $style = 'unicode-bidi:isolate;display:inline-block;max-width:100%;';

        if ($noWrap) {
            $style .= 'white-space:nowrap;';
        }

        return new HtmlString(sprintf(
            '<span dir="%s" style="%s">%s</span>',
            e($dir),
            e($style),
            e((string) $value),
        ));
    }
}
