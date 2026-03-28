<?php

return [
    // Course Policy
    'course' => [
        'view_any' => [
            'deny' => 'You do not have permission to view any courses.',
        ],
        'view' => [
            'deny' => "You don't have permission to view this course",
        ],
        'update' => [
            'deny' => "You don't have permission to update this course",
            'not_enrolled' => 'You dont enrolled in this course.',
        ],
        'delete' => [
            'deny' => "You don't have permission to archive this course",
        ],
        'hard_delete' => [
            'deny' => "You don't have permission to permanently delete courses",
        ],
        'restore' => [
            'deny' => "You don't have permission to restore this course",
        ],
        'generate_teacher_code' => [
            'deny' => "You don't have permission to generate teacher invite code",
        ],
        'remove_user' => [
            'cannot_remove_self' => "You cannot remove yourself from the course",
            'deny' => "You don't have permission to remove users from this course",
        ],
        'close' => [
            'already_closed' => 'Course is already closed',
            'deny' => "You don't have permission to close this course",
        ],
        'reopen' => [
            'already_open' => 'Course is already open',
            'deny' => "You don't have permission to reopen this course",
        ],
        'regenerate_invite' => [
            'deny' => "You don't have permission to regenerate invite code",
        ],
        'get_users' => [
            'deny' => ' You do not have permission to view users for this course.',
            'not_enrolled' => 'You dont enrolled in this course.',
        ]
    ],
    // Discipline policy
    'discipline' => [
        'view_any' => [
            'deny' => 'You do not have permission to view any disciplines.',
        ],
        'view' => [
            'deny' => "You don't have permission to view this discipline in this course",
        ],
        'create' => [
            'deny' => "You don't have permission to create disciplines in this course",
            'archived' => 'You cannot create a discipline in an archived course.',
        ],
        'update' => [
            'deny' => "You don't have permission to update disciplines in this course",
            'archived' => 'You cannot update a discipline in an archived course.',
        ],
        'delete' => [
            'deny' => "You don't have permission to delete this discipline in this course",
            'archived' => 'You cannot delete a discipline in an archived course.',
        ],
    ],
    // file policy
    'file' => [
        'view_any' => [
            'deny' => 'You do not have permission to view any files.',
        ],
        'view' => [
            'deny' => "You don't have permission to view this file",
        ],
        'check_course_access' => [
            'deny' => 'You cannot view files from other students in this course',
        ],
        'view_any_in_course' => [
            'deny' => 'Only teachers can view all files in course',
        ],
        'view_student_files' => [
            'deny' => "You cannot view this student's files",
        ],
        'update' => [
            'deny' => "You don't have permission to update files",
        ],
        'delete' => [
            'deny' => "You don't have permission to delete files",
        ],
    ],
    // task policy
    'task' => [
        'view_any' => [
            'deny' => 'You do not have permission to view any tasks.',
        ],
        'view' => [
            'deny' => "You don't have permission to view this task in this course",
        ],
        'create' => [
            'deny' => "You don't have permission to create tasks in this course",
            'archived' => 'You cannot create a task in an archived course.',
        ],
        'update' => [
            'deny' => "You don't have permission to update tasks in this course",
            'archived' => 'You cannot update a task in an archived course.',
        ],
        'delete' => [
            'deny' => "You don't have permission to delete this task in this course",
            'archived' => 'You cannot delete a task in an archived course.',
        ],
    ],
    // user policy
    'user' => [
        'view' => [
            'deny' => "You don't have permission to view this user",
        ],
        'view_list' => [
            'deny' => "You don't have permission to view list of users",
        ],
        'update' => [
            'deny' => "You don't have permission to update users",
        ],
        'delete' => [
            'deny' => "You don't have permission to delete users",
            'cannot_delete_self' => "You cannot delete yourself",
        ],
        'set_role' => [
            'deny' => "You don't have permission to set roles",
            'cannot_change_self' => "You cannot change your own role",
        ],
    ],
];
