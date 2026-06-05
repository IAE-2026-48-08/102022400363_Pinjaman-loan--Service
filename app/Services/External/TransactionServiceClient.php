<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TransactionServiceClient
{
    protected string $baseUrl;
    protected bool $mockMode;

    public function __construct()
    {
        // Mendapatkan URL dari environment dengan default fallback
        $this->baseUrl = env('SERVICE_2_TRANSACTION_URL', 'http://localhost:8002/api/v1');
        $this->mockMode = env('SERVICE_MOCK_MODE', true);
    }

    /**
     * Mengambil riwayat transaksi milik nasabah tertentu (GET /api/v1/transactions/account/{account_id})
     *
     * @param string $accountId
     * @return array
     */
    public function getAccountTransactions(string $accountId): array
    {
        if ($this->mockMode) {
            return $this->getMockTransactions($accountId);
        }

        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/transactions/account/{$accountId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("Gagal mengambil transaksi akun {$accountId} dari Service 2. Status: {$response->status()}");
        } catch (\Exception $e) {
            Log::error("Koneksi gagal ke Service 2 (AccountTransactions): " . $e->getMessage());
        }

        // Fallback ke mock data jika HTTP request gagal (agar demo tetap berjalan)
        return $this->getMockTransactions($accountId);
    }

    /**
     * Simulasi mock riwayat transaksi nasabah
     * Kita sediakan beberapa skenario akun untuk mempermudah demo credit scoring:
     * 1. default: Akun sehat dengan gaji Rp 8.000.000 / bulan.
     * 2. 'low-income': Akun dengan gaji kecil Rp 1.500.000 / bulan.
     * 3. 'no-income': Akun tanpa riwayat transaksi masuk (gaji).
     */
    protected function getMockTransactions(string $accountId): array
    {
        $transactions = [];
        $now = now();

        if ($accountId === 'no-income') {
            // Hanya ada pengeluaran kecil, tidak ada income
            $transactions = [
                [
                    'id' => 'tx-101',
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => 50000.00,
                    'description' => 'Beli Pulsa',
                    'created_at' => $now->copy()->subDays(5)->toIso8601String()
                ]
            ];
        } elseif ($accountId === 'low-income') {
            // Pendapatan bulanan kecil Rp 1.500.000
            for ($i = 1; $i <= 3; $i++) {
                $monthOffset = $i - 1;
                $txDate = $now->copy()->subMonths($monthOffset)->day(5);
                
                $transactions[] = [
                    'id' => 'tx-low-c-' . $i,
                    'account_id' => $accountId,
                    'type' => 'credit',
                    'amount' => 1500000.00,
                    'description' => 'Gaji Bulanan Part-time',
                    'created_at' => $txDate->toIso8601String()
                ];
                
                $transactions[] = [
                    'id' => 'tx-low-d-' . $i,
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => 1000000.00,
                    'description' => 'Biaya Hidup',
                    'created_at' => $txDate->copy()->addDays(2)->toIso8601String()
                ];
            }
        } else {
            // Skenario Default / Normal (Sehat): Pendapatan bulanan Rp 8.000.000
            for ($i = 1; $i <= 3; $i++) {
                $monthOffset = $i - 1;
                $txDate = $now->copy()->subMonths($monthOffset)->day(1); // Gajian tiap tanggal 1
                
                // Income
                $transactions[] = [
                    'id' => 'tx-reg-c-' . $i,
                    'account_id' => $accountId,
                    'type' => 'credit',
                    'amount' => 8000000.00,
                    'description' => 'Transfer Gaji PT Jaya',
                    'created_at' => $txDate->toIso8601String()
                ];

                // Debit (pengeluaran rutin)
                $transactions[] = [
                    'id' => 'tx-reg-d1-' . $i,
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => 3000000.00,
                    'description' => 'Bayar Sewa Kos',
                    'created_at' => $txDate->copy()->addDays(1)->toIso8601String()
                ];

                $transactions[] = [
                    'id' => 'tx-reg-d2-' . $i,
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => 1500000.00,
                    'description' => 'Belanja Bulanan',
                    'created_at' => $txDate->copy()->addDays(3)->toIso8601String()
                ];
            }
        }

        return [
            'status' => 'success',
            'data' => $transactions
        ];
    }
}
