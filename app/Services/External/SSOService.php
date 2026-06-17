<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SSOService
{
    protected string $ssoUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->ssoUrl = env('SSO_URL', 'https://iae-sso.virtualfri.id');
        $this->apiKey = env('SSO_API_KEY', 'KEY-MHS-391');
    }

    /**
     * Mengambil JWKS Keys dari SSO Server dan mencachenya selama 24 jam
     *
     * @return array
     */
    public function getJwks(): array
    {
        return Cache::remember('iae_sso_jwks', 86400, function () {
            try {
                $response = Http::timeout(5)->get("{$this->ssoUrl}/api/v1/auth/jwks");
                
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['keys'])) {
                        return $data;
                    }
                }
                
                Log::error("Gagal mengambil JWKS dari SSO Server. Status: " . $response->status());
            } catch (\Exception $e) {
                Log::error("Pengecualian saat mengambil JWKS: " . $e->getMessage());
            }

            // Fallback: Jika gagal, kita sediakan hardcoded key agar sistem tidak breakdown
            return [
                "keys" => [
                    [
                        "kty" => "RSA",
                        "use" => "sig",
                        "alg" => "RS256",
                        "kid" => "iae-central-2026",
                        "n" => "xF6NotMkNlUOHf0yg3APp2R9KeSDKkd_J2mAbkDIteaWLfphxsH6bffWR4ws4jPw3ScSBnycZttE_xfouOYgrTwYJWW9YN6poZusuur42jkvzgpX-BSOsouItfhJ8a4wQUibFCQarsFkKiYkeGuW_F6cr0O1oBgwFbbaR4bx1_RpIYvzkWQES-viZUnv7_u0EYMnwfFqMr0rDP78hDNzsqhlLPBiUNxKncQvW-q0ddXe4C8CpU5seH43jur8QFT6lE4LUeObTXHzXhX4qi-Mw4j16lR2ts5wPEynctcmd4eUobHxdHc_Nas5TNJC0VXO6BOISYj_ySvnP1mx5PSHNQ",
                        "e" => "AQAB"
                    ]
                ]
            ];
        });
    }

    /**
     * Meminta M2M Token dan mencachenya selama 50 menit
     *
     * @return string|null
     */
    public function getM2MToken(): ?string
    {
        return Cache::remember('iae_sso_m2m_token', 3000, function () {
            try {
                $response = Http::timeout(5)->post("{$this->ssoUrl}/api/v1/auth/token", [
                    'api_key' => $this->apiKey
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $data['token'] ?? $data['access_token'] ?? null;
                }

                Log::error("Gagal mengambil M2M Token dari SSO Server. Status: " . $response->status());
            } catch (\Exception $e) {
                Log::error("Pengecualian saat mengambil M2M Token: " . $e->getMessage());
            }

            return null;
        });
    }
}
