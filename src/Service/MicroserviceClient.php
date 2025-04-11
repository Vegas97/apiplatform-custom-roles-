<?php

/**
 * Microservice Client Service for API Platform.
 *
 * PHP version 8.4
 *
 * @category Service
 * @package  App\Service
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 * @version  GIT: <git_id>
 */

declare(strict_types=1);

namespace App\Service;

use App\Config\MicroserviceEntityMapping;
use App\Dto\EntityMappingDto;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Client for making requests to microservices.
 *
 * @category Service
 * @package  App\Service
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class MicroserviceClient
{
    /**
     * HTTP client for making requests.
     *
     * @var HttpClientInterface
     */
    private HttpClientInterface $httpClient;

    /**
     * Logger service.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Parameter bag for accessing configuration.
     *
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $parameterBag;

    /**
     * Constructor.
     *
     * @param HttpClientInterface   $httpClient   HTTP client service
     * @param LoggerInterface       $logger       Logger service
     * @param ParameterBagInterface $parameterBag Parameter bag for configuration
     */
    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Fetch data from a microservice.
     *
     * @param string $microservice The name of the microservice
     * @param string $endpoint     The API endpoint to call
     * @param string $context      The context level (context_ids, context_mini, etc.)
     * @param array  $params       Additional query parameters
     * @param string $method       HTTP method (GET, POST, etc.)
     * @param array  $body         Request body for POST/PUT requests
     * @param array  $headers      Additional headers
     * @param string|null $id      Optional ID for single item requests
     *
     * @return array The response data
     */
    public function fetch(
        string $microservice,
        string $endpoint,
        string $context = 'context_normal',
        array $params = [],
        string $method = 'GET',
        array $body = [],
        array $headers = [],
        ?string $id = null
    ): array {
        // Add context to query parameters
        $params['context'] = $context;

        // Get base URL for the microservice from configuration
        $baseUrl = $this->getBaseUrl($microservice);

        // Build the full URL - the endpoint already includes the path
        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        // Add ID to the URL if provided
        if ($id !== null) {
            $url .= '/' . $id;
        }

        // Prepare request options
        $options = [
            'headers' => array_merge(
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                $headers
            ),
            'query' => $params,
        ];

        // Add body for POST/PUT requests
        if (!empty($body) && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            $options['json'] = $body;
        }

        $this->logger->info('Making request to microservice', [
            'microservice' => $microservice,
            'url' => $url,
            'method' => $method,
            'context' => $context,
            'params' => $params
        ]);

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = $response->toArray();

                $this->logger->info('Successful response from microservice', [
                    'microservice' => $microservice,
                    'statusCode' => $statusCode
                ]);

                return $data;
            }

            $this->logger->error('Error response from microservice', [
                'microservice' => $microservice,
                'statusCode' => $statusCode,
                'content' => $response->getContent(false)
            ]);

            return [];
        } catch (ExceptionInterface $e) {
            $this->logger->error('Exception when calling microservice', [
                'microservice' => $microservice,
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Get the base URL for a microservice.
     *
     * This method gets the base URL from MicroserviceEntityMapping.
     * If not found, it throws an exception.
     *
     * @param string $microservice The name of the microservice
     *
     * @return string The base URL
     */
    private function getBaseUrl(string $microservice): string
    {
        // Get the base URL from MicroserviceEntityMapping
        $baseUrls = \App\Config\MicroserviceEntityMapping::getMicroserviceBaseUrls();
        if (isset($baseUrls[$microservice])) {
            return $baseUrls[$microservice];
        }

        // No base URL found, throw an exception
        $this->logger->error('No base URL configured for microservice', [
            'microservice' => $microservice
        ]);

        throw new RuntimeException(
            sprintf(
                'Missing base URL configuration for microservice "%s"',
                $microservice
            )
        );
    }

    /**
     * Fetch data from a microservice based on entity mapping.
     *
     * @param EntityMappingDto $entityMapping  Entity mapping information
     * @param array           $queryParameters Query parameters for the request
     * @param string|null     $id             Optional ID for single item requests
     *
     * @return array The fetched data
     */
    public function fetchEntityData(
        EntityMappingDto $entityMapping,
        array $queryParameters = [],
        ?string $id = null
    ): array {
        $microservice = $entityMapping->microservice;
        $entity = $entityMapping->entity;
        $endpoint = $entityMapping->endpoint;
        $context = $entityMapping->context;

        // Verify that the endpoint is defined
        if ($endpoint === null) {
            throw new RuntimeException(
                sprintf(
                    'Missing endpoint configuration for entity "%s" in microservice "%s"',
                    $entity,
                    $microservice
                )
            );
        }

        // Make the request using the standard fetch method
        return $this->fetch(
            $microservice,
            $endpoint,
            $context,
            $queryParameters,
            'GET',
            [],
            [],
            $id
        );
    }
}
