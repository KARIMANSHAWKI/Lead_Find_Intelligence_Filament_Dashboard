<?php

namespace App\Models;

use Database\Factories\AgentRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['status', 'started_at', 'completed_at', 'failed_at', 'error_message', 'candidates_found', 'prospects_qualified'])]
#[Hidden(['callback_token_hash'])]
class AgentRun extends Model
{
    /** @use HasFactory<AgentRunFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    protected static function booted(): void
    {
        static::updating(function (AgentRun $run): void {
            if ($run->isDirty('organization_id') && $run->prospects()->where('organization_id', '!=', $run->organization_id)->exists()) {
                throw ValidationException::withMessages(['organization_id' => 'An agent run with prospects cannot move to another organization.']);
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return HasMany<Prospect, $this>
     */
    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'candidates_found' => 'integer',
            'prospects_qualified' => 'integer',
        ];
    }
}
