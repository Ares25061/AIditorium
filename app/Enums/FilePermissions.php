<?php

namespace App\Enums;

enum FilePermissions : string
{
    case VIEW = 'files.view';
    case VIEW_LIST = 'files.view-list';
    case UPDATE = 'files.update';
    case DELETE = 'files.delete';

    public static function values(): array
    {
        $values = [];
        foreach (self::cases() as $permission) {
            $values[] = $permission->value;
        }
        return $values;
    }
}
