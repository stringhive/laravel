<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeScanDir(array $files): string
{
    $dir = sys_get_temp_dir().'/stringhive-audit-'.uniqid();
    mkdir($dir, 0755, true);

    foreach ($files as $name => $content) {
        file_put_contents($dir.'/'.$name, $content);
    }

    return $dir;
}

function auditResponse(array $keys): array
{
    return ['keys' => $keys, 'total' => count($keys)];
}

// ---------------------------------------------------------------------------
// Laravel namespace matching (filename.key)
// ---------------------------------------------------------------------------

it('does not flag a key as orphaned when code uses filename.key notation', function () {
    $dir = makeScanDir([
        'blade.php' => "<?php echo __('sparky.company_team_create_success'); ?>",
    ]);

    Http::fake(['*' => Http::response(auditResponse([
        ['key' => 'company_team_create_success', 'file' => 'sparky.php'],
    ]))]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--format' => 'json',
    ])
        ->assertExitCode(0);

    $output = $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--format' => 'json',
    ])->run();

    Http::assertSentCount(2);

    removeTempDir($dir);
});

it('reports zero orphaned and zero missing when file-namespaced keys match', function () {
    $dir = makeScanDir([
        'page.php' => "<?php\nreturn __('sparky.company_team_create_success') . __('sparky.another_key');",
    ]);

    Http::fake(['*' => Http::response(auditResponse([
        ['key' => 'company_team_create_success', 'file' => 'sparky.php'],
        ['key' => 'another_key', 'file' => 'sparky.php'],
    ]))]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--format' => 'json',
        '--fail-on-orphaned' => true,
        '--fail-on-missing' => true,
    ])->assertExitCode(0);

    removeTempDir($dir);
});

it('still reports genuinely missing keys', function () {
    $dir = makeScanDir([
        'page.php' => "<?php echo __('sparky.existing_key') . __('sparky.unknown_key');",
    ]);

    Http::fake(['*' => Http::response(auditResponse([
        ['key' => 'existing_key', 'file' => 'sparky.php'],
    ]))]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--format' => 'json',
        '--fail-on-missing' => true,
    ])->assertExitCode(1);

    removeTempDir($dir);
});

it('still reports genuinely orphaned keys', function () {
    $dir = makeScanDir([
        'page.php' => "<?php echo __('sparky.existing_key');",
    ]);

    Http::fake(['*' => Http::response(auditResponse([
        ['key' => 'existing_key', 'file' => 'sparky.php'],
        ['key' => 'orphaned_key', 'file' => 'sparky.php'],
    ]))]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--format' => 'json',
        '--fail-on-orphaned' => true,
    ])->assertExitCode(1);

    removeTempDir($dir);
});

it('matches keys when the file path is multiple levels deep', function () {
    $dir = makeScanDir([
        'page.php' => "<?php echo __('admin/sparky.company_team_create_success');",
    ]);

    Http::fake(['*' => Http::response(auditResponse([
        ['key' => 'company_team_create_success', 'file' => 'admin/sparky.php'],
    ]))]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--format' => 'json',
        '--fail-on-orphaned' => true,
        '--fail-on-missing' => true,
    ])->assertExitCode(0);

    removeTempDir($dir);
});

it('handles keys that already include the namespace prefix', function () {
    $dir = makeScanDir([
        'page.php' => "<?php echo __('sparky.existing_key');",
    ]);

    // API already returns the fully-qualified key
    Http::fake(['*' => Http::response(auditResponse([
        ['key' => 'sparky.existing_key', 'file' => 'sparky.php'],
    ]))]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--format' => 'json',
        '--fail-on-orphaned' => true,
        '--fail-on-missing' => true,
    ])->assertExitCode(0);

    removeTempDir($dir);
});
