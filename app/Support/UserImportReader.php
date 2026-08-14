<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Parses the daily HR export (Ism, Login, Parol columns, in that order) into
 * rows classified for preview, without touching the database.
 */
class UserImportReader
{
    /**
     * @return list<array{row: int, name: string, username: string, password: string, status: string}>
     */
    public static function parse(string $path): array
    {
        $raw = self::readCells($path);

        // One query for every username in the file, instead of one per row.
        $usernames = collect($raw)->pluck('username')->filter()->unique()->values();
        $existing = User::query()->whereIn('username', $usernames)->pluck('username')->flip();

        $seen = [];
        $rows = [];

        foreach ($raw as $entry) {
            $status = match (true) {
                $entry['name'] === '' || $entry['username'] === '' || $entry['password'] === '' => 'invalid',
                isset($seen[$entry['username']]) => 'duplicate',
                isset($existing[$entry['username']]) => 'exists',
                default => 'new',
            };

            if ($status === 'new') {
                $seen[$entry['username']] = true;
            }

            $rows[] = [...$entry, 'status' => $status];
        }

        return $rows;
    }

    /**
     * @return list<array{row: int, name: string, username: string, password: string}>
     */
    private static function readCells(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                if ($index === 1) {
                    continue; // header row
                }

                $cells = $row->getCells();
                $name = trim((string) ($cells[0]?->getValue() ?? ''));
                $username = Str::lower(trim((string) ($cells[1]?->getValue() ?? '')));
                $password = trim((string) ($cells[2]?->getValue() ?? ''));

                if ($name === '' && $username === '' && $password === '') {
                    continue; // fully blank row, not worth reporting
                }

                $rows[] = ['row' => $index, 'name' => $name, 'username' => $username, 'password' => $password];
            }
        }

        $reader->close();

        return $rows;
    }
}
