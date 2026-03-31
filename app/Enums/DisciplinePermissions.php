<?php

namespace App\Enums;

enum DisciplinePermissions : string
{
    case VIEW = 'disciplines.view';
    case VIEW_LIST = 'disciplines.view-list';
    case CREATE = 'disciplines.create';
    case UPDATE = 'disciplines.update';
    case DELETE = 'disciplines.delete';

    public static function values(): array
    {
        $values = [];
        foreach (self::cases() as $permission) {
            $values[] = $permission->value;
        }
        return $values;
    }
}
