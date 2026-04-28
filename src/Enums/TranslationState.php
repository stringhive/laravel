<?php

declare(strict_types=1);

namespace Stringhive\Enums;

enum TranslationState: string
{
    case Empty = 'empty';
    case Translated = 'translated';
    case Approved = 'approved';
    case Warning = 'warning';
}
