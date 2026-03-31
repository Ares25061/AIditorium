<?php

return [
    // Success messages
    'created' => ':item успешно создан!',
    'updated' => ':item успешно обновлен!',
    'deleted' => ':item успешно удален!',
    'archived' => ':item успешно архивирован!',
    'restored' => ':item успешно восстановлен!',
    'added' => ':item успешно добавлен!',
    'removed' => ':item успешно удален!',
    'reopened' => ':item успешно возобновлен!',
    'generated' => ':item успешно сгенерирован!',
    'uploaded' => ':item успешно загружен!',
    'registered' => 'Пользователь успешно зарегистрирован',
    'logged_in' => 'Успешный вход в систему',
    'logged_out' => 'Выход из системы выполнен',
    'refreshed' => 'Токен успешно обновлен',
    'role_set' => 'Роль успешно установлена',
    'edited' => ':item успешно отредактирован!',
    'invite_code_generated' => 'Инвайт-код для курса успешно сгенерирован!',
    'invite_code_regenerated' => 'Инвайт-код для курса успешно пересоздан!',
    'course_left' => 'Курс покинут',

    // Error messages
    'not_found' => ':item не найден',
    'unauthorized' => 'У вас нет прав для этого действия',
    'forbidden' => 'У вас нет доступа к этому :item',
    'validation_failed' => 'Ошибка валидации',
    'server_error' => 'Произошла ошибка сервера',
    'invalid_credentials' => 'Неверные учетные данные',
    'not_found_general' => 'Ресурс не найден',

    // Specific errors
    'already_enrolled' => 'Пользователь уже записан на этот курс',
    'not_enrolled' => 'Пользователь не записан на этот курс',
    'course_closed' => 'Курс закрыт',
    'course_archived' => 'Нельзя покинуть или войти в архивированный курс',
    'invalid_invite_code' => 'Неверный инвайт-код',
    'file_not_on_server' => 'Файл отсутствует на сервере',
    'student_not_enrolled' => 'Студент не записан на этот курс',
    'avatar_upload_not_image' => 'Аватар должен быть изображением.',
    'avatar_upload_invalid_type' => 'Для аватара поддерживаются только JPG, PNG, GIF и WEBP.',
    'avatar_upload_too_large' => 'Файл аватара слишком большой. Максимальный размер — 3 МБ.',
    'avatar_upload_invalid_dimensions' => 'Изображение аватара должно быть не меньше 100×100 и не больше 2000×2000 пикселей.',
    'slug_letters' => 'slug должен содержать хотя бы одну букву',
    'slug_exists' => 'slug уже существует',
    'slug_exists_in_course' => 'slug уже существует в этом курсе',

    // Items names
    'items' => [
        'course' => 'Курс',
        'discipline' => 'Дисциплина',
        'file' => 'Файл',
        'user' => 'Пользователь',
        'task' => 'Задание',
        'avatar' => 'Аватар',
        'role' => 'Роль',
        'course_list' => 'Курсы',
        'file_list' => 'Файлы',
        'user_list' => 'Пользователи',
        'task_list' => 'Задания',
        'invite_code' => 'Инвайт-код',
    ],

    // Status messages
    'status' => [
        'success' => 'успех',
        'error' => 'ошибка',
        'unauthenticated' => 'Неавторизированный запрос.',
    ],
];
