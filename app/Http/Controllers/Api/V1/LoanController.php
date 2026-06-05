<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\Api\V1\StoreLoanRequest;
use App\Http\HttpKernel\Response;
use App\Http\Resources\Api\V1\LoanResource;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;

class LoanController extends Controller
{
    protected LoanService $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    /**
     * Ambil daftar semua pengajuan pinjaman (GET /api/v1/loans)
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $loans = $this->loanService->getAllLoans();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Daftar semua pengajuan pinjaman berhasil diambil.',
            'data' => LoanResource::collection($loans)
        ]);
    }

    /**
     * Ajukan pinjaman baru (POST /api/v1/loans)
     *
     * @param StoreLoanRequest $request
     * @return JsonResponse
     */
    public function store(StoreLoanRequest $request): JsonResponse
    {
        $loan = $this->loanService->applyForLoan(
            $request->input('account_id'),
            $request->input('amount'),
            $request->input('duration_months')
        );

        $statusMessage = $loan->status === 'approved' 
            ? 'Pengajuan pinjaman disetujui secara otomatis berdasarkan riwayat transaksi.' 
            : 'Pengajuan pinjaman ditolak berdasarkan analisis riwayat transaksi.';

        return (new LoanResource($loan))
            ->additional([
                'status' => 'success',
                'message' => $statusMessage,
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Ambil detail & status pinjaman tertentu (GET /api/v1/loans/{id})
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $loan = $this->loanService->getLoanDetails($id);

        if (!$loan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Detail pengajuan pinjaman tidak ditemukan.'
            ], 404);
        }

        return (new LoanResource($loan))
            ->additional([
                'status' => 'success',
                'message' => 'Detail pengajuan pinjaman berhasil ditemukan.'
            ])
            ->response();
    }
}
