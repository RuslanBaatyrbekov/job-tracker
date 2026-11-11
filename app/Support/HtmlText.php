<?php

namespace App\Support;

/**
 * Converts email HTML to plain text but keeps links as "text (url)",
 * so the AI can see which job each link in a job alert points to.
 */
class HtmlText
{
    public static function fromHtml(?string $html): string
    {
        $html = (string) $html;
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', ' ', $html);

        $html = preg_replace_callback('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', function ($m) {
            $url = html_entity_decode($m[1]);
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($m[2])));

            if (! str_starts_with($url, 'http')) {
                return $text;
            }

            return ($text !== '' ? $text.' ' : '').'('.$url.')';
        }, $html);

        $html = preg_replace('#<(br|/p|/div|/tr|/li|/h[1-6])\b[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);
        $text = preg_replace("/\n\s*\n+/", "\n\n", $text);

        return trim($text);
    }
}
