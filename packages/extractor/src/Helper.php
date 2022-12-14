<?php

namespace PiedWeb\Extractor;

use ForceUTF8\Encoding;

class Helper
{
    public static function clean(string $source): string
    {
        return trim(self::preg_replace_str('/\s{2,}/', ' ', Encoding::toUTF8($source)));
    }

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

    public static function preg_replace_str(string $pattern, array|string $replacement, array|string $subject, int $limit = -1, int &$count = 0): string // @phpstan-ignore-line
    {
        $return = \Safe\preg_replace($pattern, $replacement, $subject, $limit, $count);

        // if (\gettype($pattern) !== \gettype($return)) {
        if (! \is_string($return)) {
            throw new \Exception('An error occured on preg_replace');
        }

        return $return;
    }
}
