<?php

/**
 * JWT Service for handling JWT tokens.
 *
 * PHP version 8.4
 *
 * @category Service
 * @package  App\Service
 * @license  MIT License
 * @link     https://api-platform.com
 */

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\Stub\Exception;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service for handling JWT tokens.
 *
 * @category Service
 * @package  App\Service
 * @license  MIT License
 * @link     https://api-platform.com
 */
class JwtService
{
    /**
     * Logger service.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $_logger;

    /**
     * Parameter bag for accessing configuration.
     *
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $_parameterBag;

    /**
     * Constructor.
     *
     * @param LoggerInterface       $logger       Logger service
     * @param ParameterBagInterface $parameterBag Parameter bag for configuration
     */
    public function __construct(
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag
    ) {
        $this->_logger = $logger;
        $this->_parameterBag = $parameterBag;
    }

    /**
     * Create a JWT token with the given payload.
     *
     * @param array  $payload   The payload to encode
     * @param string $secretKey The secret key to use (defaults to test key in test env)
     * 
     * @return string The encoded JWT token
     */
    public function createToken(array $payload, ?string $secretKey = null): string
    {
        $key = $secretKey ?? $this->_getSecretKey();
        return JWT::encode($payload, $key, 'HS256');
    }

    /**
     * Extract and decode the JWT token from the Authorization header.
     *
     * @param string|null $authHeader   The Authorization header value
     * @param string|null $secretKey    The secret key to use (defaults to test key in test env)
     * @param bool        $throwOnError Whether to throw an exception on error
     * 
     * @return object|null The decoded token payload or null if invalid
     * 
     * @throws UnauthorizedHttpException If token is invalid and throwOnError is true
     */
    public function extractAndDecodeToken(
        ?string $authHeader,
        ?string $secretKey = null,
        bool $throwOnError = true
    ): ?object {
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            if ($throwOnError) {
                throw new UnauthorizedHttpException('Bearer', 'Missing or invalid Authorization header');
            }
            return null;
        }

        $token = substr($authHeader, 7);
        $key = $secretKey ?? $this->_getSecretKey();

        try {
            return JWT::decode($token, new Key($key, 'HS256'));
        } catch (\Exception $e) {
            $this->_logger->warning('Failed to decode JWT token', [
                'error' => $e->getMessage()
            ]);

            if ($throwOnError) {
                throw new UnauthorizedHttpException('Bearer', 'Invalid JWT token: ' . $e->getMessage());
            }

            return null;
        }
    }

    /**
     * Extract portal and roles from a JWT token or request parameters (for testing).
     *
     * @param string|null $authHeader The Authorization header value
     * @param object|null $request    The request object (for fallback in test environment)
     * @param bool        $isTestEnv  Whether we're in a test environment
     * 
     * @return array An array with 'portal' and 'userRoles' keys
     * 
     * @throws UnauthorizedHttpException If authentication fails
     */
    public function extractAuthData(
        ?string $authHeader,
        ?object $request = null,
        bool $isTestEnv = false
    ): array {
        // Try to extract data from JWT token
        $tokenData = $this->extractAndDecodeToken(
            $authHeader,
            null,
            !$isTestEnv // Only throw in non-test environments
        );

        if ($tokenData) {
            // Validate required fields
            if (!isset($tokenData->portal)) {
                throw new UnauthorizedHttpException('Bearer', 'Missing portal in JWT token');
            }

            if (!isset($tokenData->userRoles) || !is_array($tokenData->userRoles) || empty($tokenData->userRoles)) {
                throw new UnauthorizedHttpException('Bearer', 'Missing userRoles in JWT token');
            }

            $this->_logger->info('Using JWT token for authentication', [
                'portal' => $tokenData->portal,
                'userRoles' => $tokenData->userRoles
            ]);

            return [
                'portal' => $tokenData->portal,
                'userRoles' => $tokenData->userRoles
            ];
        }

        // Fallback to query parameters for testing
        if ($isTestEnv && $request) {
            $portal = $request->query->get('portal');
            $userRoles = $request->query->get('userRoles', []);

            if (empty($portal)) {
                throw new UnauthorizedHttpException('Bearer', 'Missing portal parameter');
            }

            if (!is_array($userRoles)) {
                $userRoles = [$userRoles];
            }

            if (empty($userRoles)) {
                throw new UnauthorizedHttpException('Bearer', 'Missing userRoles parameter');
            }

            $this->_logger->info('Using query parameters for authentication (test mode)', [
                'portal' => $portal,
                'userRoles' => $userRoles
            ]);

            return [
                'portal' => $portal,
                'userRoles' => $userRoles
            ];
        }

        throw new UnauthorizedHttpException('Bearer', 'Authentication required');
    }

    /**
     * Get the secret key from configuration or use a default for tests.
     *
     * @return string The secret key
     */
    private function _getSecretKey(): string
    {
        // In a real app, this would come from secure configuration
        // For testing purposes, we use a fixed key
        try {
            if ($this->_parameterBag->has('app.jwt_secret')) {
                $secret = $this->_parameterBag->get('app.jwt_secret');
                if (!empty($secret)) {
                    return $secret;
                }
            }
        } catch (\Exception $e) {
            $this->_logger->warning('Failed to get JWT secret from parameters', [
                'error' => $e->getMessage()
            ]);
        }

        // Default fallback for tests
        return 'test_secret_key';
    }
}
