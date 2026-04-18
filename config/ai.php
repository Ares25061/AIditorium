<?php

return [
    'provider' => env('AI_PROVIDER', 'openrouter'),
    'base_url' => rtrim((string) env('AI_BASE_URL', 'https://openrouter.ai/api/v1'), '/'),
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'openai/gpt-4.1-mini'),
    'timeout' => (int) env('AI_TIMEOUT', 120),
    'max_extracted_chars' => (int) env('AI_MAX_EXTRACTED_CHARS', 60000),
    'max_excerpt_chars' => (int) env('AI_MAX_EXCERPT_CHARS', 8000),
    'max_files_per_review' => (int) env('AI_MAX_FILES_PER_REVIEW', 50),
    'max_csv_preview_rows' => (int) env('AI_MAX_CSV_PREVIEW_ROWS', 20),
    'max_sheet_preview_rows' => (int) env('AI_MAX_SHEET_PREVIEW_ROWS', 20),
    'max_sheet_preview_columns' => (int) env('AI_MAX_SHEET_PREVIEW_COLUMNS', 12),
    'zip' => [
        'max_entries' => (int) env('AI_ZIP_MAX_ENTRIES', 200),
        'max_depth' => (int) env('AI_ZIP_MAX_DEPTH', 5),
        'max_total_uncompressed_bytes' => (int) env('AI_ZIP_MAX_TOTAL_UNCOMPRESSED_BYTES', 52428800),
    ],
    'text_extensions' => [
        'txt', 'md', 'json', 'xml', 'yml', 'yaml', 'ini', 'env', 'csv', 'tsv',
        'php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'py', 'java', 'kt', 'kts',
        'cs', 'go', 'rs', 'rb', 'c', 'cpp', 'h', 'hpp', 'swift', 'sql', 'sh',
        'ps1', 'html', 'css', 'scss', 'less', 'blade.php',
    ],
    'code_extensions' => [
        'php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'py', 'java', 'kt', 'kts',
        'cs', 'go', 'rs', 'rb', 'c', 'cpp', 'h', 'hpp', 'swift', 'sql', 'sh',
        'ps1', 'html', 'css', 'scss', 'less',
    ],
    'supported_extensions' => [
        'txt', 'md', 'json', 'xml', 'yml', 'yaml', 'ini', 'env',
        'php', 'js', 'ts', 'jsx', 'tsx', 'vue', 'py', 'java', 'kt', 'kts',
        'cs', 'go', 'rs', 'rb', 'c', 'cpp', 'h', 'hpp', 'swift', 'sql', 'sh',
        'ps1', 'html', 'css', 'scss', 'less',
        'docx', 'doc', 'xlsx', 'xls', 'csv', 'tsv', 'zip',
    ],
];
