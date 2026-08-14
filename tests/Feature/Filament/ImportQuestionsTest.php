<?php

use App\Filament\Resources\Questions\Pages\ImportQuestions;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * @param  list<array<int, string>>  $rows
 */
function uploadableQuestionImportFile(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Savol', 'Variant A', 'Variant B', 'Variant C', 'Variant D', 'Javob']));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    $fake = UploadedFile::fake()->createWithContent('savollar.xlsx', file_get_contents($path));
    unlink($path);

    return $fake;
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('uploading a file shows a preview before anything is saved', function () {
    Livewire::test(ImportQuestions::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableQuestionImportFile([
            ['2+2 nechiga teng?', '3', '4', '5', '6', 'b'],
        ])])
        ->call('preview')
        ->assertSet('parsed', true)
        ->assertSee('2+2 nechiga teng?')
        ->assertSee('4');

    expect(Question::query()->where('body', '2+2 nechiga teng?')->exists())->toBeFalse();
});

test('confirming the import creates only the new rows and marks the right option correct', function () {
    Question::factory()->create(['body' => 'Allaqachon mavjud savol']);

    Livewire::test(ImportQuestions::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableQuestionImportFile([
            ['Yangi savol', '1', '2', '3', '4', 'c'],
            ['Allaqachon mavjud savol', '1', '2', '3', '4', 'a'],
            ['', 'no', 'body', 'here', '', 'a'],
        ])])
        ->call('preview')
        ->call('import');

    $created = Question::query()->where('body', 'Yangi savol')->with('options')->first();

    expect($created)->not->toBeNull()
        ->and($created->options)->toHaveCount(4)
        ->and($created->options->firstWhere('is_correct', true)->body)->toBe('3');
});

test('the explanation column is saved on the question', function () {
    Livewire::test(ImportQuestions::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableQuestionImportFile([
            ['Yangi savol', '1', '2', '3', '4', 'a', 'Huquqiy asos: ORQ-1036, 17-modda'],
        ])])
        ->call('preview')
        ->call('import');

    expect(Question::query()->where('body', 'Yangi savol')->value('explanation'))
        ->toBe('Huquqiy asos: ORQ-1036, 17-modda');
});

test('the existing question is left untouched by a repeat import', function () {
    $existing = Question::factory()->create(['body' => 'Allaqachon mavjud savol']);
    $optionCountBefore = $existing->options()->count();

    Livewire::test(ImportQuestions::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableQuestionImportFile([
            ['Allaqachon mavjud savol', '1', '2', '3', '4', 'a'],
        ])])
        ->call('preview')
        ->call('import');

    expect($existing->options()->count())->toBe($optionCountBefore);
});

test('importing with no new rows does nothing', function () {
    Question::factory()->create(['body' => 'Allaqachon mavjud savol']);
    $countBefore = Question::query()->count();

    Livewire::test(ImportQuestions::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableQuestionImportFile([
            ['Allaqachon mavjud savol', '1', '2', '3', '4', 'a'],
        ])])
        ->call('preview')
        ->call('import');

    expect(Question::query()->count())->toBe($countBefore);
});

test('starting over clears the preview without importing anything', function () {
    Livewire::test(ImportQuestions::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableQuestionImportFile([
            ['Yangi savol', '1', '2', '3', '4', 'a'],
        ])])
        ->call('preview')
        ->assertSet('parsed', true)
        ->call('resetImport')
        ->assertSet('parsed', false)
        ->assertSet('rows', []);

    expect(Question::query()->where('body', 'Yangi savol')->exists())->toBeFalse();
});
