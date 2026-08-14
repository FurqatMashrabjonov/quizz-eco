<?php

use App\Models\Question;
use App\Support\QuestionImportReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * @param  list<array<int, string>>  $rows
 */
function makeQuestionImportFile(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Savol', 'Variant A', 'Variant B', 'Variant C', 'Variant D', 'Javob']));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

test('it classifies a row as new when the question body is free', function () {
    $path = makeQuestionImportFile([['2+2 nechiga teng?', '3', '4', '5', '6', 'b']]);

    $rows = QuestionImportReader::parse($path);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['status'])->toBe('new')
        ->and($rows[0]['body'])->toBe('2+2 nechiga teng?')
        ->and($rows[0]['options'])->toBe(['a' => '3', 'b' => '4', 'c' => '5', 'd' => '6'])
        ->and($rows[0]['answer'])->toBe('b');

    unlink($path);
});

test('it captures the optional explanation column', function () {
    $path = makeQuestionImportFile([['Savol', '1', '2', '3', '4', 'a', 'Huquqiy asos: ORQ-1036, 17-modda']]);

    $rows = QuestionImportReader::parse($path);

    expect($rows[0]['explanation'])->toBe('Huquqiy asos: ORQ-1036, 17-modda');

    unlink($path);
});

test('a missing explanation does not make the row invalid', function () {
    $path = makeQuestionImportFile([['Savol', '1', '2', '3', '4', 'a']]);

    $rows = QuestionImportReader::parse($path);

    expect($rows[0]['status'])->toBe('new')
        ->and($rows[0]['explanation'])->toBe('');

    unlink($path);
});

test('it lowercases the answer letter', function () {
    $path = makeQuestionImportFile([['Savol', '1', '2', '3', '4', 'C']]);

    $rows = QuestionImportReader::parse($path);

    expect($rows[0]['answer'])->toBe('c');

    unlink($path);
});

test('it still imports a question body that already exists in the database', function () {
    Question::factory()->create(['body' => 'Takrorlangan savol']);
    $path = makeQuestionImportFile([['Takrorlangan savol', '1', '2', '3', '4', 'a']]);

    $rows = QuestionImportReader::parse($path);

    expect($rows[0]['status'])->toBe('new');

    unlink($path);
});

test('it still imports every occurrence of a question repeated within the file', function () {
    $path = makeQuestionImportFile([
        ['Bir xil savol', '1', '2', '3', '4', 'a'],
        ['Bir xil savol', '1', '2', '3', '4', 'b'],
    ]);

    $rows = QuestionImportReader::parse($path);

    expect($rows[0]['status'])->toBe('new')
        ->and($rows[1]['status'])->toBe('new');

    unlink($path);
});

test('it flags a row missing a required field as invalid', function () {
    $path = makeQuestionImportFile([
        ['', '1', '2', '3', '4', 'a'],
        ['No option D', '1', '2', '3', '', 'a'],
        ['No answer', '1', '2', '3', '4', ''],
        ['Bad answer letter', '1', '2', '3', '4', 'e'],
    ]);

    $rows = QuestionImportReader::parse($path);

    expect($rows)->toHaveCount(4)
        ->and(collect($rows)->pluck('status')->all())->toBe(['invalid', 'invalid', 'invalid', 'invalid']);

    unlink($path);
});

test('it skips fully blank rows without reporting them', function () {
    $path = makeQuestionImportFile([
        ['Real Row', '1', '2', '3', '4', 'a'],
        ['', '', '', '', '', ''],
    ]);

    $rows = QuestionImportReader::parse($path);

    expect($rows)->toHaveCount(1);

    unlink($path);
});

test('it reports the original excel row number for each entry', function () {
    $path = makeQuestionImportFile([
        ['First', '1', '2', '3', '4', 'a'],
        ['Second', '1', '2', '3', '4', 'b'],
    ]);

    $rows = QuestionImportReader::parse($path);

    expect($rows[0]['row'])->toBe(2)
        ->and($rows[1]['row'])->toBe(3);

    unlink($path);
});
