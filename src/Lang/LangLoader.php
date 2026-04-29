<?php

declare(strict_types=1);

namespace Stringhive\Lang;

class LangLoader
{
    /**
     * Return locale codes that have a subdirectory in $langPath (PHP-style).
     * Skips 'vendor' and anything that doesn't look like a locale code.
     *
     * @return array<int, string>
     */
    public function phpLocales(string $langPath): array
    {
        if (! is_dir($langPath)) {
            return [];
        }

        $locales = [];
        foreach (glob($langPath.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $locale = basename($dir);
            if ($locale !== 'vendor' && preg_match('/^[a-zA-Z]{2,8}(_[a-zA-Z]{2,4})?$/', $locale)) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    /**
     * Return locale codes that have a *.json file directly in $langPath (JSON-style).
     *
     * @return array<int, string>
     */
    public function jsonLocales(string $langPath): array
    {
        if (! is_dir($langPath)) {
            return [];
        }

        $locales = [];
        foreach (glob($langPath.'/*.json') ?: [] as $file) {
            $locales[] = pathinfo($file, PATHINFO_FILENAME);
        }

        return $locales;
    }

    /**
     * Read PHP translation files for one locale.
     * Skips files that don't return an array (guards against bad files).
     * Skips any filename that matches an entry in $exclude (fnmatch patterns).
     *
     * @param  array<int, string>  $exclude  glob patterns to skip (e.g. ['auth.php'])
     * @return array<string, array<mixed>> filename => nested array
     */
    public function readPhpLocale(string $langPath, string $locale, array $exclude = []): array
    {
        $dir = $langPath.'/'.$locale;
        if (! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (glob($dir.'/*.php') ?: [] as $path) {
            $filename = basename($path);
            if ($this->isExcluded($filename, $exclude)) {
                continue;
            }
            $data = include $path;
            if (is_array($data)) {
                $files[$filename] = $data;
            }
        }

        return $files;
    }

    /**
     * Return true if $filename matches any of the given fnmatch patterns.
     *
     * @param  array<int, string>  $patterns
     */
    public function isExcluded(string $filename, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $filename)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read a JSON locale file and return its decoded contents.
     * Returns [] if the file doesn't exist or contains non-object JSON.
     *
     * @return array<string, string>
     */
    public function readJsonLocale(string $langPath, string $locale): array
    {
        $path = $langPath.'/'.$locale.'.json';
        if (! file_exists($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        $data = json_decode($raw !== false ? $raw : '', true);

        return is_array($data) ? $data : [];
    }

    /**
     * Write PHP translation file content for one locale.
     * Creates the locale directory if it does not exist.
     *
     * @param  array<string, string>  $files  filename => PHP file content string
     * @return array<int, string> list of absolute paths written
     */
    public function writePhpLocale(string $langPath, string $locale, array $files): array
    {
        $dir = $langPath.'/'.$locale;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $written = [];
        foreach ($files as $filename => $content) {
            $path = $dir.'/'.$filename;
            file_put_contents($path, $content);
            $written[] = $path;
        }

        return $written;
    }

    /**
     * Write a single file directly into the lang directory root.
     * Used for JSON-style all-locale exports (e.g. lang/es.json).
     */
    public function writeLangFile(string $langPath, string $filename, string $content): string
    {
        $path = $langPath.'/'.$filename;
        file_put_contents($path, $content);

        return $path;
    }
}
