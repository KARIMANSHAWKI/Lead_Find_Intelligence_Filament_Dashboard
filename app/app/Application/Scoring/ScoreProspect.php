<?php

namespace App\Application\Scoring;

use App\Models\Prospect;

class ScoreProspect
{
    public function __construct(private LeadScoringService $scoring) {}

    public function execute(Prospect $prospect): int
    {
        $score = $this->scoring->calculate(
            icpFit: $prospect->icp_fit,
            productRelevance: $prospect->product_relevance,
            buyingSignals: $prospect->buyingSignals()->get(['strength'])->map(fn ($signal): array => ['strength' => $signal->strength])->all(),
            evidenceQuality: $prospect->evidence_quality,
        );
        $prospect->update(['score' => $score]);

        return $score;
    }
}
