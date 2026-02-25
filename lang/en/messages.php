<?php

return [
    // Success messages
    'created' => ':item created successfully!',
    'updated' => ':item updated successfully!',
    'deleted' => ':item deleted successfully!',
    'archived' => ':item archived successfully!',
    'restored' => ':item restored successfully!',
    'added' => ':item added successfully!',
    'removed' => ':item removed successfully!',
    'generated' => ':item generated successfully!',
    'uploaded' => ':item uploaded successfully!',
    'registered' => 'User registered successfully',
    'logged_in' => 'Logged in successfully',
    'logged_out' => 'Logged out successfully',
    'refreshed' => 'Token refreshed successfully',
    'role_set' => 'Role set successfully',
    'edited' => ':item edited successfully!',
    'invite_code_generated' => 'Course invite code generated successfully!',

    // Error messages
    'not_found' => ':item not found',
    'unauthorized' => 'You are not authorized to perform this action',
    'forbidden' => 'You do not have permission to access this :item',
    'validation_failed' => 'Validation failed',
    'server_error' => 'Server error occurred',
    'invalid_credentials' => 'Invalid credentials',
    'not_found_general' => 'Resource not found',

    // Specific errors
    'already_enrolled' => 'User is already enrolled in this course',
    'not_enrolled' => 'User is not enrolled in this course',
    'invalid_invite_code' => 'Invalid invite code',
    'file_not_on_server' => 'File does not exist on server',
    'student_not_enrolled' => 'Student is not enrolled in this course',

    // Items names (for :item placeholder)
    'items' => [
        'course' => 'Course',
        'file' => 'File',
        'user' => 'User',
        'task' => 'Task',
        'avatar' => 'Avatar',
        'role' => 'Role',
        'course_list' => 'Courses',
        'file_list' => 'Files',
        'user_list' => 'Users',
        'task_list' => 'Tasks',
        'invite_code' => 'Invite code',

    ],

    // Auth specific
    'auth' => [
        'token_type' => 'bearer',
        'authorization' => 'Authorization',
    ],

    // Status messages
    'status' => [
        'success' => 'success',
        'error' => 'error',
    ],
];
