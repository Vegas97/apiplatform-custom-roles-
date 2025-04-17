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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service for handling JWT tokens.
 *
 * This service handles JWT token creation, validation, and extraction of authentication data.
 * It supports both production use with real JWT tokens and test environment fallbacks.
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
     * Get token from request object.
     *
     * Extracts the JWT token from the Authorization header of the request.
     *
     * @param Request|null $request The request object
     *
     * @return string|null The JWT token or null if not found
     */
    public function getTokenFromRequest(?Request $request): ?string
    {
        if (null === $request) {
            $this->_logger->warning('Request object is null, cannot extract token');
            return null;
        }

        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            return null;
        }

        return substr($authHeader, 7);
    }

    /**
     * Validate a JWT token and extract claims.
     *
     * @param string|null $token The JWT token to validate
     * 
     * @return array An array with 'portal' and 'userRoles' keys
     * 
     * @throws UnauthorizedHttpException If token validation fails
     */
    public function validateToken(?string $token): array
    {
        if (empty($token)) {
            $this->_logger->warning('Empty token provided to validateToken');
            throw new UnauthorizedHttpException('Bearer', 'Authentication token is required');
        }

        // Decode the JWT token
        try {
            $tokenData = JWT::decode($token, new Key($this->_getSecretKey(), 'HS256'));
        } catch (\Exception $e) {
            $this->_logger->warning('Failed to decode JWT token', [
                'error' => $e->getMessage()
            ]);
            throw new UnauthorizedHttpException('Bearer', 'Invalid JWT token: ' . $e->getMessage());
        }

        // Validate required fields
        if (!isset($tokenData->portal)) {
            throw new UnauthorizedHttpException('Bearer', 'Missing portal in JWT token');
        }

        // Log warning if userroles are missing but don't throw an exception
        if (!isset($tokenData->userRoles) || !is_array($tokenData->userRoles) || empty($tokenData->userRoles)) {
            throw new UnauthorizedHttpException('Bearer', 'Missing user roles in JWT token');
        }

        $this->_logger->info('JWT token validated successfully', [
            'portal' => $tokenData->portal
        ]);

        return [
            'portal' => $tokenData->portal,
            'userRoles' => $tokenData->userRoles
        ];
    }

    /**
     * Get test token for development and testing environments.
     * 
     * This method creates a token with test data that can be used in development
     * and testing environments without needing a real JWT token.
     *
     * @param string $portal    The portal to include in the token
     * @param array  $userRoles The user roles to include in the token
     * 
     * @return string A JWT token with the provided data
     */
    public function getTestToken(string $portal, array $userRoles = []): string
    {
        $payload = [
            'portal' => $portal,
            'userRoles' => $userRoles,
            'iat' => time(),
            'exp' => time() + 3600 // 1 hour expiration
        ];

        return $this->createToken($payload);
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
