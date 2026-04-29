<?php

declare(strict_types=1);

namespace Stringhive\Commands;

use Illuminate\Console\Command;
use Stringhive\Exceptions\AuthenticationException;
use Stringhive\Exceptions\ForbiddenException;
use Stringhive\Exceptions\HiveNotFoundException;
use Stringhive\Exceptions\StringLimitException;
use Stringhive\Exceptions\ValidationException;
use Stringhive\Stringhive;

class PushCommand extends Command
{
    protected $signature = 'stringhive:push
                            {hive?                    : Hive slug (overrides config stringhive.hive)}
                            {--sync                   : Also delete strings absent from the import (per-file)}
                            {--conflict-strategy=keep : What to do with translations when source changes (keep|clear)}
                            {--with-translations      : Also push translation files for non-source locales}
                            {--source-locale=         : Override source locale (defaults to config app.locale)}
                            {--lang-path=             : Override the lang directory path}';

    protected $description = 'Push local translation files to StringHive';

    public function handle(Stringhive $client): int
    {
        $hive = $this->argument('hive') ?? config('stringhive.hive');

        if (! $hive) {
            $this->error('No hive specified. Pass a hive argument or set stringhive.hive in your config (STRINGHIVE_HIVE).');

            return self::FAILURE;
        }

        $hive = (string) $hive;
        $sync = (bool) $this->option('sync');
        $strategy = (string) ($this->option('conflict-strategy') ?? 'keep');
        $withTranslations = (bool) $this->option('with-translations');
        $langPath = $this->option('lang-path') ? (string) $this->option('lang-path') : null;
        $sourceLocale = $this->option('source-locale') ? (string) $this->option('source-locale') : null;

        if ($langPath !== null && ! is_dir($langPath)) {
            $this->error("Lang path not found: {$langPath}");

            return self::FAILURE;
        }

        $this->line("Pushing to hive: <info>{$hive}</info>");

        try {
            $result = $client->push(
                hive: $hive,
                langPath: $langPath,
                sourceLocale: $sourceLocale,
                sync: $sync,
                conflictStrategy: $strategy,
                withTranslations: $withTranslations,
            );
        } catch (AuthenticationException|ForbiddenException|HiveNotFoundException|StringLimitException|ValidationException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['source'] === null && empty($result['translations'])) {
            $this->warn('No translation files found.');

            return self::SUCCESS;
        }

        if ($result['source'] !== null) {
            $r = $result['source'];
            $this->line(sprintf(
                '  source — created: %d  updated: %d  unchanged: %d  cleared: %d',
                (int) ($r['created'] ?? 0),
                (int) ($r['updated'] ?? 0),
                (int) ($r['unchanged'] ?? 0),
                (int) ($r['translations_cleared'] ?? 0),
            ));
        }

        foreach ($result['translations'] as $locale => $r) {
            $this->line(sprintf(
                '  %s — created: %d  updated: %d  skipped: %d  unknown: %d',
                $locale,
                (int) ($r['created'] ?? 0),
                (int) ($r['updated'] ?? 0),
                (int) ($r['skipped'] ?? 0),
                (int) ($r['unknown'] ?? 0),
            ));
        }

        $this->line('Done.');

        return self::SUCCESS;
    }
}
