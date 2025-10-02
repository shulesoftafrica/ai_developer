<?php

namespace App\Enums;

enum TaskType: string
{
    case BUG = 'bug';
    case FEATURE = 'feature';
    case UPGRADE = 'upgrade';
    case MAINTENANCE = 'maintenance';
    
    public function label(): string
    {
        return match($this) {
            self::BUG => 'Bug Fix',
            self::FEATURE => 'New Feature',
            self::UPGRADE => 'Upgrade',
            self::MAINTENANCE => 'Maintenance',
        };
    }
    
    public function getJobClass(): string
    {
        return match($this) {
            self::BUG => \App\Jobs\ProcessBugTask::class,
            self::FEATURE => \App\Jobs\ProcessNewTask::class,
            self::UPGRADE => \App\Jobs\ProcessUpgradeTask::class,
            self::MAINTENANCE => \App\Jobs\ProcessNewTask::class,
        };
    }
}