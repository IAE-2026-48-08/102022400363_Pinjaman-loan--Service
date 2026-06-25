<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = env('IAE_KEY', '102022400363');
        $apiKey = $request->header('X-IAE-KEY');

        if (!$apiKey || $apiKey !== $expectedKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Autentikasi API Key gagal: Header X-IAE-KEY tidak valid atau tidak ditemukan.'
            ], 401);
        }

        return $next($request);
    }
}
