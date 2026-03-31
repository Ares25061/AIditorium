<?php

namespace App\Enums;

enum TaskPermissions : string
{
    case VIEW = 'tasks.view';
    case VIEW_LIST = 'tasks.view-list';
    case CREATE = 'tasks.create';
    case UPDATE = 'tasks.update';
    case DELETE = 'tasks.delete';

    public static function values(): array
    {
        $values = [];
        foreach (self::cases() as $permission) {
            $values[] = $permission->value;
        }
        return $values;
    }
}
