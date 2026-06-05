<?php

namespace App\Services;

use App\Models\Loan;
use App\Services\External\AccountServiceClient;
use App\Services\External\TransactionServiceClient;
use Illuminate\Validation\ValidationException;

class LoanService
{
    protected AccountServiceClient $accountClient;
    protected TransactionServiceClient $transactionClient;

    public function __construct(
        AccountServiceClient $accountClient,
        TransactionServiceClient $transactionClient
    ) {
        $this->accountClient = $accountClient;
        $this->transactionClient = $transactionClient;
    }

    /**
     * Memproses pengajuan pinjaman baru
     *
     * @param string $accountId
     * @param float $amount
     * @param int $durationMonths
     * @return Loan
     * @throws ValidationException
     */
    public function applyForLoan(string $accountId, float $amount, int $durationMonths): Loan
    {
        // 1. Ambil detail akun & status keaktifan dari Service 1
        $accountDetails = $this->accountClient->getAccountDetails($accountId);
        if (empty($accountDetails['data']) || ($accountDetails['data']['status'] ?? '') !== 'active') {
            throw ValidationException::withMessages([
                'account_id' => ['Akun nasabah tidak ditemukan atau sedang tidak aktif di sistem.'],
            ]);
        }

        // 2. Validasi kelayakan akun nasabah (apakah aktif & terverifikasi) di Service 1
        $validationResult = $this->accountClient->validateAccount($accountId);
        if (empty($validationResult['data']) || !($validationResult['data']['is_valid'] ?? false)) {
            $msg = $validationResult['data']['message'] ?? 'Akun tidak memenuhi syarat verifikasi.';
            throw ValidationException::withMessages([
                'account_id' => ["Validasi Akun Gagal: {$msg}"],
            ]);
        }

        // 3. Tarik riwayat transaksi nasabah dari Service 2 sebagai bahan penilaian finansial
        $transactionResult = $this->transactionClient->getAccountTransactions($accountId);
        $transactions = $transactionResult['data'] ?? [];

        // 4. Hitung cicilan bulanan dengan bunga flat 5% (0.0500)
        $interestRate = 0.0500;
        $totalRepayable = $amount * (1 + $interestRate);
        $monthlyInstallment = $totalRepayable / $durationMonths;

        // 5. Analisis kelayakan finansial (Credit Scoring)
        // Hitung total uang masuk (type = 'credit') selama 3 bulan terakhir
        $totalIncome = 0;
        foreach ($transactions as $tx) {
            if (($tx['type'] ?? '') === 'credit') {
                $totalIncome += floatval($tx['amount'] ?? 0);
            }
        }
        
        // Rata-rata pendapatan bulanan (berdasarkan 3 bulan riwayat data)
        $averageMonthlyIncome = $totalIncome / 3;

        // Aturan: Pendapatan bulanan minimal harus 3x dari cicilan bulanan yang diajukan
        $status = 'approved';
        $rejectionReason = null;
        $remainingBalance = $totalRepayable;

        if ($averageMonthlyIncome < ($monthlyInstallment * 3)) {
            $status = 'rejected';
            $rejectionReason = sprintf(
                "Rata-rata pendapatan bulanan nasabah (Rp %s) tidak mencukupi untuk cicilan bulanan (Rp %s). Diperlukan pendapatan minimal 3x cicilan (Rp %s).",
                number_format($averageMonthlyIncome, 0, ',', '.'),
                number_format($monthlyInstallment, 0, ',', '.'),
                number_format($monthlyInstallment * 3, 0, ',', '.')
            );
            $remainingBalance = 0; // Jika ditolak, tidak ada saldo pinjaman yang tersisa
        }

        // 6. Simpan pengajuan pinjaman ke database
        return Loan::create([
            'account_id' => $accountId,
            'amount' => $amount,
            'duration_months' => $durationMonths,
            'interest_rate' => $interestRate,
            'monthly_installment' => $monthlyInstallment,
            'remaining_balance' => $remainingBalance,
            'status' => $status,
            'rejection_reason' => $rejectionReason,
        ]);
    }

    /**
     * Mengambil daftar semua pengajuan pinjaman
     */
    public function getAllLoans()
    {
        return Loan::orderBy('created_at', 'desc')->get();
    }

    /**
     * Mengambil detail pinjaman tertentu berdasarkan ID
     */
    public function getLoanDetails(string $loanId): ?Loan
    {
        return Loan::find($loanId);
    }
}
