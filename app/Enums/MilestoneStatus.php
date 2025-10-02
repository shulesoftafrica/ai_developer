<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::SKIPPED => 'Skipped',
        };
    }
}