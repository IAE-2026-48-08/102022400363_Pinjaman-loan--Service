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
     * Ambil daftar pengajuan pinjaman (GET /api/v1/loans)
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();

        if ($user->hasRole('admin') || $user->hasRole('staf')) {
            $loans = $this->loanService->getAllLoans();
            $message = 'Daftar semua pengajuan pinjaman berhasil diambil.';
        } elseif ($user->hasRole('warga')) {
            // Warga hanya bisa melihat pinjaman miliknya sendiri
            $loans = \App\Models\Loan::where('account_id', $user->email)
                ->orderBy('created_at', 'desc')
                ->get();
            $message = 'Daftar pengajuan pinjaman Anda berhasil diambil.';
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Peran Anda tidak dikenali.'
            ], 403);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => $message,
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
        $user = auth()->user();

        // Hanya warga yang boleh mengajukan pinjaman
        if (!$user->hasRole('warga')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Hanya warga yang dapat melakukan pengajuan pinjaman.'
            ], 403);
        }

        // Pastikan account_id di request sesuai dengan email warga yang sedang login (keamanan data)
        if ($request->input('account_id') !== $user->email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Anda hanya dapat mengajukan pinjaman untuk akun Anda sendiri.'
            ], 403);
        }

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
        $user = auth()->user();
        $loan = $this->loanService->getLoanDetails($id);

        if (!$loan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Detail pengajuan pinjaman tidak ditemukan.'
            ], 404);
        }

        // Batasi akses jika pengguna adalah warga biasa dan mencoba melihat data warga lain
        if ($user->hasRole('warga') && $loan->account_id !== $user->email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Anda tidak memiliki wewenang untuk melihat detail pinjaman ini.'
            ], 403);
        }

        if (!$user->hasRole('admin') && !$user->hasRole('staf') && !$user->hasRole('warga')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Peran Anda tidak dikenali.'
            ], 403);
        }

        return (new LoanResource($loan))
            ->additional([
                'status' => 'success',
                'message' => 'Detail pengajuan pinjaman berhasil ditemukan.'
            ])
            ->response();
    }
}
