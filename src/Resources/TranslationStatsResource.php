<?php

declare(strict_types=1);

namespace Stringhive\Resources;

readonly class TranslationStatsResource
{
    public function __construct(
        public string $locale,
        public int $translated,
        public int $approved,
        public int $warning,
        public int $empty,
        public float $translatedPercent,
        public float $approvedPercent,
    ) {}

    public static function fromArray(string $locale, array $data): self
    {
        return new self(
            locale: $locale,
            translated: $data['translated'],
            approved: $data['approved'],
            warning: $data['warning'],
            empty: $data['empty'],
            translatedPercent: $data['translated_percent'],
            approvedPercent: $data['approved_percent'],
        );
    }
}
