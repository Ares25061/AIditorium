<?php

namespace App\AI\Contracts;

use App\AI\DTO\CompiledReviewPayload;
use App\AI\DTO\ReviewResult;

interface LLMClientInterface
{
    public function analyze(CompiledReviewPayload $payload): ReviewResult;
}
