<?php

namespace App\Application\Scoring;

use InvalidArgumentException;

class LeadScoringService
{
    /** @param list<array{strength: string}> $buyingSignals */
    public function calculate(string $icpFit, string $productRelevance, array $buyingSignals, ?string $evidenceQuality): int
    {
        $icp = $this->points($icpFit, 40, 25);
        $relevance = $this->points($productRelevance, 20, 10);
        $evidence = $evidenceQuality === null ? 0 : $this->points($evidenceQuality, 10, 5);
        $signals = 0;
        foreach ($buyingSignals as $signal) {
            $signals += match ($signal['strength']) {
                'high' => 15, 'medium' => 8, 'low' => 3,
                default => throw new InvalidArgumentException('Invalid buying signal strength.'),
            };
        }

        return max(0, min(100, $icp + $relevance + $evidence + min(30, $signals)));
    }

    private function points(string $classification, int $high, int $medium): int
    {
        return match ($classification) {
            'high' => $high, 'medium' => $medium, 'low' => 0,
            default => throw new InvalidArgumentException('Invalid intelligence classification.'),
        };
    }
}
