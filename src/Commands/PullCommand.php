<?php

declare(strict_types=1);

namespace Stringhive\Commands;

use Illuminate\Console\Command;
use Stringhive\Exceptions\AuthenticationException;
use Stringhive\Exceptions\ForbiddenException;
use Stringhive\Exceptions\HiveNotFoundException;
use Stringhive\Exceptions\ValidationException;
use Stringhive\Stringhive;

class PullCommand extends Command
{
    protected $signature = 'stringhive:pull
                            {hive?             : Hive slug (overrides config stringhive.hive)}
                            {--locale=         : Pull a specific locale only (omit to pull all locales)}
                            {--format=         : Export format (php|json, auto-detected from lang path if omitted)}
                            {--dry-run         : Preview what would be written without touching any files}
                            {--include-source  : Also pull the source locale}
                            {--source-locale=  : Override source locale (defaults to config app.locale)}
                            {--lang-path=      : Override the lang directory path}
                            {--exclude=*       : Glob pattern of files to skip (repeatable; merged with config stringhive.exclude)}
                            {--include=*       : Glob pattern of files to allow (repeatable; merged with config stringhive.include; if set, only matching files are pulled)}';

    protected $description = 'Pull translations from StringHive into local lang files';

    public function handle(Stringhive $client): int
    {
        $hive = $this->argument('hive') ?? config('stringhive.hive');

        if (! $hive) {
            $this->error('No hive specified. Pass a hive argument or set stringhive.hive in your config (STRINGHIVE_HIVE).');

            return self::FAILURE;
        }

        $hive = (string) $hive;
        $locale = $this->option('locale') !== null ? (string) $this->option('locale') : null;
        $format = $this->option('format') !== null ? (string) $this->option('format') : null;
        $dryRun = (bool) $this->option('dry-run');
        $includeSource = (bool) $this->option('include-source');
        $sourceLocale = $this->option('source-locale') ? (string) $this->option('source-locale') : null;
        $langPath = (string) ($this->option('lang-path') ?? config('stringhive.lang_path')) ?: null;
        $exclude = array_merge((array) config('stringhive.exclude', []), (array) $this->option('exclude'));
        $include = array_merge((array) config('stringhive.include', []), (array) $this->option('include'));

        if ($format !== null && ! in_array($format, ['php', 'json'], true)) {
            $this->error("Invalid format '{$format}'. Use 'php' or 'json'.");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[dry-run] No files will be written.');
        }

        $label = $locale !== null ? " (locale: {$locale})" : '';
        $this->line("Pulling from hive: <info>{$hive}</info>{$label}");

        try {
            $result = $client->pull(
                hive: $hive,
                langPath: $langPath,
                locale: $locale,
                format: $format,
                dryRun: $dryRun,
                includeSource: $includeSource,
                sourceLocale: $sourceLocale,
                exclude: $exclude,
                include: $include,
            );
        } catch (AuthenticationException|ForbiddenException|HiveNotFoundException|ValidationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (empty($result['files'])) {
            $this->warn('No files returned from export.');

            return self::SUCCESS;
        }

        foreach ($result['paths'] as $path) {
            if ($result['written']) {
                $this->line("  Written: <info>{$path}</info>");
            } else {
                $this->line("  [dry-run] Would write: <comment>{$path}</comment>");
            }
        }

        $this->line('Done.');

        return self::SUCCESS;
    }
}
