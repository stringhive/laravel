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

// ---------------------------------------------------------------------------
// --fail-on-unapproved
// ---------------------------------------------------------------------------

function hiveResponse(array $stats): array
{
    return ['slug' => 'my-app', 'name' => 'My App', 'stats' => $stats];
}

it('exits 0 when all locales meet the default 100% approval threshold', function () {
    $dir = makeScanDir(['page.php' => "<?php echo __('hello');"]);

    Http::fake([
        '*/keys*' => Http::response(auditResponse([])),
        '*'       => Http::response(hiveResponse([
            'fr' => ['approved_percent' => 100.0],
            'de' => ['approved_percent' => 100.0],
        ])),
    ]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--fail-on-unapproved' => true,
    ])->assertExitCode(0);

    removeTempDir($dir);
});

it('exits 1 when a locale is below 100% approval', function () {
    $dir = makeScanDir(['page.php' => "<?php echo __('hello');"]);

    Http::fake([
        '*/keys*' => Http::response(auditResponse([])),
        '*'       => Http::response(hiveResponse([
            'fr' => ['approved_percent' => 80.0],
            'de' => ['approved_percent' => 100.0],
        ])),
    ]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--fail-on-unapproved' => true,
    ])->assertExitCode(1);

    removeTempDir($dir);
});

it('passes when locale is at exactly --min-approved threshold', function () {
    $dir = makeScanDir(['page.php' => "<?php echo __('hello');"]);

    Http::fake([
        '*/keys*' => Http::response(auditResponse([])),
        '*'       => Http::response(hiveResponse([
            'fr' => ['approved_percent' => 95.0],
        ])),
    ]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--fail-on-unapproved' => true,
        '--min-approved' => '95',
    ])->assertExitCode(0);

    removeTempDir($dir);
});

it('fails when locale is below --min-approved threshold', function () {
    $dir = makeScanDir(['page.php' => "<?php echo __('hello');"]);

    Http::fake([
        '*/keys*' => Http::response(auditResponse([])),
        '*'       => Http::response(hiveResponse([
            'fr' => ['approved_percent' => 94.9],
        ])),
    ]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--fail-on-unapproved' => true,
        '--min-approved' => '95',
    ])->assertExitCode(1);

    removeTempDir($dir);
});

it('scopes the unapproved check to --locale filter and ignores unlisted locales', function () {
    $dir = makeScanDir(['page.php' => "<?php echo __('hello');"]);

    Http::fake([
        '*/keys*' => Http::response(auditResponse([])),
        '*'       => Http::response(hiveResponse([
            'fr' => ['approved_percent' => 50.0],
            'de' => ['approved_percent' => 100.0],
            'es' => ['approved_percent' => 100.0],
        ])),
    ]);

    // Only checking de and es — fr (which would fail) is excluded
    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--fail-on-unapproved' => true,
        '--locale' => 'de,es',
    ])->assertExitCode(0);

    removeTempDir($dir);
});

it('fails when a --locale-filtered locale is below threshold', function () {
    $dir = makeScanDir(['page.php' => "<?php echo __('hello');"]);

    Http::fake([
        '*/keys*' => Http::response(auditResponse([])),
        '*'       => Http::response(hiveResponse([
            'fr' => ['approved_percent' => 80.0],
            'de' => ['approved_percent' => 100.0],
        ])),
    ]);

    $this->artisan('stringhive:audit', [
        'hive' => 'my-app',
        '--scan-path' => $dir,
        '--fail-on-unapproved' => true,
        '--locale' => 'fr,de',
    ])->assertExitCode(1);

    removeTempDir($dir);
});
