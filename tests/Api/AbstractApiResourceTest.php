<?php

/**
 * Abstract test class for API Platform resources.
 *
 * @category Tests
 * @package  App\Tests\Api
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */

namespace App\Tests\Api;

use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * AbstractApiResourceTest class for testing API Platform resources.
 *
 * This abstract class provides common functionality for testing API resources,
 * including methods for testing GET collection, GET item, and other HTTP methods.
 *
 * @category Tests
 * @package  App\Tests\Api
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
abstract class AbstractApiResourceTest extends WebTestCase
{
    /**
     * The base URI for the API resource (e.g., '/api/user_dtos')
     *
     * @var string
     */
    protected string $resourceUri;

    /**
     * The resource name (e.g., 'UserDto')
     *
     * @var string
     */
    protected string $resourceName;

    /**
     * The role required to access this resource
     *
     * @var string
     */
    protected string $resourceRole;

    /**
     * Get a client with JWT authentication
     *
     * @param array  $userRoles User roles for JWT token
     * @param string $portal    Portal for JWT token
     * 
     * @return KernelBrowser
     */
    protected function getAuthenticatedClient(array $userRoles, string $portal): KernelBrowser
    {
        $client = static::createClient();

        // Create a JWT token with roles and portal information
        $payload = [
            'sub' => '1',  // Subject (user ID)
            'roles' => $userRoles,
            'portal' => $portal,
            'iat' => time(),  // Issued at time
            'exp' => time() + 3600  // Expiration time (1 hour from now)
        ];

        $token = JWT::encode($payload, 'test_secret_key', 'HS256');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $client;
    }

    /**
     * Test a GET collection endpoint
     *
     * @param array  $userRoles         User roles for JWT token
     * @param string $portal            Portal for JWT token
     * @param int    $expectedStatus    Expected HTTP status code
     * @param int    $expectedTotalItems Expected total items (null to skip check)
     * @param array  $expectedFields    Fields that should be present in each item
     * 
     * @return void
     */
    protected function testGetCollection(
        array $userRoles,
        string $portal,
        int $expectedStatus = Response::HTTP_OK,
        ?int $expectedTotalItems = null,
        array $expectedFields = []
    ): void {
        // Debug the environment
        $this->assertEquals('test', $_SERVER['APP_ENV'], 'APP_ENV should be "test"');
        $this->assertEquals('SystemBFF', $_SERVER['APP_BFF_NAME'], 'APP_BFF_NAME should be "SystemBFF"');

        // Create the client first
        $client = $this->getAuthenticatedClient($userRoles, $portal);
        
        // Get the logger from the client's container
        $logger = $client->getContainer()->get(LoggerInterface::class);
        $logger->info("Starting {$this->resourceName} collection test", [
            'environment' => $_SERVER['APP_ENV'],
            'bffName' => $_SERVER['APP_BFF_NAME'],
            'testMethod' => __METHOD__,
            'userRoles' => $userRoles,
            'portal' => $portal
        ]);

        // Make the request
        $client->request('GET', $this->resourceUri);

        // Assert the status code
        $this->assertEquals(
            $expectedStatus,
            $client->getResponse()->getStatusCode(),
            "The {$this->resourceName} collection endpoint should return a {$expectedStatus} response"
        );

        // If we're expecting a non-successful response, we're done
        if ($expectedStatus !== Response::HTTP_OK) {
            return;
        }

        // Assert the content type
        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json; charset=utf-8',
            'The response content type should be application/ld+json; charset=utf-8'
        );

