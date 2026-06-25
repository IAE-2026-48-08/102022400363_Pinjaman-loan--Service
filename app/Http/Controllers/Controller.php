<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Pinjaman (Loan) Service API",
    version: "1.0.0",
    description: "Mini-service for Loan/Pinjaman management. Part of the IAE Tugas 3 ecosystem.",
    contact: new OA\Contact(email: "102022400363@student.iae.id")
)]
#[OA\Server(url: "/", description: "Local / Docker Server")]
#[OA\SecurityScheme(
    securityScheme: "ApiKeyAuth",
    type: "apiKey",
    in: "header",
    name: "X-IAE-KEY",
    description: "API Key (NIM mahasiswa, e.g. 102022400363)"
)]
#[OA\SecurityScheme(
    securityScheme: "BearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "SSO Bearer Token dari iae-sso.virtualfri.id"
)]
#[OA\Schema(
    schema: "LoanResource",
    type: "object",
    properties: [
        new OA\Property(property: "id", type: "string", example: "uuid-xxxx"),
        new OA\Property(property: "account_id", type: "string", example: "warga13@ktp.iae.id"),
        new OA\Property(property: "amount", type: "number", format: "float", example: 5000000),
        new OA\Property(property: "duration_months", type: "integer", example: 12),
        new OA\Property(property: "interest_rate", type: "number", format: "float", example: 0.05),
        new OA\Property(property: "monthly_installment", type: "number", format: "float", example: 441667),
        new OA\Property(property: "remaining_balance", type: "number", format: "float", example: 5000000),
        new OA\Property(property: "status", type: "string", enum: ["approved", "rejected"], example: "approved"),
        new OA\Property(property: "rejection_reason", type: "string", nullable: true, example: null),
        new OA\Property(property: "receipt_number", type: "string", nullable: true, example: "IAE-LOG-2026-ABCD1234"),
        new OA\Property(property: "created_at", type: "string", format: "date-time"),
        new OA\Property(property: "updated_at", type: "string", format: "date-time")
    ]
)]
#[OA\Schema(
    schema: "SuccessListResponse",
    type: "object",
    properties: [
        new OA\Property(property: "status", type: "string", example: "success"),
        new OA\Property(property: "message", type: "string", example: "Berhasil"),
        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/LoanResource"))
    ]
)]
#[OA\Schema(
    schema: "SuccessSingleResponse",
    type: "object",
    properties: [
        new OA\Property(property: "status", type: "string", example: "success"),
        new OA\Property(property: "message", type: "string", example: "Berhasil"),
        new OA\Property(property: "data", ref: "#/components/schemas/LoanResource")
    ]
)]
#[OA\Schema(
    schema: "ErrorResponse",
    type: "object",
    properties: [
        new OA\Property(property: "status", type: "string", example: "error"),
        new OA\Property(property: "message", type: "string", example: "Autentikasi API Key gagal."),
        new OA\Property(property: "errors", type: "object", nullable: true, example: null)
    ]
)]
abstract class Controller
{
    //
}
