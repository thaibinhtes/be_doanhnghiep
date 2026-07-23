<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaVerifier
{
    public function isEnabled(): bool
    {
        if (! (bool) config('services.recaptcha.enabled', true)) {
            return false;
        }

        return filled(config('services.recaptcha.site_key'))
            && filled(config('services.recaptcha.project_id'))
            && filled(config('services.recaptcha.api_key'));
    }

    public function siteKey(): ?string
    {
        $key = config('services.recaptcha.site_key');

        return filled($key) ? (string) $key : null;
    }

    public function action(): string
    {
        return (string) config('services.recaptcha.action', 'LOGIN');
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        $siteKey = (string) config('services.recaptcha.site_key');
        $projectId = (string) config('services.recaptcha.project_id');
        $apiKey = (string) config('services.recaptcha.api_key');
        $expectedAction = $this->action();
        $minScore = (float) config('services.recaptcha.min_score', 0.5);

        $url = sprintf(
            'https://recaptchaenterprise.googleapis.com/v1/projects/%s/assessments?key=%s',
            rawurlencode($projectId),
            rawurlencode($apiKey),
        );

        $event = [
            'token' => $token,
            'expectedAction' => $expectedAction,
            'siteKey' => $siteKey,
        ];

        if (filled($remoteIp)) {
            $event['userIpAddress'] = $remoteIp;
        }

        try {
            $response = Http::timeout(8)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'event' => $event,
                ]);

            if (! $response->ok()) {
                Log::warning('reCAPTCHA Enterprise HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return false;
            }

            $payload = $response->json() ?? [];
            $tokenProperties = $payload['tokenProperties'] ?? null;

            if (! is_array($tokenProperties) || ! ($tokenProperties['valid'] ?? false)) {
                Log::warning('reCAPTCHA Enterprise token invalid', [
                    'reason' => $tokenProperties['invalidReason'] ?? 'UNKNOWN',
                ]);

                return false;
            }

            $actualAction = (string) ($tokenProperties['action'] ?? '');
            if ($actualAction !== '' && strcasecmp($actualAction, $expectedAction) !== 0) {
                Log::warning('reCAPTCHA Enterprise action mismatch', [
                    'expected' => $expectedAction,
                    'actual' => $actualAction,
                ]);

                return false;
            }

            $score = $payload['riskAnalysis']['score'] ?? null;
            if (is_numeric($score) && (float) $score < $minScore) {
                Log::warning('reCAPTCHA Enterprise score too low', [
                    'score' => $score,
                    'min_score' => $minScore,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA Enterprise verify failed', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
