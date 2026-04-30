<?php

declare(strict_types=1);

namespace Stringhive\Resources;

readonly class SourceStringResource
{
    public function __construct(
        public string $key,
        public string $sourceValue,
        public bool $isPlural,
        public string $file,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            sourceValue: $data['source_value'],
            isPlural: (bool) ($data['is_plural'] ?? false),
            file: $data['file'],
        );
    }
}
