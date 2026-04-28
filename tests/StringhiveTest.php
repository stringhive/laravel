<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Stringhive\Exceptions\AuthenticationException;
use Stringhive\Exceptions\ForbiddenException;
use Stringhive\Exceptions\HiveNotFoundException;
use Stringhive\Exceptions\StringLimitException;
use Stringhive\Exceptions\ValidationException;
use Stringhive\Stringhive;

const BASE_URL = 'https://stringhive.test';
const TOKEN = 'test-token';

function makeClient(): Stringhive
{
    return new Stringhive(BASE_URL, TOKEN);
}

// ---------------------------------------------------------------------------
// locales()
// ---------------------------------------------------------------------------

it('returns locales', function () {
    Http::fake([
        BASE_URL.'/api/locales' => Http::response([
            'locales' => [
                ['code' => 'en', 'name' => 'English', 'region' => 'US', 'rtl' => false, 'is_popular' => true],
                ['code' => 'es', 'name' => 'Spanish', 'region' => 'ES', 'rtl' => false, 'is_popular' => true],
            ],
        ]),
    ]);

    $result = makeClient()->locales();

    expect($result)->toHaveCount(2)
        ->and($result[0]['code'])->toBe('en')
        ->and($result[1]['code'])->toBe('es');
});

it('sends bearer token with locales request', function () {
    Http::fake([BASE_URL.'/*' => Http::response(['locales' => []])]);

    makeClient()->locales();

    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.TOKEN));
});

// ---------------------------------------------------------------------------
// hives()
// ---------------------------------------------------------------------------

it('returns hives', function () {
    Http::fake([
        BASE_URL.'/api/hives' => Http::response([
            'hives' => [
                ['slug' => 'my-app', 'name' => 'My App', 'source_locale' => 'en', 'locales' => ['es'], 'string_count' => 10],
            ],
        ]),
    ]);

    $result = makeClient()->hives();

    expect($result)->toHaveCount(1)
        ->and($result[0]['slug'])->toBe('my-app');
});

// ---------------------------------------------------------------------------
// hive()
// ---------------------------------------------------------------------------

it('returns hive stats for a slug', function () {
    Http::fake([
        BASE_URL.'/api/hives/my-app' => Http::response([
            'slug' => 'my-app',
            'name' => 'My App',
            'source_locale' => 'en',
            'string_count' => 100,
            'locales' => [
                'es' => ['translated' => 80, 'approved' => 60, 'warning' => 5, 'empty' => 15, 'translated_percent' => 85.0, 'approved_percent' => 60.0],
            ],
        ]),
    ]);

    $result = makeClient()->hive('my-app');

    expect($result['slug'])->toBe('my-app')
        ->and($result['locales']['es']['translated'])->toBe(80);
});

// ---------------------------------------------------------------------------
// strings()
// ---------------------------------------------------------------------------

it('returns paginated source strings', function () {
    Http::fake([
        BASE_URL.'/api/hives/my-app/strings*' => Http::response([
            'data' => [
                ['key' => 'auth.email', 'source_value' => 'Email', 'file' => 'auth.php'],
            ],
            'meta' => ['total' => 1, 'per_page' => 100, 'current_page' => 1, 'last_page' => 1],
        ]),
    ]);

    $result = makeClient()->strings('my-app');

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['key'])->toBe('auth.email')
        ->and($result['meta']['total'])->toBe(1);
});

it('sends file filter query param', function () {
    Http::fake([BASE_URL.'/*' => Http::response(['data' => [], 'meta' => ['total' => 0, 'per_page' => 100, 'current_page' => 1, 'last_page' => 1]])]);

    makeClient()->strings('my-app', 'auth.php');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'file=auth.php'));
});

it('sends per_page and page query params', function () {
    Http::fake([BASE_URL.'/*' => Http::response(['data' => [], 'meta' => ['total' => 0, 'per_page' => 50, 'current_page' => 2, 'last_page' => 2]])]);

    makeClient()->strings('my-app', perPage: 50, page: 2);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'per_page=50') && str_contains($r->url(), 'page=2'));
});

// ---------------------------------------------------------------------------
// allStrings()
// ---------------------------------------------------------------------------

it('paginates through all pages and merges data', function () {
    Http::fake([
        BASE_URL.'/*' => Http::sequence()
            ->push([
                'data' => [['key' => 'a', 'source_value' => 'A', 'file' => 'app.php']],
                'meta' => ['total' => 2, 'per_page' => 500, 'current_page' => 1, 'last_page' => 2],
            ])
            ->push([
                'data' => [['key' => 'b', 'source_value' => 'B', 'file' => 'app.php']],
                'meta' => ['total' => 2, 'per_page' => 500, 'current_page' => 2, 'last_page' => 2],
            ]),
    ]);

    $result = makeClient()->allStrings('my-app');

    expect($result)->toHaveCount(2)
        ->and($result[0]['key'])->toBe('a')
        ->and($result[1]['key'])->toBe('b');
});

