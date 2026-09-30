<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingType: string
{
    case Int = 'int';
    case Bool = 'bool';
    case Enum = 'enum';
    case IntList = 'int_list';
    case Time = 'time';
    case Version = 'version';
}
