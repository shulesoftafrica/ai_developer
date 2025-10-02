<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'sprint_id',
        'type',
        'title',
        'description',
        'content',
        'status',
        'priority',
        'assigned_to',
        'locked_at',
        'locked_by',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'type' => TaskType::class,
        'status' => TaskStatus::class,
        'content' => 'array',
        'metadata' => 'array',
        'locked_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function aiLogs(): HasMany
    {
        return $this->hasMany(AiLog::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null && $this->locked_at->isFuture();
    }

    public function lock(string $worker, int $seconds = 3600): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        return $this->update([
            'locked_at' => now()->addSeconds($seconds),
            'locked_by' => $worker,
        ]);
    }

    public function unlock(): bool
    {
        return $this->update([
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }

    public function canTransitionTo(TaskStatus $status): bool
    {
        return $this->status->canTransitionTo($status);
    }

    public function transitionTo(TaskStatus $status): bool
    {
        if (!$this->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;
        
        if ($status === TaskStatus::COMPLETED) {
            $this->completed_at = now();
        }

        return $this->save();
    }
}