<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const PHP_FIXTURES = __DIR__.'/../fixtures/lang/php';
const JSON_FIXTURES = __DIR__.'/../fixtures/lang/json';

function importOk(): array
{
    return ['created' => 2, 'updated' => 0, 'unchanged' => 0, 'translations_cleared' => 0];
}

function translationOk(): array
{
    return ['created' => 2, 'updated' => 0, 'skipped' => 0, 'unknown' => 0];
}

// ---------------------------------------------------------------------------
// PHP-style
// ---------------------------------------------------------------------------

it('pushes PHP source strings', function () {
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => PHP_FIXTURES,
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' &&
        str_contains($r->url(), '/api/hives/my-app/strings') &&
        isset($r->data()['files']['app.php']) &&
        isset($r->data()['files']['auth.php']) &&
        $r->data()['conflict_strategy'] === 'keep'
    );
});

it('pushes PHP translations for non-source locales', function () {
    Http::fake(['*' => Http::sequence()
        ->push(importOk())       // source strings
        ->push(translationOk()), // es translations
    ]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--with-translations' => true,
        '--lang-path' => PHP_FIXTURES,
    ])->assertExitCode(0);

    Http::assertSentCount(2);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/translations/es')
    );
});

it('uses syncStrings when --sync flag is set', function () {
    Http::fake(['*' => Http::response(
        array_merge(importOk(), ['deleted' => 0])
    )]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--sync' => true,
        '--lang-path' => PHP_FIXTURES,
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT');
});

it('passes conflict-strategy to the API', function () {
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--conflict-strategy' => 'clear',
        '--lang-path' => PHP_FIXTURES,
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $r) => isset($r->data()['conflict_strategy']) &&
        $r->data()['conflict_strategy'] === 'clear'
    );
});

// ---------------------------------------------------------------------------
// JSON-style
// ---------------------------------------------------------------------------

it('pushes JSON source strings', function () {
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => JSON_FIXTURES,
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' &&
        isset($r->data()['files']['en.json']) &&
        $r->data()['files']['en.json']['Hello'] === 'Hello'
    );
});

it('uses source locale as file key when pushing JSON translations', function () {
    Http::fake(['*' => Http::sequence()
        ->push(importOk())
        ->push(translationOk()),
    ]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--with-translations' => true,
        '--lang-path' => JSON_FIXTURES,
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/translations/es') &&
        isset($r->data()['files']['en.json'])
    );
});

// ---------------------------------------------------------------------------
// Include
// ---------------------------------------------------------------------------

it('only pushes files matching --include patterns', function () {
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => PHP_FIXTURES,
        '--include' => ['app.php'],
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $r) => isset($r->data()['files']['app.php']) &&
        ! isset($r->data()['files']['auth.php'])
    );
});

it('merges config include with --include option during push', function () {
    config(['stringhive.include' => ['app.php']]);
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => PHP_FIXTURES,
        '--include' => ['auth.php'],
    ])->assertExitCode(0);

    // config includes app.php, CLI includes auth.php → both are pushed
    Http::assertSent(fn (Request $r) => isset($r->data()['files']['app.php']) &&
        isset($r->data()['files']['auth.php'])
    );
});

// ---------------------------------------------------------------------------
// Exclude
// ---------------------------------------------------------------------------

it('skips files matching --exclude patterns', function () {
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => PHP_FIXTURES,
        '--exclude' => ['auth.php'],
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $r) => isset($r->data()['files']['app.php']) &&
        ! isset($r->data()['files']['auth.php'])
    );
});

it('merges config exclude with --exclude option', function () {
    // config excludes app.php, CLI excludes auth.php — both files excluded → nothing pushed
    config(['stringhive.exclude' => ['app.php']]);
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => PHP_FIXTURES,
        '--exclude' => ['auth.php'],
    ])->assertExitCode(0);

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// Lang path config
// ---------------------------------------------------------------------------

it('uses lang_path from config when no --lang-path option is given', function () {
    config(['stringhive.lang_path' => PHP_FIXTURES]);
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $r) => isset($r->data()['files']['app.php']));
});

it('--lang-path option takes precedence over config lang_path', function () {
    config(['stringhive.lang_path' => '/wrong/path']);
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => PHP_FIXTURES,
    ])->assertExitCode(0);

    Http::assertSent(fn (Request $r) => isset($r->data()['files']['app.php']));
});

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------

it('fails when lang path does not exist', function () {
    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => '/this/path/does/not/exist',
    ])->assertExitCode(1);

    Http::assertNothingSent();
});

it('returns failure on 401', function () {
    Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => PHP_FIXTURES,
    ])->assertExitCode(1);
});

it('returns failure when string limit is reached', function () {
    Http::fake(['*' => Http::response(['message' => 'String limit reached for your plan.'], 422)]);

    $this->artisan('stringhive:push', [
        'hive' => 'my-app',
        '--lang-path' => PHP_FIXTURES,
    ])->assertExitCode(1);
});

it('uses hive from config when no argument is given', function () {
    config(['stringhive.hive' => 'config-hive']);
    Http::fake(['*' => Http::response(importOk())]);

    $this->artisan('stringhive:push', [
        '--lang-path' => PHP_FIXTURES,
    ])->assertExitCode(0);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/hives/config-hive/strings'));
});

it('fails when no hive argument and no config hive', function () {
    config(['stringhive.hive' => null]);

    $this->artisan('stringhive:push', [
        '--lang-path' => PHP_FIXTURES,
    ])->assertExitCode(1);

    Http::assertNothingSent();
});
