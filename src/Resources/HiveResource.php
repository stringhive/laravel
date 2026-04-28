<?php

declare(strict_types=1);

namespace Stringhive\Resources;

readonly class HiveResource
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $sourceLocale,
        public array $locales,
        public int $stringCount,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'],
            name: $data['name'],
            sourceLocale: $data['source_locale'],
            locales: $data['locales'],
            stringCount: $data['string_count'],
        );
    }
}
