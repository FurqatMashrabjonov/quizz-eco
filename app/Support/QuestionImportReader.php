<?php

namespace App\Support;

use App\Models\Question;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Parses a question bank export (Savol, Variant A-D, Javob columns, in that
 * order) into rows classified for preview, without touching the database.
 * The "Javob" column names the correct option by letter (a/b/c/d).
 */
class QuestionImportReader
{
    /**
     * @return list<array{row: int, body: string, options: array{a: string, b: string, c: string, d: string}, answer: string, status: string}>
     */
    public static function parse(string $path): array
    {
        $raw = self::readCells($path);

        // One query for every question body in the file, instead of one per row.
        $bodies = collect($raw)->pluck('body')->filter()->unique()->values();
        $existing = Question::query()->whereIn('body', $bodies)->pluck('body')->flip();

        $seen = [];
        $rows = [];

        foreach ($raw as $entry) {
            $isValid = $entry['body'] !== ''
                && $entry['options']['a'] !== ''
                && $entry['options']['b'] !== ''
                && $entry['options']['c'] !== ''
                && $entry['options']['d'] !== ''
                && in_array($entry['answer'], ['a', 'b', 'c', 'd'], true);

            $status = match (true) {
                ! $isValid => 'invalid',
                isset($seen[$entry['body']]) => 'duplicate',
                isset($existing[$entry['body']]) => 'exists',
                default => 'new',
            };

            if ($status === 'new') {
                $seen[$entry['body']] = true;
            }

            $rows[] = [...$entry, 'status' => $status];
        }

        return $rows;
    }

    /**
     * @return list<array{row: int, body: string, options: array{a: string, b: string, c: string, d: string}, answer: string}>
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
                $body = trim((string) ($cells[0]?->getValue() ?? ''));
                $a = trim((string) ($cells[1]?->getValue() ?? ''));
                $b = trim((string) ($cells[2]?->getValue() ?? ''));
                $c = trim((string) ($cells[3]?->getValue() ?? ''));
                $d = trim((string) ($cells[4]?->getValue() ?? ''));
                $answer = mb_strtolower(trim((string) ($cells[5]?->getValue() ?? '')));

                if ($body === '' && $a === '' && $b === '' && $c === '' && $d === '' && $answer === '') {
                    continue; // fully blank row, not worth reporting
                }

                $rows[] = [
                    'row' => $index,
                    'body' => $body,
                    'options' => ['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d],
                    'answer' => $answer,
                ];
            }
        }

        $reader->close();

        return $rows;
    }
}
