<?php

declare(strict_types=1);

namespace Cinomnia\Services;

/**
 * Fuzzy title matching for search: tolerate typos and rank TMDB candidates.
 */
final class TitleMatcher
{
    /** @var array<string, true> */
    private const STOPWORDS = [
        'a'    => true,
        'an'   => true,
        'the'  => true,
        'of'   => true,
        'and'  => true,
        'or'   => true,
        'in'   => true,
        'on'   => true,
        'at'   => true,
        'to'   => true,
        'for'  => true,
        'with' => true,
        'from' => true,
        'by'   => true,
    ];

    /**
     * Lowercase ASCII-ish form used for comparison.
     */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($ascii) && $ascii !== '') {
                $text = strtolower($ascii);
            }
        }

        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $text, bool $stripStopwords = false): array
    {
        $normalized = self::normalize($text);
        if ($normalized === '') {
            return [];
        }

        $tokens = explode(' ', $normalized);
        if (!$stripStopwords) {
            return $tokens;
        }

        return array_values(array_filter(
            $tokens,
            static fn(string $token): bool => $token !== '' && !isset(self::STOPWORDS[$token])
        ));
    }

    /**
     * Multi-word queries are treated as full titles, so weak TMDB hits trigger fallbacks.
     */
    public static function looksLikeFullTitle(string $query): bool
    {
        $all          = self::tokens($query);
        $significant  = self::tokens($query, true);

        return count($all) >= 3 || count($significant) >= 2;
    }

    /**
     * Best similarity between the typed query and any candidate title string.
     *
     * @param list<string> $candidateTitles
     */
    public static function score(string $query, array $candidateTitles): float
    {
        $best = 0.0;

        foreach ($candidateTitles as $title) {
            if (!is_string($title) || trim($title) === '') {
                continue;
            }

            $best = max($best, self::scoreOne($query, $title));
            if ($best >= 0.995) {
                return 1.0;
            }
        }

        return $best;
    }

    public static function scoreOne(string $query, string $title): float
    {
        $queryNorm = self::normalize($query);
        $titleNorm = self::normalize($title);

        if ($queryNorm === '' || $titleNorm === '') {
            return 0.0;
        }

        if ($queryNorm === $titleNorm) {
            return 1.0;
        }

        $full = self::levenshteinRatio($queryNorm, $titleNorm);
        $token = self::tokenMatchScore($queryNorm, $titleNorm);
        $contain = 0.0;

        if (str_contains($titleNorm, $queryNorm)) {
            $contain = 0.9 + 0.1 * (strlen($queryNorm) / max(strlen($titleNorm), 1));
        }

        return max($full, $token, $contain);
    }

    /**
     * TMDB queries that still find the title when one word is misspelled.
     *
     * @return list<string>
     */
    public static function fallbackQueries(string $query, int $limit = 6): array
    {
        $original    = self::normalize($query);
        $significant = self::tokens($query, true);
        $candidates  = [];

        $push = static function (string $candidate) use (&$candidates, $original): void {
            $candidate = self::normalize($candidate);
            if ($candidate !== '' && $candidate !== $original && !isset($candidates[$candidate])) {
                $candidates[$candidate] = true;
            }
        };

        $longestIndex = null;
        $longestLen   = 0;
        foreach ($significant as $index => $token) {
            $length = strlen($token);
            if ($length > $longestLen) {
                $longestLen   = $length;
                $longestIndex = $index;
            }
        }

        // Drop the longest word — that is usually the typo in titles like
        // "The Hounting of Hill House" → "Hill House".
        if ($longestIndex !== null && count($significant) >= 2) {
            $without = $significant;
            unset($without[$longestIndex]);
            $push(implode(' ', $without));
        }

        if (count($significant) >= 2) {
            $push(implode(' ', array_slice($significant, -2)));
        }

        if ($significant !== []) {
            $push(implode(' ', $significant));
        }

        // Prefix of the longest token: "Incepton" → "Incept".
        if ($longestIndex !== null && $longestLen >= 5) {
            foreach ([1, 2] as $drop) {
                if ($longestLen - $drop < 4) {
                    continue;
                }

                $variant = $significant;
                $variant[$longestIndex] = substr($significant[$longestIndex], 0, -$drop);
                $push(implode(' ', $variant));
            }
        }

        if (count($significant) >= 2) {
            foreach ($significant as $index => $token) {
                if ($index === $longestIndex || strlen($token) < 4) {
                    continue;
                }

                $without = $significant;
                unset($without[$index]);
                $joined = implode(' ', $without);
                if (strlen(str_replace(' ', '', $joined)) >= 6) {
                    $push($joined);
                }
            }
        }

        return array_slice(array_keys($candidates), 0, $limit);
    }

    private static function tokenMatchScore(string $query, string $title): float
    {
        $queryTokens = explode(' ', $query);
        $titleTokens = explode(' ', $title);

        if ($queryTokens === [] || $titleTokens === []) {
            return 0.0;
        }

        $allScore = self::averageBestTokenScore($queryTokens, $titleTokens);
        $significant = array_values(array_filter(
            $queryTokens,
            static fn(string $token): bool => !isset(self::STOPWORDS[$token])
        ));

        if ($significant === []) {
            return $allScore;
        }

        $scores = [];
        foreach ($significant as $queryToken) {
            $best = 0.0;
            foreach ($titleTokens as $titleToken) {
                $best = max($best, self::tokenSimilarity($queryToken, $titleToken));
            }
            $scores[] = $best;
        }

        $arith = array_sum($scores) / count($scores);
        $min   = min($scores);
        $geo   = 1.0;
        foreach ($scores as $score) {
            $geo *= max($score, 0.05);
        }
        $geo = $geo ** (1 / count($scores));

        // A weak leftover word (the typo vs an unrelated title) must pull the
        // score down, otherwise "Hill House" matches every house-on-a-hill film.
        $significantScore = ($arith * 0.45) + ($geo * 0.35) + ($min * 0.20);

        return ($allScore * 0.20) + ($significantScore * 0.80);
    }

    /**
     * @param list<string> $queryTokens
     * @param list<string> $titleTokens
     */
    private static function averageBestTokenScore(array $queryTokens, array $titleTokens): float
    {
        $sum = 0.0;

        foreach ($queryTokens as $queryToken) {
            $best = 0.0;
            foreach ($titleTokens as $titleToken) {
                $best = max($best, self::tokenSimilarity($queryToken, $titleToken));
            }
            $sum += $best;
        }

        return $sum / count($queryTokens);
    }

    private static function tokenSimilarity(string $left, string $right): float
    {
        if ($left === $right) {
            return 1.0;
        }

        $leftLen  = strlen($left);
        $rightLen = strlen($right);
        if ($leftLen === 0 || $rightLen === 0) {
            return 0.0;
        }

        $ratio = self::levenshteinRatio($left, $right);
        $min   = min($leftLen, $rightLen);
        $max   = max($leftLen, $rightLen);

        if ($min >= 3 && (str_starts_with($left, $right) || str_starts_with($right, $left))) {
            $ratio = max($ratio, 0.88 * ($min / $max));
        }

        if ($min >= 4) {
            $leftSound  = metaphone($left);
            $rightSound = metaphone($right);
            if ($leftSound !== '' && $leftSound === $rightSound) {
                $ratio = max($ratio, 0.86);
            }
        }

        return $ratio;
    }

    private static function levenshteinRatio(string $left, string $right): float
    {
        $max = max(strlen($left), strlen($right));
        if ($max === 0) {
            return 1.0;
        }

        if ($max > 255) {
            $left  = substr($left, 0, 255);
            $right = substr($right, 0, 255);
            $max   = 255;
        }

        return 1.0 - (levenshtein($left, $right) / $max);
    }
}
