<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

/** Lecture du code source pour la garde d'architecture : commentaires ignorés. */
final class SourceScanner
{
    private const FORBIDDEN = '/(?<!->)(?<!::)(?<!\$)(?<!function )\b(?:time|microtime|hrtime|date|gmdate|mktime|strtotime|date_create|date_create_immutable|uniqid|rand|mt_rand|random_int|random_bytes|shuffle|str_shuffle|array_rand|lcg_value|mt_srand|srand|header|setcookie|http_response_code|session_[a-z_]+)\s*\(|\$_(?:SESSION|COOKIE|GET|POST|SERVER|REQUEST|FILES)\b|\bPDO\w*\b|\bnew\s+\\\\?DateTime(?:Immutable)?\s*\(\s*(?:\)|[\'"]now[\'"])/';

    public static function stripComments(string $php): string
    {
        $out = '';
        foreach (token_get_all($php) as $token) {
            if (\is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
                $newlines = substr_count($token[1], "\n");
                $out .= $newlines === 0 ? ' ' : str_repeat("\n", $newlines);
                continue;
            }
            $out .= \is_array($token) ? $token[1] : $token;
        }

        return $out;
    }

    /** @return list<string> */
    public static function forbiddenUses(string $php): array
    {
        preg_match_all(self::FORBIDDEN, self::stripComments($php), $matches);

        return $matches[0];
    }

    /** @return list<string> */
    public static function referencedModules(string $php): array
    {
        $modules = [];
        foreach (token_get_all($php) as $token) {
            if (!\is_array($token) || ($token[0] !== T_NAME_QUALIFIED && $token[0] !== T_NAME_FULLY_QUALIFIED)) {
                continue;
            }
            $parts = explode('\\', ltrim($token[1], '\\'));
            if ($parts[0] === 'LeCercle' && isset($parts[1])) {
                $modules[$parts[1]] = true;
            }
        }
        $names = array_keys($modules);
        sort($names);

        return $names;
    }
}
