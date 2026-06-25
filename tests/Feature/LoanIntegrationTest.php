<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\External\SSOService;
use App\Services\External\SoapAuditService;
use App\Jobs\PublishLoanEvent;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use Firebase\JWT\JWT;

class LoanIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $privateKeyPem = "-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDL/qodZu57EOtT
croO0IFW7bPHEMx9n64t49S8vFE3uOucP81UFO59naqORVpI9fBpFhsYzvDRQ0MY
Om64IsrNXpq4FEgcd+R+OkW347Ha7vukM3o6V4ovBBtye48anYML2bMPQPZD+lFY
9ZCPsw/kkIEOin3u7OI6s3XrknZ5UYXf3O7XyTuIfzShMhUbYhlDqY/+Hf/kWFCz
t4dDHQ62GQnrq+Nno4v4kGyVr03VEw2VMH06RGVXWIrQYHsp8Z+ERRh8M1Znh9ir
nhfxb26KKw8gANFk6qg1M5ByRWDR7mawuahD5iPen4OtFAt25lfv6t5y1GSkDZAA
kIcIMYnTAgMBAAECggEABBBHID3QK1hrb6ad6TSjFvG2PpVplWkMZbKfpsDTl1oS
K3Mj6nReSddNsfbUtRZdGyLLGGPq7Sg7Wmyzjux2gL/NMjSJOTP0M8WCZQbeoJ9Z
GOzDlwzuXkBlMZMfhyazGRXVmMyw+yqt2ylNH2ewPdvZDkqY9pz2ZrfzdeVdgc4i
TDTsG2IvFmxP2hKaSVEZLcstwBJMrIv72nNinnHi1bFoBQbRGPgYeZJSJTlPkyAU
AujYqJ5Ljj2CskLuuGO8RAc3juL9Nx9zgN3HY6eFbHxCjPjKjLSHnQEPKbdZvIyS
59dWO8oG9Bx7jRd3lDwTHf07SnVEjFrnWjqo42MT4QKBgQD3FkzMgHkg3QPt+dRv
FqIrk3qbgIVBx9eODlp5lzxzYYaBp1Q0cKw0k0livITJpoqoXHoDKugboQKO3q3a
sbW6aqKUC9RTbwHKqCFzDojSDHhNm14i8jm3c1dDBSZmFusrwc2JPpQUMMbYVPxL
pfDwmaWR/k0MEl38aQpoHPL28wKBgQDTWm7VtOP23J9mvNjSpUZZ8/YtaB1aCSgl
SzY2lxcp+QwqpT6oPdDrBW2HPcVGCFzV+kjx+mM3E5C6AN6nRQ/whRjw27kYtxgF
hPOGTxBdJsr3ZfpUvGggmPD1IVQILr60VPECRLSmDj0ipwRNdEY5JPucU3m3Wy5o
hfidyD+ZoQKBgCQTtHjzlTwQKT+5B6SEuH8GVJOZ61sUc8vBGsLAK0pphfsuVGQn
w20VyFRLVFQhJgO5JPOLc0J9euMjbl0NL4ydf0mAhKr9/VP6wo+LIr0Qpwwl0FPn
7Dd19trJSLcFR6cm7/zHD1X3XUE+/2uIOirXNE6hw9wsXl65c5SKDYuzAoGAKnC2
YON6A1A6Ef9J6sKVZeq/PE7z1eiQzyxTLpMYa60+7DFSa6Y+FXN5kvasbmuveKhR
jYWh9qVPIoqaKyyLDtkrMPJuMLBTeog//nBR8OKhTxyDMBDFOAZ+HDsdDKeWU8/a
tYHmZJHnZNX03zjCASeT/sgkYNVGdGayjbxwSQECgYEA5Md7DjWTBxX9sTQoK5Y9
H+yo72rgTe68HK8cfcxcmeVHljnYP5Lbw0lQAqdB+Ttfob/iyusJcHgsZft+Bq9P
mMEb3fj7Z3i5Y86eRssWv6rGys7ylWbzWNwzR5PCa/DaMB+GL22boX3FELIZQGUS
KZqg69B8rcrJLgef39FTSok=
-----END PRIVATE KEY-----";

    protected array $jwksMock = [
        'keys' => [
            [
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => 'iae-central-2026',
                'n' => 'y_6qHWbuexDrU3K6DtCBVu2zxxDMfZ-uLePUvLxRN7jrnD_NVBTufZ2qjkVaSPXwaRYbGM7w0UNDGDpuuCLKzV6auBRIHHfkfjpFt-Ox2u77pDN6OleKLwQbcnuPGp2DC9mzD0D2Q_pRWPWQj7MP5JCBDop97uziOrN165J2eVGF39zu18k7iH80oTIVG2IZQ6mP_h3_5FhQs7eHQx0OthkJ66vjZ6OL-JBsla9N1RMNlTB9OkRlV1iK0GB7KfGfhEUYfDNWZ4fYq54X8W9uiisPIADRZOqoNTOQckVg0e5msLmoQ-Yj3p-DrRQLduZX7-rectRkpA2QAJCHCDGJ0w',
                'e' => 'AQAB'
            ]
        ]
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles secara manual untuk testing
        \Illuminate\Support\Facades\DB::table('roles')->insert([
            ['name' => 'Warga', 'slug' => 'warga', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Staf', 'slug' => 'staf', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Admin', 'slug' => 'admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Helper untuk membuat JWT token palsu yang valid
     */
    protected function generateTestToken(string $type, string $sub, array $profileOrApp): string
    {
        $payload = [
            'iss' => 'iae-central-mock',
            'sub' => $sub,
            'iat' => time() - 10,
            'exp' => time() + 3600,
            'token_type' => $type,
        ];

        if ($type === 'user') {
            $payload['profile'] = $profileOrApp;
        } else {
            $payload['app'] = $profileOrApp;
        }

        return JWT::encode($payload, $this->privateKeyPem, 'RS256', 'iae-central-2026');
    }

    public function test_request_without_api_key_returns_401()
    {
        // Request tanpa X-IAE-KEY header harus ditolak dengan 401
        $response = $this->getJson('/api/v1/loans');
        $response->assertStatus(401)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Autentikasi API Key gagal: Header X-IAE-KEY tidak valid atau tidak ditemukan.'
                 ]);
    }

    public function test_request_with_invalid_api_key_returns_401()
    {
        // Request dengan X-IAE-KEY header salah harus ditolak dengan 401
        $response = $this->withHeaders([
            'X-IAE-KEY' => 'SALAH_KEY_123'
        ])->getJson('/api/v1/loans');
        
        $response->assertStatus(401)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Autentikasi API Key gagal: Header X-IAE-KEY tidak valid atau tidak ditemukan.'
                 ]);
    }

    public function test_valid_user_token_authenticates_and_maps_role()
    {
        $this->mock(SSOService::class, function ($mock) {
            $mock->shouldReceive('getJwks')->andReturn($this->jwksMock);
        });

        $token = $this->generateTestToken('user', 'warga99@ktp.iae.id', [
            'name' => 'Budi Utomo',
            'nim' => '2026000099',
            'email' => 'warga99@ktp.iae.id'
        ]);

        $response = $this->withHeaders([
            'X-IAE-KEY' => '102022400363',
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/loans');

        $response->assertStatus(200);
        
        // Cek apakah user dibuat di local DB
        $user = User::where('email', 'warga99@ktp.iae.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Budi Utomo', $user->name);
        
        // Cek apakah role dipetakan ke 'warga'
        $this->assertTrue($user->hasRole('warga'));
    }

    public function test_warga_cannot_apply_loan_for_other_account()
    {
        $this->mock(SSOService::class, function ($mock) {
            $mock->shouldReceive('getJwks')->andReturn($this->jwksMock);
        });

        $token = $this->generateTestToken('user', 'warga99@ktp.iae.id', [
            'name' => 'Budi Utomo',
            'nim' => '2026000099',
            'email' => 'warga99@ktp.iae.id'
        ]);

        $response = $this->withHeaders([
            'X-IAE-KEY' => '102022400363',
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/loans', [
            'account_id' => 'warga01@ktp.iae.id', // spoofing other account
            'amount' => 5000000,
            'duration_months' => 12
        ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Akses ditolak: Anda hanya dapat mengajukan pinjaman untuk akun Anda sendiri.'
                 ]);
    }

    public function test_warga_can_apply_loan_success_with_soap_audit_and_amqp_queue()
    {
        Queue::fake();

        // Mock SSO JWKS
        $ssoMock = $this->mock(SSOService::class);
        $ssoMock->shouldReceive('getJwks')->andReturn($this->jwksMock);

        // Mock SOAP Audit Service agar mengembalikan receipt number tertentu
        $soapMock = $this->mock(SoapAuditService::class);
        $soapMock->shouldReceive('auditTransaction')->andReturn('IAE-LOG-2026-TEST1234');

        $token = $this->generateTestToken('user', 'warga99@ktp.iae.id', [
            'name' => 'Budi Utomo',
            'nim' => '2026000099',
            'email' => 'warga99@ktp.iae.id'
        ]);
        
        $response = $this->withHeaders([
            'X-IAE-KEY' => '102022400363',
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/loans', [
            'account_id' => 'warga99@ktp.iae.id',
            'amount' => 5000000,
            'duration_months' => 12
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.receipt_number', 'IAE-LOG-2026-TEST1234');
        $response->assertJsonPath('data.status', 'approved');

        // Pastikan tersimpan di local database
        $this->assertDatabaseHas('loans', [
            'account_id' => 'warga99@ktp.iae.id',
            'receipt_number' => 'IAE-LOG-2026-TEST1234'
        ]);

        // Pastikan job antrean (asinkron RabbitMQ) di-dispatch
        Queue::assertPushed(PublishLoanEvent::class);
    }
}
