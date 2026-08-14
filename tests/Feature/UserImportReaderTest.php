<?php

use App\Models\User;
use App\Support\UserImportReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * @param  list<array<int, string>>  $rows
 */
function makeImportFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Ism', 'Login', 'Parol']));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

test('it classifies a row as new when the username is free', function () {
    $path = makeImportFile([['Alisher Nurmatov', 'alisher.nurmatov', '480371']]);

    $rows = UserImportReader::parse($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['status'])->toBe('new')
        ->and($rows[0]['name'])->toBe('Alisher Nurmatov')
        ->and($rows[0]['username'])->toBe('alisher.nurmatov')
        ->and($rows[0]['password'])->toBe('480371');

    unlink($path);
});

test('it lowercases usernames so they match how the app stores them', function () {
    $path = makeImportFile([['Jane Doe', 'Jane.Doe', '123456']]);

    $rows = UserImportReader::parse($path);

    expect($rows[0]['username'])->toBe('jane.doe');

    unlink($path);
});

test('it flags a username that already exists in the database', function () {
    User::factory()->create(['username' => 'taken.login']);
    $path = makeImportFile([['Someone New', 'taken.login', '111111']]);

    $rows = UserImportReader::parse($path);

    expect($rows[0]['status'])->toBe('exists');

    unlink($path);
});

test('it flags the second occurrence of a username repeated within the file', function () {
    $path = makeImportFile([
        ['First Copy', 'dup.login', '111111'],
        ['Second Copy', 'dup.login', '222222'],
    ]);

    $rows = UserImportReader::parse($path);

    expect($rows[0]['status'])->toBe('new')
        ->and($rows[1]['status'])->toBe('duplicate');

    unlink($path);
});

test('it flags a row missing a required field as invalid', function () {
    $path = makeImportFile([
        ['', 'no.name', '111111'],
        ['No Login', '', '111111'],
        ['No Password', 'no.password', ''],
    ]);

    $rows = UserImportReader::parse($path);

    expect($rows)->toHaveCount(3)
        ->and(collect($rows)->pluck('status')->all())->toBe(['invalid', 'invalid', 'invalid']);

    unlink($path);
});

test('it skips fully blank rows without reporting them', function () {
    $path = makeImportFile([
        ['Real Row', 'real.row', '111111'],
        ['', '', ''],
    ]);

    $rows = UserImportReader::parse($path);

    expect($rows)->toHaveCount(1);

    unlink($path);
});

test('it reports the original excel row number for each entry', function () {
    $path = makeImportFile([
        ['First', 'first.row', '111111'],
        ['Second', 'second.row', '222222'],
    ]);

    $rows = UserImportReader::parse($path);

    expect($rows[0]['row'])->toBe(2)
        ->and($rows[1]['row'])->toBe(3);

    unlink($path);
});
