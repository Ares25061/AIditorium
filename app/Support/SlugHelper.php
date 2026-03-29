<?php

namespace App\Support;

use Illuminate\Support\Str;

class SlugHelper
{
    public static function normalize(?string $value): string
    {
        return Str::slug((string) $value);
    }

    public static function containsLetters(string $slug): bool
    {
        return preg_match('/[a-z]/', $slug) === 1;
    }
}
