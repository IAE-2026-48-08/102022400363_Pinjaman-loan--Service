<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\Api\V1\StoreLoanRequest;
use App\Http\Resources\Api\V1\LoanResource;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Loans", description: "Endpoints manajemen pengajuan pinjaman")]
class LoanController extends Controller
{
    protected LoanService $loanService;

    public function __construct(LoanService $loanService)
    {
        $this->loanService = $loanService;
    }

    #[OA\Get(
        path: "/api/v1/loans",
        summary: "Daftar semua pengajuan pinjaman",
        description: "Admin/Staf melihat semua pinjaman. Warga hanya melihat pinjaman miliknya sendiri.",
        security: [["ApiKeyAuth" => []], ["BearerAuth" => []]],
        tags: ["Loans"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Berhasil",
                content: new OA\JsonContent(ref: "#/components/schemas/SuccessListResponse")
            ),
            new OA\Response(
                response: 401,
                description: "Tidak terautentikasi",
                content: new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
            ),
            new OA\Response(
                response: 403,
                description: "Akses ditolak",
                content: new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
            )
        ]
    )]
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
                'message' => 'Akses ditolak: Peran Anda tidak dikenali.',
                'errors' => null
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => LoanResource::collection($loans)
        ]);
    }

    #[OA\Post(
        path: "/api/v1/loans",
        summary: "Ajukan pinjaman baru",
        description: "Hanya warga yang dapat mengajukan pinjaman. account_id harus sesuai email pengguna yang login.",
        security: [["ApiKeyAuth" => []], ["BearerAuth" => []]],
        tags: ["Loans"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["account_id", "amount", "duration_months"],
                properties: [
                    new OA\Property(property: "account_id", type: "string", example: "warga13@ktp.iae.id"),
                    new OA\Property(property: "amount", type: "number", format: "float", example: 5000000),
                    new OA\Property(property: "duration_months", type: "integer", example: 12)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Pinjaman berhasil diajukan",
                content: new OA\JsonContent(ref: "#/components/schemas/SuccessSingleResponse")
            ),
            new OA\Response(
                response: 401,
                description: "Tidak terautentikasi",
                content: new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
            ),
            new OA\Response(
                response: 403,
                description: "Akses ditolak",
                content: new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
            ),
            new OA\Response(
                response: 422,
                description: "Validasi gagal",
                content: new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
            )
        ]
    )]
    public function store(StoreLoanRequest $request): JsonResponse
    {
        $user = auth()->user();

        // Hanya warga yang boleh mengajukan pinjaman
        if (!$user->hasRole('warga')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Hanya warga yang dapat melakukan pengajuan pinjaman.',
                'errors' => null
            ], 403);
        }

        // Pastikan account_id di request sesuai dengan email warga yang sedang login (keamanan data)
        if ($request->input('account_id') !== $user->email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Anda hanya dapat mengajukan pinjaman untuk akun Anda sendiri.',
                'errors' => null
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

        return response()->json([
            'status' => 'success',
            'message' => $statusMessage,
            'data' => new LoanResource($loan)
        ], 201);
    }

    #[OA\Get(
        path: "/api/v1/loans/{id}",
        summary: "Detail satu pengajuan pinjaman",
        description: "Admin/Staf dapat melihat semua pinjaman. Warga hanya dapat melihat miliknya sendiri.",
        security: [["ApiKeyAuth" => []], ["BearerAuth" => []]],
        tags: ["Loans"],
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                description: "UUID pinjaman",
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Berhasil",
                content: new OA\JsonContent(ref: "#/components/schemas/SuccessSingleResponse")
            ),
            new OA\Response(
                response: 401,
                description: "Tidak terautentikasi",
                content: new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
            ),
            new OA\Response(
                response: 403,
                description: "Akses ditolak",
                content: new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
            ),
            new OA\Response(
                response: 404,
                description: "Pinjaman tidak ditemukan",
                content: new OA\JsonContent(ref: "#/components/schemas/ErrorResponse")
            )
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        $loan = $this->loanService->getLoanDetails($id);

        if (!$loan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Detail pengajuan pinjaman tidak ditemukan.',
                'errors' => null
            ], 404);
        }

        // Batasi akses jika pengguna adalah warga biasa dan mencoba melihat data warga lain
        if ($user->hasRole('warga') && $loan->account_id !== $user->email) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Anda tidak memiliki wewenang untuk melihat detail pinjaman ini.',
                'errors' => null
            ], 403);
        }

        if (!$user->hasRole('admin') && !$user->hasRole('staf') && !$user->hasRole('warga')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak: Peran Anda tidak dikenali.',
                'errors' => null
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail pengajuan pinjaman berhasil ditemukan.',
            'data' => new LoanResource($loan)
        ]);
    }
}
