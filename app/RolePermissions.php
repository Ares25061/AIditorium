<?php

namespace App;

enum RolePermissions : string
{
    case UPDATE = 'roles.update';
    case DELETE = 'roles.delete';
    case SET = 'roles.set';
    public static function values(): array
    {
        $values = [];
        foreach (self::cases() as $permission) {
            $values[] = $permission->value;
        }
        return $values;
    }
}
