<?php

namespace App\Services\External;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SoapAuditService
{
    protected string $soapUrl;
    protected SSOService $ssoService;

    public function __construct(SSOService $ssoService)
    {
        $this->soapUrl = env('SOAP_AUDIT_URL', 'https://iae-sso.virtualfri.id/soap/v1/audit');
        $this->ssoService = $ssoService;
    }

    /**
     * Mengirim audit pengajuan pinjaman ke SOAP Legacy Audit Service
     *
     * @param string $activityName Nama aktivitas bisnis (misal: LoanApplied)
     * @param array $logData Data transaksi yang akan dijadikan JSON CDATA
     * @return string|null Mengembalikan ReceiptNumber jika sukses, null jika gagal
     */
    public function auditTransaction(string $activityName, array $logData): ?string
    {
        // 1. Dapatkan M2M Bearer Token (SOAP endpoint membutuhkan service-level credentials)
        $token = $this->ssoService->getM2MToken();

        if (!$token) {
            Log::error("SOAP Audit Gagal: Tidak dapat memperoleh M2M token.");
            return null;
        }

        // 2. Susun XML Envelope kaku
        $jsonContent = json_encode($logData);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
 <soap:Body>
 <iae:AuditRequest>
 <iae:TeamID>TEAM-04</iae:TeamID>
 <iae:ActivityName>' . htmlspecialchars($activityName) . '</iae:ActivityName>
 <iae:LogContent><![CDATA[' . $jsonContent . ']]></iae:LogContent>
 </iae:AuditRequest>
 </soap:Body>
</soap:Envelope>';

        try {
            // 3. Kirim SOAP Request menggunakan HTTP POST
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'Content-Length' => strlen($xml)
                ])
                ->withBody($xml, 'text/xml')
                ->post($this->soapUrl);

            // 4. Parsing respon SOAP XML
            if ($response->successful()) {
                $soapRes = $response->body();
                $xmlElement = new \SimpleXMLElement($soapRes);
                $xmlElement->registerXPathNamespace('iae', 'http://iae.central/audit');
                
                $statusResult = $xmlElement->xpath('//iae:Status');
                $receiptResult = $xmlElement->xpath('//iae:ReceiptNumber');
                
                $status = count($statusResult) > 0 ? (string)$statusResult[0] : null;
                $receiptNumber = count($receiptResult) > 0 ? (string)$receiptResult[0] : null;
                
                if (strtoupper($status) === 'SUCCESS' && $receiptNumber) {
                    Log::info("SOAP Audit Sukses. Receipt: {$receiptNumber}");
                    return $receiptNumber;
                } else {
                    Log::warning("SOAP Audit ditolak/gagal oleh server. Status: {$status}");
                }
            } else {
                Log::error("SOAP Audit Gagal. HTTP Status: " . $response->status() . ". Response: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("SOAP Audit Gagal karena Pengecualian: " . $e->getMessage());
        }

        return null;
    }
}
