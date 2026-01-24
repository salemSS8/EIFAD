<?php

namespace App\Domain\Auth\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

/**
 * Firebase Authentication Service.
 * Verifies Firebase ID tokens and extracts user identity.
 */
class FirebaseAuthService
{
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
    private const CACHE_KEY = 'firebase_public_keys';
    private const CACHE_TTL = 3600; // 1 hour

    private string $projectId;

    public function __construct()
    {
        $this->projectId = config('firebase.project_id');
    }

    /**
     * Verify a Firebase ID token and return the decoded payload.
     *
     * @param string $idToken The Firebase ID token from the client
     * @return array The decoded token payload
     * @throws Exception If token verification fails
     */
    public function verifyIdToken(string $idToken): array
    {
        $publicKeys = $this->getPublicKeys();

        // Decode token header to get the key ID
        $tokenParts = explode('.', $idToken);
        if (count($tokenParts) !== 3) {
            throw new Exception('Invalid token format');
        }

        $header = json_decode(base64_decode($tokenParts[0]), true);
        if (!isset($header['kid'])) {
            throw new Exception('Token missing key ID');
        }

        $keyId = $header['kid'];
        if (!isset($publicKeys[$keyId])) {
            // Refresh keys and try again
            $publicKeys = $this->getPublicKeys(forceRefresh: true);
            if (!isset($publicKeys[$keyId])) {
                throw new Exception('Invalid token key ID');
            }
        }

        try {
            $decoded = JWT::decode($idToken, new Key($publicKeys[$keyId], 'RS256'));
            $payload = (array) $decoded;

            // Verify claims
            $this->verifyClaims($payload);

            return $payload;
        } catch (Exception $e) {
            Log::error('Firebase token verification failed', [
                'error' => $e->getMessage()
            ]);
            throw new Exception('Token verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Extract user info from a verified token payload.
     */
    public function extractUserInfo(array $payload): array
    {
        return [
            'firebase_uid' => $payload['sub'] ?? null,
            'email' => $payload['email'] ?? null,
            'email_verified' => $payload['email_verified'] ?? false,
            'name' => $payload['name'] ?? null,
            'picture' => $payload['picture'] ?? null,
            'provider' => $payload['firebase']['sign_in_provider'] ?? 'unknown',
        ];
    }

    /**
     * Verify token claims.
     */
    private function verifyClaims(array $payload): void
    {
        $now = time();

        // Check expiration
        if (!isset($payload['exp']) || $payload['exp'] < $now) {
            throw new Exception('Token has expired');
        }

        // Check issued at time
        if (!isset($payload['iat']) || $payload['iat'] > $now) {
            throw new Exception('Token issued in the future');
        }

        // Check audience
        if (!isset($payload['aud']) || $payload['aud'] !== $this->projectId) {
            throw new Exception('Invalid token audience');
        }

        // Check issuer
        $expectedIssuer = 'https://securetoken.google.com/' . $this->projectId;
        if (!isset($payload['iss']) || $payload['iss'] !== $expectedIssuer) {
            throw new Exception('Invalid token issuer');
        }

        // Check subject (user ID)
        if (!isset($payload['sub']) || empty($payload['sub'])) {
            throw new Exception('Token missing user ID');
        }

        // Check authentication time
        if (!isset($payload['auth_time']) || $payload['auth_time'] > $now) {
            throw new Exception('Invalid authentication time');
        }
    }

    /**
     * Get Google's public keys for token verification.
     */
    private function getPublicKeys(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && config('firebase.cache_token_verification')) {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached) {
                return $cached;
            }
        }

        try {
            $response = Http::get(self::GOOGLE_CERTS_URL);

            if ($response->failed()) {
                throw new Exception('Failed to fetch public keys');
            }

            $keys = $response->json();

            if (config('firebase.cache_token_verification')) {
                Cache::put(self::CACHE_KEY, $keys, self::CACHE_TTL);
            }

            return $keys;
        } catch (Exception $e) {
            Log::error('Failed to fetch Firebase public keys', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
