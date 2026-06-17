<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\External\SSOService;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class VerifySSOToken
{
    protected SSOService $ssoService;

    public function __construct(SSOService $ssoService)
    {
        $this->ssoService = $ssoService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Ambil Authorization Header
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Autentikasi gagal: Token tidak ditemukan atau format tidak valid.'
            ], 401);
        }

        $token = substr($authHeader, 7);

        try {
            // 2. Ambil JWKS dan Verifikasi Token
            $jwks = $this->ssoService->getJwks();
            $keys = JWK::parseKeySet($jwks);
            
            // Decode token (jika kedaluwarsa atau signature tidak valid akan melempar exception)
            $decoded = JWT::decode($token, $keys);
            
            // 3. Ekstrak data berdasarkan tipe token (user / m2m)
            $tokenType = $decoded->token_type ?? 'user';
            
            if ($tokenType === 'user') {
                $email = $decoded->profile->email ?? $decoded->sub;
                $name = $decoded->profile->name ?? 'SSO User';
                $roleSlug = 'warga';
            } else {
                $email = ($decoded->sub ?? 'system') . '@system.iae.id';
                $name = $decoded->app->name ?? 'System Client';
                $roleSlug = 'staf';
            }

            // 4. Sinkronkan dengan tabel users lokal
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt(Str::random(16))
                ]
            );

            // 5. Petakan ke tabel roles lokal
            $role = Role::where('slug', $roleSlug)->first();
            if ($role && !$user->roles()->where('role_id', $role->id)->exists()) {
                $user->roles()->attach($role->id);
            }

            // 6. Login secara lokal di Laravel Guard
            Auth::login($user);

            // 7. Simpan payload token di request attributes agar bisa diakses di Controller
            $request->attributes->set('sso_payload', $decoded);
            $request->attributes->set('sso_token', $token);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Autentikasi gagal: ' . $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}
