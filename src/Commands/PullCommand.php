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
                            {hive         : Hive slug}
                            {--locale=    : Pull a specific locale only (omit to pull all locales)}
                            {--format=php : Export format (php|json)}
                            {--dry-run    : Preview what would be written without touching any files}
                            {--lang-path= : Override the lang directory path}';

    protected $description = 'Pull translations from StringHive into local lang files';

    public function handle(Stringhive $client): int
    {
        $hive = (string) $this->argument('hive');
        $locale = $this->option('locale') !== null ? (string) $this->option('locale') : null;
        $format = (string) ($this->option('format') ?? 'php');
        $dryRun = (bool) $this->option('dry-run');
        $langPath = $this->option('lang-path') ? (string) $this->option('lang-path') : null;

        if (! in_array($format, ['php', 'json'], true)) {
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
