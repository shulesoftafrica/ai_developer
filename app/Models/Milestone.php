<?php

namespace App\Models;

use App\Enums\AgentType;
use App\Enums\MilestoneStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'sequence',
        'title',
        'description',
        'agent_type',
        'status',
        'input_data',
        'output_data',
        'git_branch',
        'git_commit',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'agent_type' => AgentType::class,
        'status' => MilestoneStatus::class,
        'input_data' => 'array',
        'output_data' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function aiLogs(): HasMany
    {
        return $this->hasMany(AiLog::class);
    }

    public function start(): bool
    {
        if ($this->status !== MilestoneStatus::PENDING) {
            return false;
        }

        return $this->update([
            'status' => MilestoneStatus::IN_PROGRESS,
            'started_at' => now(),
        ]);
    }

    public function complete(array $outputData = []): bool
    {
        if ($this->status !== MilestoneStatus::IN_PROGRESS) {
            return false;
        }

        return $this->update([
            'status' => MilestoneStatus::COMPLETED,
            'output_data' => $outputData,
            'completed_at' => now(),
        ]);
    }

    public function fail(string $reason = ''): bool
    {
        return $this->update([
            'status' => MilestoneStatus::FAILED,
            'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
            'completed_at' => now(),
        ]);
    }

    public function getBranchName(): string
    {
        return config('app.git.branch_prefix') . $this->task_id . '-' . $this->sequence;
    }
}