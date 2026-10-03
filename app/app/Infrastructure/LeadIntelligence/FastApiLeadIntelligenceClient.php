<?php

namespace App\Infrastructure\LeadIntelligence;

use App\Application\LeadIntelligence\LeadIntelligenceResult;
use App\Models\IcpConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FastApiLeadIntelligenceClient
{
    public function __construct(private LeadIntelligenceResponseMapper $mapper) {}

    /** @param array{url: string, token: string} $callback */
    public function run(int $runId, IcpConfiguration $icp, array $callback): ?LeadIntelligenceResult
    {
        $baseUrl = rtrim((string) config('services.lead_intelligence.base_url'), '/');
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new LeadIntelligenceFailure('Lead Intelligence service is not configured.');
        }

        try {
            $response = Http::acceptJson()->asJson()
                ->connectTimeout(10)
                ->timeout(max(1, min(600, (int) config('services.lead_intelligence.timeout', 120))))
                ->withOptions(['allow_redirects' => false])
                ->post($baseUrl.'/api/v1/agent/run', [
                    'run_id' => $runId,
                    'callback' => $callback,
                    'client' => [
                        'product' => $icp->product,
                        'target_industries' => $icp->target_industries,
                        'location' => $icp->location,
                        'company_size' => ['min' => $icp->company_size_min, 'max' => $icp->company_size_max],
                        'ideal_customer_description' => $icp->ideal_customer_description,
                    ],
                ]);

        } catch (ConnectionException $exception) {
            $timeout = str_contains(strtolower($exception->getMessage()), 'timed out') || str_contains($exception->getMessage(), 'cURL error 28');
            throw new LeadIntelligenceFailure($timeout ? 'The agent run timed out. Please try again.' : 'Lead Intelligence service is temporarily unavailable.');
        }

        if (! $response->successful()) {
            throw new LeadIntelligenceFailure($response->status() >= 500
                ? 'Lead Intelligence service is temporarily unavailable.'
                : 'Lead Intelligence service could not process this request. Please review your ICP and try again.');
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new LeadIntelligenceFailure('The service returned an invalid response.');
        }
        if ($response->status() === 202) {
            if (($data['run_id'] ?? null) !== $runId || ! in_array($data['status'] ?? null, ['pending', 'running'], true)) {
                throw new LeadIntelligenceFailure('The service returned an invalid acknowledgement.');
            }

            return null;
        }

        return $this->mapper->map($runId, $data);
    }
}
