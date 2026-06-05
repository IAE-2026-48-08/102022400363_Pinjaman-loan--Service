<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccountServiceClient
{
    protected string $baseUrl;
    protected bool $mockMode;

    public function __construct()
    {
        // Mendapatkan URL dari environment dengan default fallback
        $this->baseUrl = env('SERVICE_1_ACCOUNT_URL', 'http://localhost:8001/api/v1');
        $this->mockMode = env('SERVICE_MOCK_MODE', true);
    }

    /**
     * Mengambil detail dan saldo akun tertentu (GET /api/v1/accounts/{id})
     *
     * @param string $accountId
     * @return array
     */
    public function getAccountDetails(string $accountId): array
    {
        if ($this->mockMode) {
            return $this->getMockAccountDetails($accountId);
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/accounts/{$accountId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Gagal mengambil detail akun {$accountId} dari Service 1. Status: {$response->status()}");
        } catch (\Exception $e) {
            Log::error("Koneksi gagal ke Service 1 (AccountDetails): " . $e->getMessage());
        }

        // Fallback ke mock data jika HTTP request gagal (agar demo tetap berjalan)
        return $this->getMockAccountDetails($accountId);
    }

    /**
     * Memvalidasi kelayakan akun nasabah (POST /accounts/{id}/validate)
     * Note: Dari alur bisnis, ini diarahkan ke POST /accounts/{id}/validate
     *
     * @param string $accountId
     * @return array
     */
    public function validateAccount(string $accountId): array
    {
        if ($this->mockMode) {
            return $this->getMockValidationStatus($accountId);
        }

        try {
            // Sesuai dengan spesifikasi endpoint di Service 1
            $response = Http::timeout(5)->post("{$this->baseUrl}/accounts/{$accountId}/validate");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Gagal memvalidasi akun {$accountId} di Service 1. Status: {$response->status()}");
        } catch (\Exception $e) {
            Log::error("Koneksi gagal ke Service 1 (Validate): " . $e->getMessage());
        }

        // Fallback ke mock jika HTTP request gagal
        return $this->getMockValidationStatus($accountId);
    }

    /**
     * Simulasi mock detail akun
     */
    protected function getMockAccountDetails(string $accountId): array
    {
        // Untuk simulasi limit pinjaman atau pengujian, kita bisa bedakan respon berdasarkan ID akun
        $balance = 10000000.00; // default 10 juta
        $status = 'active';

        if ($accountId === 'inactive-acc') {
            $status = 'inactive';
        } elseif ($accountId === 'low-balance') {
            $balance = 50000.00; // saldo sangat rendah
        }

        return [
            'status' => 'success',
            'data' => [
                'id' => $accountId,
                'name' => 'Nasabah Contoh ' . $accountId,
                'email' => 'nasabah.' . $accountId . '@email.com',
                'balance' => $balance,
                'status' => $status,
                'created_at' => now()->subMonths(12)->toIso8601String(),
            ]
        ];
    }

    /**
     * Simulasi mock status validasi
     */
    protected function getMockValidationStatus(string $accountId): array
    {
        $isValid = true;
        $message = "Akun aktif dan terverifikasi.";

        if ($accountId === 'unverified-acc') {
            $isValid = false;
            $message = "Akun belum terverifikasi.";
        } elseif ($accountId === 'inactive-acc') {
            $isValid = false;
            $message = "Akun ditangguhkan atau tidak aktif.";
        }

        return [
            'status' => 'success',
            'data' => [
                'account_id' => $accountId,
                'is_valid' => $isValid,
                'message' => $message,
                'validated_at' => now()->toIso8601String()
            ]
        ];
    }
}
