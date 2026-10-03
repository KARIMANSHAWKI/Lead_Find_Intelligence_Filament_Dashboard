<?php

namespace App\Http\Controllers\Api;

use App\Application\LeadIntelligence\CompleteLeadIntelligenceRun;
use App\Http\Controllers\Controller;
use App\Infrastructure\LeadIntelligence\LeadIntelligenceFailure;
use App\Infrastructure\LeadIntelligence\LeadIntelligenceResponseMapper;
use App\Models\AgentRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AgentRunResultController extends Controller
{
    public function __invoke(Request $request, int $run, LeadIntelligenceResponseMapper $mapper, CompleteLeadIntelligenceRun $completion): JsonResponse
    {
        $record = AgentRun::findOrFail($run);
        $token = $request->bearerToken();
        abort_unless($token && $record->callback_token_hash && hash_equals($record->callback_token_hash, hash('sha256', $token)), 401);
        $data = $request->validate(['run_id' => ['required', 'integer'], 'status' => ['required', 'in:completed,failed']]);
        abort_unless(is_int($request->input('run_id')) && $data['run_id'] === $run, 422, 'The callback run ID does not match.');

        if (in_array($record->status, ['completed', 'failed'], true)) {
            abort_unless($record->status === $data['status'], 409, 'This agent run has already finished.');

            return response()->json(['run_id' => $record->id, 'status' => $record->status]);
        }
        try {
            if ($data['status'] === 'failed') {
                $message = match ($request->input('error_code')) {
                    'timeout' => 'The agent run timed out. Please try again.',
                    'service_unavailable' => 'Lead Intelligence service is temporarily unavailable.',
                    'invalid_response' => 'The service returned an invalid response.',
                    default => 'The agent could not complete its research. Please try again.',
                };
                $record = $completion->fail($record, $message);
            } else {
                try {
                    $result = $mapper->map($run, $request->all());
                } catch (LeadIntelligenceFailure $exception) {
                    return response()->json(['message' => $exception->getMessage()], 422);
                }
                $record = $completion->complete($record, $result);
            }

        } catch (LeadIntelligenceFailure $exception) {
            return response()->json(['message' => 'This agent run has already finished.'], 409);
        } catch (Throwable $exception) {
            Log::warning('Agent callback persistence failed.', ['run_id' => $run, 'exception' => $exception::class]);

            return response()->json(['message' => 'Results could not be saved. Please retry this callback.'], 503);
        }

        return response()->json(['run_id' => $record->id, 'status' => $record->status]);
    }
}
