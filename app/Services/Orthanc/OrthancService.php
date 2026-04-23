<?php

namespace App\Services\Orthanc;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Base service untuk komunikasi dengan Orthanc REST API.
 *
 * Tanggung jawab:
 *  - Menyediakan HTTP client terkonfigurasi (basic auth, timeout, SSL).
 *  - Menyediakan helper GET/POST/PUT/DELETE dengan error handling konsisten.
 *  - Logging ringan untuk audit/debug.
 *  - Tidak menyimpan PHI. Semua payload hanya di-forward.
 */
class OrthancService
{
    protected string $baseUrl;

    protected string $username;

    protected string $password;

    protected int $timeout;

    protected bool $verifySsl;

    public function __construct()
    {
        $config = (array) config('orthanc');

        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'http://localhost:8042'), '/');
        $this->username = (string) ($config['username'] ?? 'orthanc');
        $this->password = (string) ($config['password'] ?? 'orthanc');
        $this->timeout = (int) ($config['timeout'] ?? 10);
        $this->verifySsl = (bool) ($config['verify_ssl'] ?? true);
    }

    /**
     * Bangun HTTP client dengan konfigurasi default.
     */
    protected function client(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withBasicAuth($this->username, $this->password)
            ->acceptJson();

        if (! $this->verifySsl) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * GET JSON dari Orthanc. Mengembalikan array.
     *
     * @return array<mixed>
     */
    public function get(string $endpoint, array $query = []): array
    {
        $response = $this->client()->get($this->normalize($endpoint), $query);

        return $this->handleJson('GET', $endpoint, $response);
    }

    /**
     * GET raw response (untuk binary stream, misal download DICOM).
     */
    public function getRaw(string $endpoint, array $query = []): Response
    {
        $response = $this->client()->get($this->normalize($endpoint), $query);

        $this->guardSuccess('GET', $endpoint, $response);

        return $response;
    }

    /**
     * POST JSON body.
     *
     * @return array<mixed>
     */
    public function post(string $endpoint, array $data = []): array
    {
        $response = $this->client()->asJson()->post($this->normalize($endpoint), $data);

        return $this->handleJson('POST', $endpoint, $response);
    }

    /**
     * PUT JSON body.
     *
     * @return array<mixed>
     */
    public function put(string $endpoint, array $data = []): array
    {
        $response = $this->client()->asJson()->put($this->normalize($endpoint), $data);

        return $this->handleJson('PUT', $endpoint, $response);
    }

    /**
     * DELETE resource. Tidak mengembalikan body.
     */
    public function deleteResource(string $endpoint): void
    {
        $response = $this->client()->delete($this->normalize($endpoint));

        $this->guardSuccess('DELETE', $endpoint, $response);
    }

    /**
     * Normalisasi endpoint agar selalu diawali slash.
     */
    protected function normalize(string $endpoint): string
    {
        return '/'.ltrim($endpoint, '/');
    }

    /**
     * Validasi response dan decode JSON menjadi array.
     *
     * @return array<mixed>
     */
    protected function handleJson(string $method, string $endpoint, Response $response): array
    {
        $this->guardSuccess($method, $endpoint, $response);

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Pastikan response sukses, jika tidak lempar RuntimeException dengan log.
     */
    protected function guardSuccess(string $method, string $endpoint, Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('Orthanc API error', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'body' => $this->truncate((string) $response->body(), 500),
        ]);

        throw new RuntimeException(sprintf(
            'Orthanc API %s %s failed with status %d',
            $method,
            $endpoint,
            $response->status()
        ));
    }

    protected function truncate(string $value, int $length): string
    {
        return mb_strlen($value) <= $length ? $value : mb_substr($value, 0, $length).'…';
    }
}
