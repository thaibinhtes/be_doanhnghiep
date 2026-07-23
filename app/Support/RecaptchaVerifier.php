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

        // Widget chỉ cần site key; API key dùng khi verify phía server.
        return filled(config('services.recaptcha.site_key'));
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

    public function canVerify(): bool
    {
        return $this->isEnabled()
            && filled(config('services.recaptcha.project_id'))
            && filled(config('services.recaptcha.api_key'));
    }

    /**
     * @return array{ok: bool, reason: string|null}
     */
    public function verifyDetailed(?string $token, ?string $remoteIp = null): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => true, 'reason' => null];
        }

        $token = trim((string) $token);
        if ($token === '') {
            return ['ok' => false, 'reason' => 'MISSING'];
        }

        if (! $this->canVerify()) {
            Log::warning('reCAPTCHA Enterprise missing project_id or api_key');

            return ['ok' => false, 'reason' => 'CONFIG'];
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

                return ['ok' => false, 'reason' => 'HTTP'];
            }

            $payload = $response->json() ?? [];
            $tokenProperties = $payload['tokenProperties'] ?? null;

            if (! is_array($tokenProperties) || ! ($tokenProperties['valid'] ?? false)) {
                $reason = (string) ($tokenProperties['invalidReason'] ?? 'UNKNOWN');

                Log::warning('reCAPTCHA Enterprise token invalid', [
                    'reason' => $reason,
                ]);

                return ['ok' => false, 'reason' => $reason];
            }

            $actualAction = (string) ($tokenProperties['action'] ?? '');
            if ($actualAction !== '' && strcasecmp($actualAction, $expectedAction) !== 0) {
                Log::warning('reCAPTCHA Enterprise action mismatch', [
                    'expected' => $expectedAction,
                    'actual' => $actualAction,
                ]);

                return ['ok' => false, 'reason' => 'ACTION_MISMATCH'];
            }

            $score = $payload['riskAnalysis']['score'] ?? null;
            if (is_numeric($score) && (float) $score < $minScore) {
                Log::warning('reCAPTCHA Enterprise score too low', [
                    'score' => $score,
                    'min_score' => $minScore,
                ]);

                return ['ok' => false, 'reason' => 'SCORE'];
            }

            return ['ok' => true, 'reason' => null];
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA Enterprise verify failed', [
                'message' => $e->getMessage(),
            ]);

            return ['ok' => false, 'reason' => 'EXCEPTION'];
        }
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        return $this->verifyDetailed($token, $remoteIp)['ok'];
    }
}
