<?php

namespace App\Models;

use App\Enums\AgentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'milestone_id',
        'run_id',
        'agent_type',
        'prompt',
        'response',
        'model',
        'tokens_used',
        'execution_time_ms',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'agent_type' => AgentType::class,
        'prompt' => 'array',
        'response' => 'array',
        'tokens_used' => 'integer',
        'execution_time_ms' => 'integer',
        'metadata' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function getTotalCost(): float
    {
        // Approximate cost calculation for Claude Sonnet 4
        // Input tokens: $3 per million, Output tokens: $15 per million
        $inputTokens = $this->tokens_used * 0.8; // Estimate 80% input
        $outputTokens = $this->tokens_used * 0.2; // Estimate 20% output
        
        return ($inputTokens * 3 / 1000000) + ($outputTokens * 15 / 1000000);
    }
}