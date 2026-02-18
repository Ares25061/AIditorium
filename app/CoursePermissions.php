<?php

namespace App;

enum CoursePermissions : string
{
    case VIEW = 'courses.view';
    case VIEW_LIST = 'courses.view-list';
    case UPDATE = 'courses.update';
    case DELETE = 'courses.delete';
    case HARD_DELETE = 'courses.hard-delete';
    case RESTORE = 'courses.restore';
    case GENERATE_TEACHER_CODE_INVITE = 'courses.generate-teacher-code-invite';

    public static function values(): array
    {
        $values = [];
        foreach (self::cases() as $permission) {
            $values[] = $permission->value;
        }
        return $values;
    }
}
