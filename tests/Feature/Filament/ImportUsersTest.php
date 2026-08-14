<?php

use App\Filament\Resources\Users\Pages\ImportUsers;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * @param  list<array<int, string>>  $rows
 */
function uploadableImportFile(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';

    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['Ism', 'Login', 'Parol']));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    $fake = UploadedFile::fake()->createWithContent('xodimlar.xlsx', file_get_contents($path));
    unlink($path);

    return $fake;
}

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('uploading a file shows a preview before anything is saved', function () {
    Livewire::test(ImportUsers::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableImportFile([
            ['Alisher Nurmatov', 'alisher.nurmatov', '480371'],
        ])])
        ->call('preview')
        ->assertSet('parsed', true)
        ->assertSee('Alisher Nurmatov')
        ->assertSee('alisher.nurmatov')
        ->assertSee('480371');

    expect(User::query()->where('username', 'alisher.nurmatov')->exists())->toBeFalse();
});

test('confirming the import creates only the new rows', function () {
    User::factory()->create(['username' => 'already.here']);

    Livewire::test(ImportUsers::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableImportFile([
            ['New Person', 'new.person', '111111'],
            ['Existing Person', 'already.here', '222222'],
            ['', 'missing.name', '333333'],
        ])])
        ->call('preview')
        ->call('import');

    $created = User::query()->where('username', 'new.person')->first();

    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('New Person')
        ->and($created->role)->toBe('user')
        ->and($created->plain_password)->toBe('111111')
        ->and(Hash::check('111111', $created->password))->toBeTrue()
        ->and(User::query()->where('username', 'missing.name')->exists())->toBeFalse();
});

test('the existing user is left untouched by a repeat import', function () {
    $existing = User::factory()->create(['username' => 'already.here', 'plain_password' => 'original']);

    Livewire::test(ImportUsers::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableImportFile([
            ['Renamed', 'already.here', 'different-password'],
        ])])
        ->call('preview')
        ->call('import');

    expect($existing->refresh()->plain_password)->toBe('original');
});

test('importing with no new rows does nothing', function () {
    User::factory()->create(['username' => 'already.here']);
    $countBefore = User::query()->count();

    Livewire::test(ImportUsers::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableImportFile([
            ['Existing', 'already.here', '111111'],
        ])])
        ->call('preview')
        ->call('import');

    expect(User::query()->count())->toBe($countBefore);
});

test('starting over clears the preview without importing anything', function () {
    Livewire::test(ImportUsers::class)
        ->set('data.file', [])
        ->upload('data.file', [uploadableImportFile([
            ['Someone', 'some.one', '111111'],
        ])])
        ->call('preview')
        ->assertSet('parsed', true)
        ->call('resetImport')
        ->assertSet('parsed', false)
        ->assertSet('rows', []);

    expect(User::query()->where('username', 'some.one')->exists())->toBeFalse();
});
