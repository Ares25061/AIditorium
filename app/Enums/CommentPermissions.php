<?php

namespace App\Enums;

enum CommentPermissions : string
{
    case VIEW = 'comments.view';
    case VIEW_LIST = 'comments.view-list';
    case UPDATE = 'comments.update';
    case DELETE = 'comments.delete';

    public static function values(): array
    {
        $values = [];
        foreach (self::cases() as $permission) {
            $values[] = $permission->value;
        }
        return $values;
    }
}