        // Parse and validate the response
        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent, 'Response should be valid JSON');

        $jsonResponse = json_decode($responseContent, true);

        // Log the response data
        $logger->info("{$this->resourceName} collection test response received", [
            'statusCode' => $client->getResponse()->getStatusCode(),
            'responseType' => $client->getResponse()->headers->get('content-type'),
            'responseData' => $jsonResponse
        ]);

        // Assert common structure
        $this->assertArrayHasKey('@context', $jsonResponse, 'Response should have @context key');
        $this->assertArrayHasKey('@id', $jsonResponse, 'Response should have @id key');
        $this->assertArrayHasKey('@type', $jsonResponse, 'Response should have @type key');
        $this->assertArrayHasKey('member', $jsonResponse, 'Collection should have member key');

        // Assert expected values
        $this->assertEquals("/api/contexts/{$this->resourceName}", $jsonResponse['@context']);
        $this->assertEquals($this->resourceUri, $jsonResponse['@id']);
        $this->assertEquals('Collection', $jsonResponse['@type']);

        if ($expectedTotalItems !== null) {
            $this->assertArrayHasKey('totalItems', $jsonResponse, 'Collection should have totalItems key');
            $this->assertEquals($expectedTotalItems, $jsonResponse['totalItems']);
        }

        // Check that member array exists
        $this->assertIsArray($jsonResponse['member'], 'Collection member should be an array');
        
        // If there are members, check the fields
        if (!empty($jsonResponse['member'])) {
            $firstMember = $jsonResponse['member'][0];
            
            foreach ($expectedFields as $field) {
                $this->assertArrayHasKey($field, $firstMember, "Member should have {$field} field");
            }
        }
    }

    /**
     * Test a GET item endpoint
     *
     * @param string $itemId           ID of the item to retrieve
     * @param array  $userRoles        User roles for JWT token
     * @param string $portal           Portal for JWT token
     * @param int    $expectedStatus   Expected HTTP status code
     * @param array  $expectedFields   Fields that should be present in the response
     * @param array  $unexpectedFields Fields that should not be present in the response
     * 
     * @return void
     */
    protected function testGetItem(
        string $itemId,
        array $userRoles,
        string $portal,
        int $expectedStatus = Response::HTTP_OK,
        array $expectedFields = []
    ): void {
        // Debug the environment
        $this->assertEquals('test', $_SERVER['APP_ENV'], 'APP_ENV should be "test"');
        $this->assertEquals('SystemBFF', $_SERVER['APP_BFF_NAME'], 'APP_BFF_NAME should be "SystemBFF"');

        // Create the client first to avoid multiple kernel boots
        $client = $this->getAuthenticatedClient($userRoles, $portal);
        
        // Get the logger from the client's container
        $logger = $client->getContainer()->get(LoggerInterface::class);
        $logger->info("Starting {$this->resourceName} item test", [
            'environment' => $_SERVER['APP_ENV'],
            'bffName' => $_SERVER['APP_BFF_NAME'],
            'testMethod' => __METHOD__,
            'itemId' => $itemId,
            'userRoles' => $userRoles,
            'portal' => $portal
        ]);

        // Make the request
        $client->request('GET', "{$this->resourceUri}/{$itemId}");

        // Assert the status code
        $this->assertEquals(
            $expectedStatus,
            $client->getResponse()->getStatusCode(),
            "The {$this->resourceName} item endpoint should return a {$expectedStatus} response"
        );

        // If we're expecting a non-successful response, we're done
        if ($expectedStatus !== Response::HTTP_OK) {
            return;
        }

        // Assert the content type
        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json; charset=utf-8',
            'The response content type should be application/ld+json; charset=utf-8'
        );

        // Parse and validate the response
        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent, 'Response should be valid JSON');

        $jsonResponse = json_decode($responseContent, true);

        // Log the response data
        $logger->info("{$this->resourceName} item test response received", [
            'statusCode' => $client->getResponse()->getStatusCode(),
            'responseType' => $client->getResponse()->headers->get('content-type'),
            'responseData' => $jsonResponse
        ]);

        // Assert common structure
        $this->assertArrayHasKey('@context', $jsonResponse, 'Response should have @context key');
        $this->assertArrayHasKey('@id', $jsonResponse, 'Response should have @id key');
        $this->assertArrayHasKey('@type', $jsonResponse, 'Response should have @type key');

        // Assert expected values
        $this->assertEquals("/api/contexts/{$this->resourceName}", $jsonResponse['@context']);
        $this->assertEquals("{$this->resourceUri}/{$itemId}", $jsonResponse['@id']);
        $this->assertEquals($this->resourceName, $jsonResponse['@type']);

        // Check expected and unexpected fields
        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $jsonResponse, "Response should have {$field} field");
        }
    }

    /**
     * Data provider for API test cases
     *
     * This method should be implemented by child classes to provide test cases
     * for different endpoints, roles, portals, etc.
     *
     * @return array<array<string, mixed>>
     */
    abstract public function casesProvider(): array;

    /**
     * Test an API resource case using data from the provider
     *
     * @param string   $method           HTTP method
     * @param string   $url              API endpoint URL
     * @param array    $userRoles        User roles for JWT token
     * @param string   $portal           Portal for JWT token
     * @param int      $expectedStatus   Expected HTTP status code
     * @param int|null $expectedItems    Expected totalItems for collections
     * @param bool     $isCollection     Whether this is a collection endpoint
     * @param array    $expectedFields   Fields that should be present
     * @param array    $unexpectedFields Fields that should not be present
     * 
     * @dataProvider casesProvider
     * @test
     * @return void
     */
    public function testCase(
        string $method,
        string $url,
        array $userRoles,
        string $portal,
        int $expectedStatus,
        ?int $expectedItems,
        bool $isCollection,
        array $expectedFields = [],
        array $unexpectedFields = []
    ): void {
        // Debug the environment
        $this->assertEquals('test', $_SERVER['APP_ENV'], 'APP_ENV should be "test"');
        $this->assertEquals('SystemBFF', $_SERVER['APP_BFF_NAME'], 'APP_BFF_NAME should be "SystemBFF"');

        // Create the client first
        $client = $this->getAuthenticatedClient($userRoles, $portal);
        
        // Get the logger from the client's container
        $logger = $client->getContainer()->get(LoggerInterface::class);
        $logger->info("Starting {$this->resourceName} test", [
            'environment' => $_SERVER['APP_ENV'],
            'bffName' => $_SERVER['APP_BFF_NAME'],
            'testMethod' => __METHOD__,
            'endpoint' => $url,
            'method' => $method,
            'userRoles' => $userRoles,
            'portal' => $portal
        ]);

        // Make the request
        $client->request($method, $url);

        // Assert the status code
        $this->assertEquals(
            $expectedStatus,
            $client->getResponse()->getStatusCode(),
            "The endpoint {$url} should return a {$expectedStatus} response"
        );

        // If we're expecting a non-successful response, we're done
        if ($expectedStatus !== Response::HTTP_OK) {
            return;
        }

        // Assert the content type
        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json; charset=utf-8',
            'The response content type should be application/ld+json; charset=utf-8'
        );

        // Parse and validate the response
        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent, 'Response should be valid JSON');

        $jsonResponse = json_decode($responseContent, true);

        // Log the response data
        $logger->info("{$this->resourceName} test response received", [
            'statusCode' => $client->getResponse()->getStatusCode(),
            'responseType' => $client->getResponse()->headers->get('content-type'),
            'responseData' => $jsonResponse
        ]);

        // Assert common structure
        $this->assertArrayHasKey('@context', $jsonResponse, 'Response should have @context key');
        $this->assertArrayHasKey('@id', $jsonResponse, 'Response should have @id key');
        $this->assertArrayHasKey('@type', $jsonResponse, 'Response should have @type key');

        // Collection-specific assertions
        if ($isCollection) {
            $this->assertArrayHasKey('member', $jsonResponse, 'Collection should have member key');
            
            if ($expectedItems !== null) {
                $this->assertArrayHasKey('totalItems', $jsonResponse, 'Collection should have totalItems key');
                $this->assertEquals($expectedItems, $jsonResponse['totalItems']);
            }

            // Check that member array exists
            $this->assertIsArray($jsonResponse['member'], 'Collection member should be an array');
            
            // If there are members, check the first one
            if (!empty($jsonResponse['member'])) {
                $firstMember = $jsonResponse['member'][0];
                foreach ($expectedFields as $field) {
                    $this->assertArrayHasKey($field, $firstMember, "Member should have {$field} field");
                }
                

            }
        }
        // Item-specific assertions
        else {
            foreach ($expectedFields as $field) {
                $this->assertArrayHasKey($field, $jsonResponse, "Response should have {$field} field");
            }
            

        }
    }
}
