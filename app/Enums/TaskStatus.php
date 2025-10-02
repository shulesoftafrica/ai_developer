<?php

namespace App\Enums;

enum TaskStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }
    
    public function canTransitionTo(TaskStatus $status): bool
    {
        return match($this) {
            self::PENDING => in_array($status, [self::IN_PROGRESS, self::CANCELLED]),
            self::IN_PROGRESS => in_array($status, [self::COMPLETED, self::FAILED, self::CANCELLED]),
            self::COMPLETED => false,
            self::FAILED => in_array($status, [self::PENDING, self::CANCELLED]),
            self::CANCELLED => in_array($status, [self::PENDING]),
        };
    }
}