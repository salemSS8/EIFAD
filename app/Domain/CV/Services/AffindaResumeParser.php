<?php

namespace App\Domain\CV\Services;

use App\Domain\CV\DTOs\CanonicalResumeDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Affinda Resume Parser Service.
 * 
 * Uses Affinda API for professional CV/Resume parsing.
 * This is the PRIMARY parser as per documentation.
 * Regex-based parsing is used only as fallback.
 */
class AffindaResumeParser
{
    private string $apiKey;
    private string $baseUrl;
    private string $workspaceId;
    private CanonicalResumeMapper $mapper;

    public function __construct(CanonicalResumeMapper $mapper)
    {
        $this->apiKey = config('services.affinda.api_key', '');
        $this->baseUrl = config('services.affinda.base_url', 'https://api.affinda.com/v3');
        $this->workspaceId = config('services.affinda.workspace_id', '');
        $this->mapper = $mapper;
    }

    /**
     * Check if Affinda is configured and available.
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->workspaceId);
    }

    /**
     * Parse a CV file using Affinda API.
     *
     * @param string $filePath Path to CV file
     * @return CanonicalResumeDTO|null
     */
    public function parseFile(string $filePath): ?CanonicalResumeDTO
    {
        if (!$this->isAvailable()) {
            Log::warning('AffindaResumeParser: API key not configured, skipping');
            return null;
        }

        if (!file_exists($filePath)) {
            Log::error('AffindaResumeParser: File not found', ['path' => $filePath]);
            return null;
        }

        try {
            // Upload document to Affinda
            $response = Http::withToken($this->apiKey)
                ->timeout(120)
                ->attach(
                    'file',
                    file_get_contents($filePath),
                    basename($filePath)
                )
                ->post("{$this->baseUrl}/documents", [
                    'workspace' => $this->workspaceId,
                    'wait' => 'true', // Wait for processing to complete
                ]);

            if ($response->failed()) {
                Log::error('AffindaResumeParser: API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            // Check if parsing was successful
            if (!isset($data['data'])) {
                Log::warning('AffindaResumeParser: No data in response');
                return null;
            }

            Log::info('AffindaResumeParser: CV parsed successfully', [
                'document_id' => $data['meta']['identifier'] ?? 'unknown'
            ]);

            // Map to Canonical DTO
            return $this->mapper->fromAffindaResponse($data);
        } catch (\Exception $e) {
            Log::error('AffindaResumeParser: Exception', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Parse CV from base64 encoded content.
     */
    public function parseBase64(string $base64Content, string $filename = 'resume.pdf'): ?CanonicalResumeDTO
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(120)
                ->post("{$this->baseUrl}/documents", [
                    'workspace' => $this->workspaceId,
                    'file' => [
                        'name' => $filename,
                        'data' => $base64Content,
                    ],
                    'wait' => 'true',
                ]);

            if ($response->failed()) {
                return null;
            }

            return $this->mapper->fromAffindaResponse($response->json());
        } catch (\Exception $e) {
            Log::error('AffindaResumeParser: Base64 parse failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Parse CV from URL.
     */
    public function parseUrl(string $url): ?CanonicalResumeDTO
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(120)
                ->post("{$this->baseUrl}/documents", [
                    'workspace' => $this->workspaceId,
                    'url' => $url,
                    'wait' => 'true',
                ]);

            if ($response->failed()) {
                return null;
            }

            return $this->mapper->fromAffindaResponse($response->json());
        } catch (\Exception $e) {
            Log::error('AffindaResumeParser: URL parse failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get raw Affinda response without mapping.
     */
    public function parseFileRaw(string $filePath): ?array
    {
        if (!$this->isAvailable() || !file_exists($filePath)) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(120)
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post("{$this->baseUrl}/documents", [
                    'workspace' => $this->workspaceId,
                    'wait' => 'true',
                ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
