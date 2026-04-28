<?php

declare(strict_types=1);

namespace Stringhive\Enums;

enum ExportFormat: string
{
    case Json = 'json';
    case Php = 'php';
}
