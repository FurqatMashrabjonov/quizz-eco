<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Builds real .xlsx downloads.
 *
 * CSV was unusable here: Excel splits columns on the list separator of the
 * user's locale, so a comma-delimited file lands entirely in column A for
 * anyone whose Excel expects semicolons. A spreadsheet file has no such
 * ambiguity, and openspout already ships with Filament.
 */
class XlsxExport
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<array<int, string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headings, iterable $rows): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'export').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues($headings, (new Style)->setFontBold()));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return response()
            ->download($path, $filename)
            ->deleteFileAfterSend();
    }
}