it('returns single page without extra requests', function () {
    Http::fake([
        BASE_URL.'/*' => Http::response([
            'data' => [['key' => 'a', 'source_value' => 'A', 'file' => 'app.php']],
            'meta' => ['total' => 1, 'per_page' => 500, 'current_page' => 1, 'last_page' => 1],
        ]),
    ]);

    $result = makeClient()->allStrings('my-app');

    Http::assertSentCount(1);
    expect($result)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// importStrings()
// ---------------------------------------------------------------------------

it('posts source strings and returns import stats', function () {
    Http::fake([
        BASE_URL.'/api/hives/my-app/strings' => Http::response([
            'created' => 45, 'updated' => 12, 'unchanged' => 1188, 'translations_cleared' => 5,
        ]),
    ]);

    $result = makeClient()->importStrings('my-app', ['app.php' => ['title' => 'Hello']]);

    expect($result['created'])->toBe(45)
        ->and($result['updated'])->toBe(12);

    Http::assertSent(function (Request $r) {
        $body = $r->data();

        return $r->method() === 'POST'
            && $body['conflict_strategy'] === 'keep'
            && isset($body['files']['app.php']);
    });
});

it('passes conflict_strategy to import', function () {
    Http::fake([BASE_URL.'/*' => Http::response(['created' => 0, 'updated' => 0, 'unchanged' => 0, 'translations_cleared' => 0])]);

    makeClient()->importStrings('my-app', [], 'clear');

    Http::assertSent(fn (Request $r) => $r->data()['conflict_strategy'] === 'clear');
});

// ---------------------------------------------------------------------------
// syncStrings()
// ---------------------------------------------------------------------------

it('puts source strings and returns sync stats', function () {
    Http::fake([
        BASE_URL.'/api/hives/my-app/strings' => Http::response([
            'created' => 10, 'updated' => 5, 'unchanged' => 100, 'deleted' => 3, 'translations_cleared' => 1,
        ]),
    ]);

    $result = makeClient()->syncStrings('my-app', ['app.php' => ['title' => 'Hello']]);

    expect($result['deleted'])->toBe(3);

    Http::assertSent(fn (Request $r) => $r->method() === 'PUT');
});

// ---------------------------------------------------------------------------
// importTranslations()
// ---------------------------------------------------------------------------

it('posts translations and returns stats', function () {
    Http::fake([
        BASE_URL.'/api/hives/my-app/translations/es' => Http::response([
            'created' => 120, 'updated' => 0, 'skipped' => 18, 'unknown' => 5,
        ]),
    ]);

    $result = makeClient()->importTranslations('my-app', 'es', ['app.php' => ['title' => 'Hola']]);

    expect($result['created'])->toBe(120)
        ->and($result['skipped'])->toBe(18);

    Http::assertSent(function (Request $r) {
        $body = $r->data();

        return str_contains($r->url(), '/translations/es')
            && $body['overwrite_strategy'] === 'skip';
    });
});

it('passes overwrite_strategy to translation import', function () {
    Http::fake([BASE_URL.'/*' => Http::response(['created' => 0, 'updated' => 0, 'skipped' => 0, 'unknown' => 0])]);

    makeClient()->importTranslations('my-app', 'es', [], 'overwrite');

    Http::assertSent(fn (Request $r) => $r->data()['overwrite_strategy'] === 'overwrite');
});

// ---------------------------------------------------------------------------
// export()
// ---------------------------------------------------------------------------

it('exports translations and returns files', function () {
    Http::fake([
        BASE_URL.'/api/hives/my-app/export*' => Http::response([
            'files' => ['app.json' => '{"title":"Hola"}'],
        ]),
    ]);

    $result = makeClient()->export('my-app', 'json', 'es');

    expect($result['files'])->toHaveKey('app.json');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'format=json')
        && str_contains($r->url(), 'locale=es'));
});

it('exports all locales when locale param is omitted', function () {
    Http::fake([
        BASE_URL.'/api/hives/my-app/export*' => Http::response([
            'files' => ['en.json' => '{}', 'es.json' => '{}'],
        ]),
    ]);

    $result = makeClient()->export('my-app');

    expect($result['files'])->toHaveCount(2);

    Http::assertSent(fn (Request $r) => ! str_contains($r->url(), 'locale='));
});

// ---------------------------------------------------------------------------
// Exception handling
// ---------------------------------------------------------------------------

it('throws AuthenticationException on 401', function () {
    Http::fake([BASE_URL.'/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    makeClient()->locales();
})->throws(AuthenticationException::class, 'Unauthenticated.');

it('throws ForbiddenException on 403', function () {
    Http::fake([BASE_URL.'/*' => Http::response(['message' => 'Token does not have read permission.'], 403)]);

    makeClient()->hives();
})->throws(ForbiddenException::class, 'Token does not have read permission.');

it('throws HiveNotFoundException on 404 with slug', function () {
    Http::fake([BASE_URL.'/*' => Http::response([], 404)]);

    makeClient()->hive('missing-app');
})->throws(HiveNotFoundException::class, "Hive 'missing-app' not found.");

it('throws ValidationException on 422', function () {
    Http::fake([
        BASE_URL.'/*' => Http::response([
            'message' => 'The file field is required.',
            'errors' => ['file' => ['The file field is required.']],
        ], 422),
    ]);

    makeClient()->importStrings('my-app', []);
})->throws(ValidationException::class, 'The file field is required.');

it('exposes errors array from ValidationException', function () {
    Http::fake([
        BASE_URL.'/*' => Http::response([
            'message' => 'The file field is required.',
            'errors' => ['file' => ['The file field is required.']],
        ], 422),
    ]);

    try {
        makeClient()->importStrings('my-app', []);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('file');
    }
});

it('throws StringLimitException when plan limit is reached', function () {
    Http::fake([
        BASE_URL.'/*' => Http::response(['message' => 'String limit reached for your plan.'], 422),
    ]);

    makeClient()->importStrings('my-app', ['app.php' => ['key' => 'value']]);
})->throws(StringLimitException::class, 'String limit reached for your plan.');

it('throws RuntimeException on unexpected status', function () {
    Http::fake([BASE_URL.'/*' => Http::response([], 500)]);

    makeClient()->locales();
})->throws(RuntimeException::class);
