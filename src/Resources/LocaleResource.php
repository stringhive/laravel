<?php

declare(strict_types=1);

namespace Stringhive\Resources;

readonly class LocaleResource
{
    public function __construct(
        public string $code,
        public string $name,
        public string $region,
        public bool $rtl,
        public bool $isPopular,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            name: $data['name'],
            region: $data['region'],
            rtl: $data['rtl'],
            isPopular: $data['is_popular'],
        );
    }
}
