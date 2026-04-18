<?php

namespace App\Enums;

enum ReviewRunStatus: string
{
    case QUEUED = 'queued';
    case EXTRACTING = 'extracting';
    case ANALYZING = 'analyzing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
