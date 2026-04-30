<?php

declare(strict_types=1);

namespace Stringhive\Commands;

use Illuminate\Console\Command;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Stringhive\Exceptions\AuthenticationException;
use Stringhive\Exceptions\ForbiddenException;
use Stringhive\Exceptions\HiveNotFoundException;
use Stringhive\Stringhive;

class AuditCommand extends Command
{
    protected $signature = 'stringhive:audit
                            {hive?               : Hive slug (overrides config stringhive.hive)}
                            {--format=table      : Output format (table|json|github)}
                            {--fail-on-missing   : Exit 1 if any keys are used in code but absent from the hive}
                            {--fail-on-orphaned  : Exit 1 if any hive keys are not referenced in code}';

    protected $description = 'Diff hive keys against static translation calls in the codebase';

    // Matches the first string argument of trans(), __(), trans_choice(), Lang::get/choice(), @lang()
    private const PATTERN = '/(?<![a-zA-Z_\x7f-\xff])(?:trans_choice|trans|__)\s*\(\s*([\'"])(.*?)\1|Lang::(?:get|choice)\s*\(\s*([\'"])(.*?)\3|@lang\s*\(\s*([\'"])(.*?)\5/u';

    public function handle(Stringhive $client): int
    {
        $hive = $this->argument('hive') ?? config('stringhive.hive');

        if (! $hive) {
            $this->error('No hive specified. Pass a hive argument or set stringhive.hive in your config (STRINGHIVE_HIVE).');

            return self::FAILURE;
        }

        $hive = (string) $hive;
        $format = strtolower((string) ($this->option('format') ?? 'table'));

        if (! in_array($format, ['table', 'json', 'github'], true)) {
            $this->error("Invalid format '{$format}'. Use 'table', 'json', or 'github'.");

            return self::FAILURE;
        }

        if ($format !== 'json') {
            $this->line("Auditing hive: <info>{$hive}</info>");
        }

        try {
            $apiResponse = $client->keys($hive);
        } catch (AuthenticationException|ForbiddenException|HiveNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        /** @var array<int, array{key: string, file: string|null}> $apiKeyList */
        $apiKeyList = $apiResponse['keys'] ?? [];
        $apiTotal = (int) ($apiResponse['total'] ?? count($apiKeyList));

        if ($format !== 'json') {
            $this->line("  API keys: <info>{$apiTotal}</info>");
        }

        $scan = $this->scanCode(base_path());

        /** @var array<string, list<array{file: string, line: int}>> $codeKeys */
        $codeKeys = $scan['keys'];
        $dynamicCount = $scan['dynamic'];
        $fileCount = $scan['files'];

        if ($format !== 'json') {
            $this->line(sprintf(
                '  Scanned: <info>%d</info> files — <info>%d</info> unique static keys (<comment>%d</comment> dynamic skipped)',
                $fileCount,
                count($codeKeys),
                $dynamicCount,
            ));
        }

        $apiKeyMap = [];
        foreach ($apiKeyList as $entry) {
            $apiKeyMap[$entry['key']] = $entry['file'] ?? null;
        }

        $orphanedKeys = array_values(array_diff(array_keys($apiKeyMap), array_keys($codeKeys)));
        $missingKeys = array_values(array_diff(array_keys($codeKeys), array_keys($apiKeyMap)));
        sort($orphanedKeys);
        sort($missingKeys);

        match ($format) {
            'json'   => $this->outputJson($hive, $apiTotal, $fileCount, count($codeKeys), $dynamicCount, $apiKeyMap, $orphanedKeys, $missingKeys, $codeKeys),
            'github' => $this->outputGithub($apiKeyMap, $orphanedKeys, $missingKeys, $codeKeys),
            default  => $this->outputTable($apiKeyMap, $orphanedKeys, $missingKeys, $codeKeys),
        };

        $exitCode = self::SUCCESS;

        if ($this->option('fail-on-missing') && ! empty($missingKeys)) {
            $exitCode = self::FAILURE;
        }

        if ($this->option('fail-on-orphaned') && ! empty($orphanedKeys)) {
            $exitCode = self::FAILURE;
        }

        return $exitCode;
    }

    /**
     * @return array{keys: array<string, list<array{file: string, line: int}>>, dynamic: int, files: int}
     */
    private function scanCode(string $basePath): array
    {
        $excludeDirs = ['vendor', 'node_modules', 'storage'];
        $found = [];
        $dynamicCount = 0;
        $fileCount = 0;

        $directory = new RecursiveDirectoryIterator(
            $basePath,
            RecursiveDirectoryIterator::SKIP_DOTS,
        );

        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $file, string $key, RecursiveIterator $iterator) use ($excludeDirs): bool {
                if ($file->isDir()) {
                    return ! in_array($file->getFilename(), $excludeDirs, true);
                }

                return str_ends_with($file->getFilename(), '.php');
            },
        );

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            /** @var SplFileInfo $file */
            $pathname = $file->getPathname();
            $rel = ltrim(str_replace($basePath, '', $pathname), DIRECTORY_SEPARATOR);

