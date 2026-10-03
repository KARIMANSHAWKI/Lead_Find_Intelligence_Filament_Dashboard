<?php

namespace App\Infrastructure\LeadIntelligence;

use App\Application\LeadIntelligence\LeadIntelligenceResult;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LeadIntelligenceResponseMapper
{
    /** @param array<string, mixed> $data */
    public function map(int $runId, array $data): LeadIntelligenceResult
    {
        if (! is_int($data['run_id'] ?? null)) {
            throw new LeadIntelligenceFailure('The service returned an invalid response.');
        }
        if ($data['run_id'] !== $runId) {
            throw new LeadIntelligenceFailure('The service returned a response for a different run.');
        }
        $text = ['bail', 'required', 'string', 'regex:/\S/u'];
        $level = Rule::in(['high', 'medium', 'low']);
        $validator = Validator::make($data, [
            'run_id' => ['required', 'integer', 'min:1'],
            'prospects' => ['present', 'array', 'list', 'max:1000'],
            'prospects.*' => ['required', 'array'],
            'prospects.*.company_name' => [...$text, 'max:255'],
            'prospects.*.website' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'prospects.*.icp_fit' => ['required', 'string', $level],
            'prospects.*.product_relevance' => ['required', 'string', $level],
            'prospects.*.evidence_quality' => ['nullable', 'string', $level],
            'prospects.*.why_now' => [...$text, 'max:100000'],
            'prospects.*.buying_signals' => ['present', 'array', 'list', 'max:100'],
            'prospects.*.buying_signals.*' => ['required', 'array'],
            'prospects.*.buying_signals.*.type' => [...$text, 'max:100'],
            'prospects.*.buying_signals.*.evidence' => [...$text, 'max:100000'],
            'prospects.*.buying_signals.*.source_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'prospects.*.buying_signals.*.strength' => ['required', 'string', $level],
            'prospects.*.contact_info' => ['sometimes', 'array'],
            'prospects.*.contact_info.company_emails' => ['sometimes', 'array', 'list', 'max:10'],
            'prospects.*.contact_info.company_emails.*' => ['required', 'array'],
            'prospects.*.contact_info.company_emails.*.email' => ['required', 'string', 'email:rfc', 'max:254'],
            'prospects.*.contact_info.company_emails.*.confidence' => ['nullable', 'integer', 'between:0,100'],
            'prospects.*.contact_info.company_emails.*.verification_status' => ['nullable', 'string', 'max:32'],
            'prospects.*.contact_info.company_phone_numbers' => ['sometimes', 'array', 'list', 'max:10'],
            'prospects.*.contact_info.company_phone_numbers.*' => ['required', 'string', 'max:64'],
            'prospects.*.contact_info.people' => ['sometimes', 'array', 'list', 'max:5'],
            'prospects.*.contact_info.people.*' => ['required', 'array'],
            'prospects.*.contact_info.people.*.first_name' => ['nullable', 'string', 'max:100'],
            'prospects.*.contact_info.people.*.last_name' => ['nullable', 'string', 'max:100'],
            'prospects.*.contact_info.people.*.email' => ['required', 'string', 'email:rfc', 'max:254'],
            'prospects.*.contact_info.people.*.position' => ['nullable', 'string', 'max:255'],
            'prospects.*.contact_info.people.*.seniority' => ['nullable', 'string', 'max:64'],
            'prospects.*.contact_info.people.*.department' => ['nullable', 'string', 'max:64'],
            'prospects.*.contact_info.people.*.linkedin_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'prospects.*.contact_info.people.*.phone_number' => ['nullable', 'string', 'max:64'],
            'prospects.*.contact_info.people.*.confidence' => ['nullable', 'integer', 'between:0,100'],
            'prospects.*.contact_info.people.*.verification_status' => ['nullable', 'string', 'max:32'],
        ]);
        if ($validator->fails()) {
            throw new LeadIntelligenceFailure('The service returned an invalid response.');
        }
        $prospects = [];
        foreach ($data['prospects'] as $prospect) {
            $signals = [];
            foreach ($prospect['buying_signals'] as $signal) {
                $signals[] = [
                    'type' => trim($signal['type']), 'evidence' => trim($signal['evidence']),
                    'source_url' => $signal['source_url'] ?? null, 'strength' => $signal['strength'],
                ];
            }
            $contactInfo = $prospect['contact_info'] ?? [];
            $companyEmails = collect($contactInfo['company_emails'] ?? [])->map(fn (array $contact): array => [
                'email' => trim($contact['email']),
                'confidence' => $contact['confidence'] ?? null,
                'verification_status' => $contact['verification_status'] ?? null,
            ])->values()->all();
            $companyPhones = collect($contactInfo['company_phone_numbers'] ?? [])
                ->map(fn (string $phone): string => trim($phone))->filter()->unique()->values()->all();
            $people = collect($contactInfo['people'] ?? [])->map(fn (array $contact): array => [
                'first_name' => $contact['first_name'] ?? null,
                'last_name' => $contact['last_name'] ?? null,
                'email' => trim($contact['email']),
                'position' => $contact['position'] ?? null,
                'seniority' => $contact['seniority'] ?? null,
                'department' => $contact['department'] ?? null,
                'linkedin_url' => $contact['linkedin_url'] ?? null,
                'phone_number' => $contact['phone_number'] ?? null,
                'confidence' => $contact['confidence'] ?? null,
                'verification_status' => $contact['verification_status'] ?? null,
            ])->values()->all();
            $prospects[] = [
                'company_name' => trim($prospect['company_name']), 'website' => $prospect['website'] ?? null,
                'icp_fit' => $prospect['icp_fit'], 'product_relevance' => $prospect['product_relevance'],
                'evidence_quality' => $prospect['evidence_quality'] ?? null,
                'why_now' => trim($prospect['why_now']), 'buying_signals' => $signals,
                'contact_info' => [
                    'company_emails' => $companyEmails,
                    'company_phone_numbers' => $companyPhones,
                    'people' => $people,
                ],
            ];
        }

        return new LeadIntelligenceResult($runId, $prospects);
    }
}
