<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sprint extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'metadata' => 'array',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' &&
               ($this->start_date === null || $this->start_date->isPast()) &&
               ($this->end_date === null || $this->end_date->isFuture());
    }

    public function getProgressAttribute(): array
    {
        $tasks = $this->tasks;
        $total = $tasks->count();
        
        if ($total === 0) {
            return ['total' => 0, 'completed' => 0, 'percentage' => 0];
        }

        $completed = $tasks->where('status', 'completed')->count();
        
        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => round(($completed / $total) * 100, 2),
        ];
    }
}