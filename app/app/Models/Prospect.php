<?php

namespace App\Models;

use Database\Factories\ProspectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['agent_run_id', 'company_name', 'website', 'source', 'icp_fit', 'product_relevance', 'evidence_quality', 'why_now', 'contact_info', 'score', 'status'])]
class Prospect extends Model
{
    /** @use HasFactory<ProspectFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Prospect $prospect): void {
            if ($prospect->exists && ! $prospect->isDirty(['agent_run_id', 'organization_id'])) {
                return;
            }

            $organizationId = AgentRun::query()->whereKey($prospect->agent_run_id)->value('organization_id');

            if ($organizationId === null || (int) $organizationId !== (int) $prospect->organization_id) {
                throw ValidationException::withMessages(['agent_run_id' => 'The prospect and agent run must belong to the same organization.']);
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
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * @return HasMany<BuyingSignal, $this>
     */
    public function buyingSignals(): HasMany
    {
        return $this->hasMany(BuyingSignal::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_info' => 'array',
            'score' => 'integer',
        ];
    }
}
