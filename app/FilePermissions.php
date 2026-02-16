<?php

namespace App;

enum FilePermissions : string
{
    case VIEW = 'files.view';
    case VIEW_LIST = 'files.view-list';
    case UPDATE = 'files.update';
    case DELETE = 'files.delete';
    case DELETE_ALL = 'files.delete-all';

    public static function values(): array
    {
        $values = [];
        foreach (self::cases() as $permission) {
            $values[] = $permission->value;
        }
        return $values;
    }
}
