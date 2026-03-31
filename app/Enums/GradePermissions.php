<?php
// app/Enums/GradePermissions.php

namespace App\Enums;

enum GradePermissions : string
{
    case VIEW = 'grades.view';
    case VIEW_LIST = 'grades.view-list';
    case UPDATE = 'grades.update';
    case DELETE = 'grades.delete';

    public static function values(): array
    {
        $values = [];
        foreach (self::cases() as $permission) {
            $values[] = $permission->value;
        }
        return $values;
    }
}
