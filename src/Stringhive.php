<?php

declare(strict_types=1);

namespace Stringhive;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stringhive\Exceptions\AuthenticationException;
use Stringhive\Exceptions\ForbiddenException;
use Stringhive\Exceptions\HiveNotFoundException;
use Stringhive\Exceptions\StringLimitException;
use Stringhive\Exceptions\ValidationException;
use Stringhive\Lang\LangLoader;

class Stringhive
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout = 30,
    ) {}

    // -------------------------------------------------------------------------
    // API methods
    // -------------------------------------------------------------------------

    public function locales(): array
    {
        return $this->get('/api/locales')->json('locales');
    }

    public function hives(): array
    {
        return $this->get('/api/hives')->json('hives');
    }

    public function hive(string $slug): array
    {
        return $this->get("/api/hives/{$slug}")->json();
    }

    public function strings(string $slug, ?string $file = null, int $perPage = 100, int $page = 1): array
    {
        $query = ['per_page' => $perPage, 'page' => $page];

        if ($file !== null) {
            $query['file'] = $file;
        }

        return $this->get("/api/hives/{$slug}/strings", $query)->json();
    }

    public function allStrings(string $slug, ?string $file = null): array
    {
        $all = [];
        $page = 1;

        do {
            $result = $this->strings($slug, $file, 500, $page);
            $all = array_merge($all, $result['data']);
            $lastPage = $result['meta']['last_page'];
            $page++;
        } while ($page <= $lastPage);

        return $all;
    }

    public function importStrings(string $slug, array $files, string $conflictStrategy = 'keep'): array
    {
        return $this->post("/api/hives/{$slug}/strings", [
            'conflict_strategy' => $conflictStrategy,
            'files' => $files,
        ])->json();
    }

    public function syncStrings(string $slug, array $files, string $conflictStrategy = 'keep'): array
    {
        return $this->put("/api/hives/{$slug}/strings", [
            'conflict_strategy' => $conflictStrategy,
            'files' => $files,
        ])->json();
    }

    public function importTranslations(string $slug, string $locale, array $files, string $overwriteStrategy = 'skip'): array
    {
        return $this->post("/api/hives/{$slug}/translations/{$locale}", [
            'overwrite_strategy' => $overwriteStrategy,
            'files' => $files,
        ])->json();
    }

    public function export(string $slug, string $format = 'json', ?string $locale = null): array
    {
        $query = ['format' => $format];

        if ($locale !== null) {
            $query['locale'] = $locale;
        }

        return $this->get("/api/hives/{$slug}/export", $query)->json();
    }

    // -------------------------------------------------------------------------
    // High-level lang helpers
    // -------------------------------------------------------------------------

    /**
     * Read local lang files and push them to StringHive.
     *
     * Handles both PHP-style (lang/{locale}/*.php) and JSON-style (lang/{locale}.json)
     * translation directories. Both formats can coexist.
     *
     * @return array{source: array<string,mixed>|null, translations: array<string, array<string,mixed>>}
     */
    public function push(
        string $hive,
        ?string $langPath = null,
        ?string $sourceLocale = null,
        bool $sync = false,
        string $conflictStrategy = 'keep',
        ?LangLoader $loader = null,
    ): array {
        $langPath = $langPath ?? lang_path();
        $sourceLocale = $sourceLocale ?? (string) config('app.locale', 'en');
        $loader = $loader ?? new LangLoader;

        $phpLocales = $loader->phpLocales($langPath);
        $jsonLocales = $loader->jsonLocales($langPath);

        $sourceResult = null;
        $translationResults = [];

        if (in_array($sourceLocale, $phpLocales, true)) {
            $sourceFiles = $loader->readPhpLocale($langPath, $sourceLocale);

            if (! empty($sourceFiles)) {
                $sourceResult = $sync
                    ? $this->syncStrings($hive, $sourceFiles, $conflictStrategy)
                    : $this->importStrings($hive, $sourceFiles, $conflictStrategy);

                foreach ($phpLocales as $locale) {
                    if ($locale === $sourceLocale) {
                        continue;
                    }
                    $files = $loader->readPhpLocale($langPath, $locale);
                    if (! empty($files)) {
                        $translationResults[$locale] = $this->importTranslations($hive, $locale, $files);
                    }
                }
            }
        }

        if (in_array($sourceLocale, $jsonLocales, true)) {
            $sourceData = $loader->readJsonLocale($langPath, $sourceLocale);

            if (! empty($sourceData)) {
                $fileKey = $sourceLocale.'.json';
                $sourceResult = $sync
                    ? $this->syncStrings($hive, [$fileKey => $sourceData], $conflictStrategy)
                    : $this->importStrings($hive, [$fileKey => $sourceData], $conflictStrategy);

                foreach ($jsonLocales as $locale) {
                    if ($locale === $sourceLocale) {
                        continue;
                    }
                    $data = $loader->readJsonLocale($langPath, $locale);
                    if (! empty($data)) {
                        $translationResults[$locale] = $this->importTranslations($hive, $locale, [$fileKey => $data]);
                    }
                }
            }
        }

        return [
            'source' => $sourceResult,
            'translations' => $translationResults,
        ];
    }

    /**
     * Pull translations from StringHive and write them to local lang files.
     *
     * When $locale is given, files land in lang/{locale}/{filename}.
     * When omitted, all-locale export lands directly in lang/ (e.g. lang/es.json).
     *
     * Set $dryRun = true to get the list of paths that would be written
     * without touching the filesystem.
     *
     * @return array{files: array<string,string>, paths: array<int,string>, written: bool}
     */
    public function pull(
        string $hive,
        ?string $langPath = null,
        ?string $locale = null,
        string $format = 'php',
        bool $dryRun = false,
        ?LangLoader $loader = null,
    ): array {
        $langPath = $langPath ?? lang_path();
        $loader = $loader ?? new LangLoader;

        $export = $this->export($hive, $format, $locale);
        $files = $export['files'] ?? [];

        $paths = [];
        if ($locale !== null) {
            foreach (array_keys($files) as $filename) {
                $paths[] = $langPath.'/'.$locale.'/'.$filename;
            }
        } else {
            foreach (array_keys($files) as $filename) {
                $paths[] = $langPath.'/'.$filename;
            }
        }

        if (! $dryRun) {
            if ($locale !== null) {
                foreach ($files as $filename => $content) {
                    $loader->writePhpLocale($langPath, $locale, [$filename => $content]);
                }
            } else {
                foreach ($files as $filename => $content) {
                    $loader->writeLangFile($langPath, $filename, $content);
                }
            }
        }

        return [
            'files' => $files,
            'paths' => $paths,
            'written' => ! $dryRun,
        ];
    }

    // -------------------------------------------------------------------------
    // HTTP internals
    // -------------------------------------------------------------------------

    private function get(string $path, array $query = []): Response
    {
        return $this->handleResponse(
            $this->pendingRequest()->get($this->baseUrl.$path, $query),
            $path,
        );
    }

    private function post(string $path, array $body = []): Response
    {
        return $this->handleResponse(
            $this->pendingRequest()->post($this->baseUrl.$path, $body),
            $path,
        );
    }

    private function put(string $path, array $body = []): Response
    {
        return $this->handleResponse(
            $this->pendingRequest()->put($this->baseUrl.$path, $body),
            $path,
        );
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    private function handleResponse(Response $response, string $path): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $message = $response->json('message') ?? '';

        throw match ($response->status()) {
            401 => new AuthenticationException($message ?: 'Unauthenticated.'),
            403 => new ForbiddenException($message),
            404 => new HiveNotFoundException($this->extractSlug($path)),
            422 => $message === 'String limit reached for your plan.'
                           ? new StringLimitException
                           : new ValidationException($response->json() ?? []),
            default => new RuntimeException("Unexpected HTTP status {$response->status()}: {$message}"),
        };
    }

    private function extractSlug(string $path): string
    {
        if (preg_match('~/hives/([^/?]+)~', $path, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