            if (! is_readable($pathname)) {
                continue;
            }

            $lines = file($pathname, FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            $fileCount++;

            foreach ($lines as $idx => $line) {
                if (! preg_match_all(self::PATTERN, $line, $matches, PREG_SET_ORDER)) {
                    continue;
                }

                foreach ($matches as $match) {
                    $translationKey = $match[2] !== '' ? $match[2]
                        : ($match[4] !== '' ? $match[4]
                        : ($match[6] ?? ''));

                    if ($translationKey === '') {
                        continue;
                    }

                    if (str_contains($translationKey, '$') || str_contains($translationKey, '{')) {
                        $dynamicCount++;
                        continue;
                    }

                    $found[$translationKey][] = ['file' => $rel, 'line' => $idx + 1];
                }
            }
        }

        return ['keys' => $found, 'dynamic' => $dynamicCount, 'files' => $fileCount];
    }

    /**
     * @param array<string, string|null>                          $apiKeyMap
     * @param list<string>                                        $orphanedKeys
     * @param list<string>                                        $missingKeys
     * @param array<string, list<array{file: string, line: int}>> $codeKeys
     */
    private function outputTable(array $apiKeyMap, array $orphanedKeys, array $missingKeys, array $codeKeys): void
    {
        $this->newLine();

        if (empty($missingKeys) && empty($orphanedKeys)) {
            $this->line('<info>All keys are in sync.</info>');

            return;
        }

        if (! empty($missingKeys)) {
            $this->line(sprintf('<error> MISSING — %d key(s) in code but not in the hive </error>', count($missingKeys)));
            $rows = [];

            foreach ($missingKeys as $translationKey) {
                $first = $codeKeys[$translationKey][0];
                $extra = count($codeKeys[$translationKey]) > 1
                    ? sprintf(' (+%d)', count($codeKeys[$translationKey]) - 1)
                    : '';

                $rows[] = [$translationKey, $first['file'].$extra, $first['line']];
            }

            $this->table(['Key', 'File', 'Line'], $rows);
        }

        if (! empty($orphanedKeys)) {
            $this->newLine();
            $this->line(sprintf('<comment> ORPHANED — %d key(s) in the hive but not in code </comment>', count($orphanedKeys)));
            $rows = [];

            foreach ($orphanedKeys as $translationKey) {
                $rows[] = [$translationKey, $apiKeyMap[$translationKey] ?? ''];
            }

            $this->table(['Key', 'API File'], $rows);
        }
    }

    /**
     * @param array<string, string|null>                          $apiKeyMap
     * @param list<string>                                        $orphanedKeys
     * @param list<string>                                        $missingKeys
     * @param array<string, list<array{file: string, line: int}>> $codeKeys
     */
    private function outputGithub(array $apiKeyMap, array $orphanedKeys, array $missingKeys, array $codeKeys): void
    {
        foreach ($missingKeys as $translationKey) {
            $first = $codeKeys[$translationKey][0];
            $this->line(sprintf(
                '::warning file=%s,line=%d::StringHive: missing key "%s"',
                $first['file'],
                $first['line'],
                $translationKey,
            ));
        }

        foreach ($orphanedKeys as $translationKey) {
            $apiFile = $apiKeyMap[$translationKey] ?? '';
            $hint = $apiFile !== '' ? " (file: {$apiFile})" : '';
            $this->line(sprintf('::warning ::StringHive: orphaned key "%s"%s', $translationKey, $hint));
        }
    }

    /**
     * @param array<string, string|null>                          $apiKeyMap
     * @param list<string>                                        $orphanedKeys
     * @param list<string>                                        $missingKeys
     * @param array<string, list<array{file: string, line: int}>> $codeKeys
     */
    private function outputJson(
        string $hive,
        int $apiTotal,
        int $fileCount,
        int $codeTotal,
        int $dynamicCount,
        array $apiKeyMap,
        array $orphanedKeys,
        array $missingKeys,
        array $codeKeys,
    ): void {
        $missing = [];

        foreach ($missingKeys as $translationKey) {
            $missing[] = ['key' => $translationKey, 'occurrences' => $codeKeys[$translationKey]];
        }

        $orphaned = [];

        foreach ($orphanedKeys as $translationKey) {
            $orphaned[] = ['key' => $translationKey, 'file' => $apiKeyMap[$translationKey]];
        }

        $this->line((string) json_encode([
            'hive' => $hive,
            'summary' => [
                'api_total' => $apiTotal,
                'code_total' => $codeTotal,
                'files_scanned' => $fileCount,
                'dynamic_skipped' => $dynamicCount,
                'missing' => count($missingKeys),
                'orphaned' => count($orphanedKeys),
            ],
            'missing' => $missing,
            'orphaned' => $orphaned,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
