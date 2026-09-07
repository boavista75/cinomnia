<?php

declare(strict_types=1);

namespace Cinomnia\Storage;

use RuntimeException;

/**
 * File-backed JSON store with exclusive/shared locking.
 *
 * Replaces MySQL for this private single-user app.
 */
final class JsonStore
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? (defined('DATA_STORE_PATH')
            ? DATA_STORE_PATH
            : dirname(__DIR__, 2) . '/data/store.json');
    }

    /**
     * @return array{
     *     next_list_id: int,
     *     next_item_id: int,
     *     lists: list<array<string, mixed>>,
     *     ratings: array<string, array<string, mixed>>,
     *     notes: array<string, array<string, mixed>>
     * }
     */
    public function emptyState(): array
    {
        return [
            'next_list_id' => 1,
            'next_item_id' => 1,
            'lists'        => [],
            'ratings'      => [],
            'notes'        => [],
        ];
    }

    /**
     * @return array{
     *     next_list_id: int,
     *     next_item_id: int,
     *     lists: list<array<string, mixed>>,
     *     ratings: array<string, array<string, mixed>>,
     *     notes: array<string, array<string, mixed>>
     * }
     */
    public function read(): array
    {
        $this->ensureFile();

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not read the data store.');
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Could not lock the data store for reading.');
            }

            $raw = stream_get_contents($handle);

            return $this->decode($raw === false ? '' : $raw);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param callable(array): mixed $callback Receives the store by reference.
     */
    public function mutate(callable $callback): mixed
    {
        $this->ensureFile();

        $handle = fopen($this->path, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('Could not open the data store.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Could not lock the data store for writing.');
            }

            $raw  = stream_get_contents($handle);
            $data = $this->decode($raw === false ? '' : $raw);

            $result = $callback($data);

            $json = json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $json) === false) {
                throw new RuntimeException('Could not write the data store.');
            }
            fflush($handle);

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @param 'movie'|'tv' $mediaType
     */
    public static function mediaKey(int $tmdbId, string $mediaType): string
    {
        return $mediaType . ':' . $tmdbId;
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function ensureFile(): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the data directory.');
        }

        if (!is_file($this->path)) {
            $json = json_encode(
                $this->emptyState(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            if (file_put_contents($this->path, $json, LOCK_EX) === false) {
                throw new RuntimeException('Could not create the data store.');
            }
        }
    }

    /**
     * @return array{
     *     next_list_id: int,
     *     next_item_id: int,
     *     lists: list<array<string, mixed>>,
     *     ratings: array<string, array<string, mixed>>,
     *     notes: array<string, array<string, mixed>>
     * }
     */
    private function decode(string $raw): array
    {
        $empty = $this->emptyState();

        if (trim($raw) === '') {
            return $empty;
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return $empty;
        }

        return [
            'next_list_id' => max(1, (int) ($decoded['next_list_id'] ?? 1)),
            'next_item_id' => max(1, (int) ($decoded['next_item_id'] ?? 1)),
            'lists'        => array_values($decoded['lists'] ?? []),
            'ratings'      => is_array($decoded['ratings'] ?? null) ? $decoded['ratings'] : [],
            'notes'        => is_array($decoded['notes'] ?? null) ? $decoded['notes'] : [],
        ];
    }
}
