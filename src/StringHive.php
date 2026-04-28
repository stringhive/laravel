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

class StringHive
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeout = 30,
    ) {}

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
            'files'             => $files,
        ])->json();
    }

    public function syncStrings(string $slug, array $files, string $conflictStrategy = 'keep'): array
    {
        return $this->put("/api/hives/{$slug}/strings", [
            'conflict_strategy' => $conflictStrategy,
            'files'             => $files,
        ])->json();
    }

    public function importTranslations(string $slug, string $locale, array $files, string $overwriteStrategy = 'skip'): array
    {
        return $this->post("/api/hives/{$slug}/translations/{$locale}", [
            'overwrite_strategy' => $overwriteStrategy,
            'files'              => $files,
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

    private function get(string $path, array $query = []): Response
    {
        return $this->handleResponse(
            $this->pendingRequest()->get($this->baseUrl . $path, $query),
            $path,
        );
    }

    private function post(string $path, array $body = []): Response
    {
        return $this->handleResponse(
            $this->pendingRequest()->post($this->baseUrl . $path, $body),
            $path,
        );
    }

    private function put(string $path, array $body = []): Response
    {
        return $this->handleResponse(
            $this->pendingRequest()->put($this->baseUrl . $path, $body),
            $path,
        );
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ]);
    }

    private function handleResponse(Response $response, string $path): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $message = $response->json('message') ?? '';

        throw match ($response->status()) {
            401     => new AuthenticationException($message ?: 'Unauthenticated.'),
            403     => new ForbiddenException($message),
            404     => new HiveNotFoundException($this->extractSlug($path)),
            422     => $message === 'String limit reached for your plan.'
                           ? new StringLimitException()
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
