<?php

declare(strict_types=1);

namespace Cinomnia\Auth;

use Cinomnia\Storage\JsonStore;

/**
 * Private per-title notes (replaces the old public comments thread).
 */
final class NoteService
{
    public const MAX_BODY_LENGTH = 8000;

    private JsonStore $store;

    public function __construct(JsonStore $store)
    {
        $this->store = $store;
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{body: string, updated_at: string|null}
     */
    public function getNote(int $tmdbId, string $mediaType): array
    {
        if (!$this->isValidMediaType($mediaType) || $tmdbId <= 0) {
            return ['body' => '', 'updated_at' => null];
        }

        $key  = JsonStore::mediaKey($tmdbId, $mediaType);
        $note = $this->store->read()['notes'][$key] ?? null;

        if (!is_array($note)) {
            return ['body' => '', 'updated_at' => null];
        }

        return [
            'body'       => (string) ($note['body'] ?? ''),
            'updated_at' => isset($note['updated_at']) ? (string) $note['updated_at'] : null,
        ];
    }

    /**
     * @param 'movie'|'tv' $mediaType
     * @return array{success: bool, message: string, body?: string, updated_at?: string|null}
     */
    public function saveNote(int $tmdbId, string $mediaType, string $body): array
    {
        if (!$this->isValidMediaType($mediaType)) {
            return ['success' => false, 'message' => 'Invalid media type.'];
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'message' => 'Invalid TMDB ID.'];
        }

        $body = trim($body);

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            return [
                'success' => false,
                'message' => 'Notes must be ' . self::MAX_BODY_LENGTH . ' characters or fewer.',
            ];
        }

        return $this->store->mutate(function (array &$data) use ($tmdbId, $mediaType, $body): array {
            $key = JsonStore::mediaKey($tmdbId, $mediaType);

            if ($body === '') {
                unset($data['notes'][$key]);

                return [
                    'success'    => true,
                    'message'    => 'Note cleared.',
                    'body'       => '',
                    'updated_at' => null,
                ];
            }

            $updatedAt = JsonStore::now();
            $data['notes'][$key] = [
                'body'       => $body,
                'updated_at' => $updatedAt,
            ];

            return [
                'success'    => true,
                'message'    => 'Notes saved.',
                'body'       => $body,
                'updated_at' => $updatedAt,
            ];
        });
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    private function isValidMediaType(string $mediaType): bool
    {
        return in_array($mediaType, ['movie', 'tv'], true);
    }
}
