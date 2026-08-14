<?php

namespace App\Support;

use OpenSpout\Reader\XLSX\Reader;

/**
 * Parses a question bank export (Savol, Variant A-D, Javob, Izoh columns, in
 * that order) into rows classified for preview, without touching the
 * database. The "Javob" column names the correct option by letter (a/b/c/d).
 * "Izoh" is an optional explanation and may be blank.
 *
 * Questions with an identical body are intentionally allowed to import more
 * than once (both within the file and against the existing bank) since the
 * question bank permits duplicate wording.
 */
class QuestionImportReader
{
    /**
     * @return list<array{row: int, body: string, options: array{a: string, b: string, c: string, d: string}, answer: string, explanation: string, status: string}>
     */
    public static function parse(string $path): array
    {
        $raw = self::readCells($path);

        $rows = [];

        foreach ($raw as $entry) {
            $isValid = $entry['body'] !== ''
                && $entry['options']['a'] !== ''
                && $entry['options']['b'] !== ''
                && $entry['options']['c'] !== ''
                && $entry['options']['d'] !== ''
                && in_array($entry['answer'], ['a', 'b', 'c', 'd'], true);

            $status = $isValid ? 'new' : 'invalid';

            $rows[] = [...$entry, 'status' => $status];
        }

        return $rows;
    }

    /**
     * @return list<array{row: int, body: string, options: array{a: string, b: string, c: string, d: string}, answer: string, explanation: string}>
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
                $explanation = trim((string) (($cells[6] ?? null)?->getValue() ?? ''));

                if ($body === '' && $a === '' && $b === '' && $c === '' && $d === '' && $answer === '') {
                    continue; // fully blank row, not worth reporting
                }

                $rows[] = [
                    'row' => $index,
                    'body' => $body,
                    'options' => ['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d],
                    'answer' => $answer,
                    'explanation' => $explanation,
                ];
            }
        }

        $reader->close();

        return $rows;
    }
}
