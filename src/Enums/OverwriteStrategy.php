<?php

declare(strict_types=1);

namespace Stringhive\Enums;

enum OverwriteStrategy: string
{
    case Skip = 'skip';
    case Overwrite = 'overwrite';
}
