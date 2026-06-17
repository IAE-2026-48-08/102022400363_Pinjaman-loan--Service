<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\External\SSOService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PublishLoanEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $routingKey;
    protected array $payload;

    /**
     * Create a new job instance.
     *
     * @param string $routingKey
     * @param array $payload
     */
    public function __construct(string $routingKey, array $payload)
    {
        $this->routingKey = $routingKey;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @param SSOService $ssoService
     * @return void
     */
    public function handle(SSOService $ssoService): void
    {
        // 1. Dapatkan Token M2M (karena job ini berjalan di background worker)
        $token = $ssoService->getM2MToken();
        if (!$token) {
            throw new \Exception("AMQP Publisher Gagal: Tidak dapat memperoleh token M2M.");
        }

        $publishUrl = env('AMQP_PUBLISH_URL', 'https://iae-sso.virtualfri.id/api/v1/messages/publish');

        // 2. Kirim POST request ke RabbitMQ Gateway REST API
        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
            ->post($publishUrl, [
                'routing_key' => $this->routingKey,
                'payload' => $this->payload
            ]);

        // 3. Tangani hasil pengiriman
        if ($response->successful()) {
            Log::info("AMQP Event berhasil dipublish ke RabbitMQ Dosen. Routing Key: {$this->routingKey}");
        } else {
            Log::error("AMQP Event gagal dipublish. HTTP Status: " . $response->status() . ". Respon: " . $response->body());
            throw new \Exception("Gagal mempublikasikan event ke RabbitMQ. Status: " . $response->status());
        }
    }
}
