<?php

namespace PiedWeb\Extractor;

use ForceUTF8\Encoding;

class Helper
{
    // public static function clean(string $source): string
    // {
    //     $source = Encoding::toUTF8($source);
    //     return trim(self::preg_replace_str('/\s{2,}/', ' ', $source));
    // }

    public static function isWebLink(string $url): bool
    {
        return 1 === preg_match('#^((?:(http:|https:)//([\w\d-]+\.)+[\w\d-]+){0,1}(/?[\w~,;\-\./?%&+\#=]*))$#', $url);
    }

    public static function htmlToPlainText(string $html, bool $keepN = false): string
    {
        $str = self::preg_replace_str('#<(style|script).*</(style|script)>#siU', ' ', $html);
        $str = self::preg_replace_str('#</?(br|p|div)>#siU', "\n", $str);
        $str = self::preg_replace_str('/<\/[a-z]+>/siU', ' ', $str);
        $str = str_replace(["\r", "\t"], ' ', $str);
        $str = strip_tags(self::preg_replace_str('/<[^<]+?>/', ' ', $str));

        return self::preg_replace_str($keepN ? '/ {2,}/' : '/\s+/', ' ', $str);
    }

    /**
     * @param array<string>|string $replacement
     * @param array<string>|string $subject
     *                                          , ?int &$count = null
     */
    public static function preg_replace_str(string $pattern, array|string $replacement, array|string $subject, int $limit = -1): string
    {
        $return = \Safe\preg_replace($pattern, $replacement, $subject, $limit); // , $count
        \assert(\is_string($return));

        return $return;
    }
}
