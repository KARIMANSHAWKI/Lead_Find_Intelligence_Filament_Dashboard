<?php

namespace App\Filament;

use App\Models\AgentRun;
use Illuminate\Support\Str;

class IntelligencePresentation
{
    public static function label(?string $value): string
    {
        return filled($value) ? Str::headline($value) : '—';
    }

    public static function color(?string $value): string
    {
        return match ($value) {
            'high', 'running' => 'primary',
            'completed', 'qualified' => 'success',
            'medium', 'pending' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public static function priority(?int $score): string
    {
        return match (true) {
            $score === null => 'Not scored yet',
            $score >= 80 => 'High Priority',
            $score >= 60 => 'Medium Priority',
            default => 'Low Priority',
        };
    }

    public static function safeUrl(?string $url): ?string
    {
        if (! $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
            && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS) ? $url : null;
    }

    public static function duration(AgentRun $run): string
    {
        $end = $run->completed_at ?? $run->failed_at;
        if (! $run->started_at || ! $end) {
            return '—';
        }
        $seconds = max(0, (int) $run->started_at->diffInSeconds($end));

        return sprintf('%dm %02ds', intdiv($seconds, 60), $seconds % 60);
    }

    public static function emailUrl(?string $email, string $company, ?string $name = null, bool $meeting = false): ?string
    {
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $company = preg_replace('/[\r\n]+/', ' ', $company);
        $greeting = filled($name) ? trim($name) : 'there';

        return 'mailto:'.rawurlencode($email).'?'.http_build_query([
            'subject' => ($meeting ? 'Meeting request — ' : 'Introduction — ').$company,
            'body' => "Hi {$greeting},\n\n".($meeting
                ? "Would you be available for a short introductory meeting to discuss {$company}'s needs? Please let me know a convenient time."
                : "I'd like to connect and learn more about {$company}'s needs.")."\n\nBest regards,",
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function meetingUrl(?string $email, string $company): ?string
    {
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return 'https://calendar.google.com/calendar/r/eventedit?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => 'Introduction — '.preg_replace('/[\r\n]+/', ' ', $company),
            'add' => $email,
            'details' => 'An introductory conversation about '.$company.'. Choose a time and review the invitation before sending.',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function phoneUrl(?string $phone): ?string
    {
        $number = preg_replace('/[^\d+]/', '', $phone ?? '');

        return preg_match('/^\+?\d{3,20}$/', $number) ? 'tel:'.$number : null;
    }

    public static function emailVerification(?string $status): string
    {
        return match ($status) {
            'valid' => 'Email verified',
            'accept_all' => 'Accept-all domain',
            'invalid' => 'Invalid email',
            default => filled($status) ? Str::headline($status) : 'Verification not supplied',
        };
    }
}
