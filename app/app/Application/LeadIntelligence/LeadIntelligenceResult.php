<?php

namespace App\Application\LeadIntelligence;

final readonly class LeadIntelligenceResult
{
    /**
     * @param  list<array<string, mixed>>  $prospects
     */
    public function __construct(public int $runId, public array $prospects) {}
}
