<?php

declare(strict_types=1);

namespace Stringhive\Resources;

readonly class SourceStringResource
{
    public function __construct(
        public string $key,
        public string $sourceValue,
        public string $file,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            sourceValue: $data['source_value'],
            file: $data['file'],
        );
    }
}
