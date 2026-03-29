<?php

namespace Tests\Unit;

use App\Support\SlugHelper;
use PHPUnit\Framework\TestCase;

class SlugHelperTest extends TestCase
{
    public function test_slug_with_letters_remains_valid_after_normalization(): void
    {
        $slug = SlugHelper::normalize('Тест 123');

        $this->assertSame('test-123', $slug);
        $this->assertTrue(SlugHelper::containsLetters($slug));
    }

    public function test_numeric_slug_is_rejected_after_normalization(): void
    {
        $slug = SlugHelper::normalize('123 456');

        $this->assertSame('123-456', $slug);
        $this->assertFalse(SlugHelper::containsLetters($slug));
    }
}
