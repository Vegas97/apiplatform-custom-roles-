<?php

/**
 * Tests for the GuestReservationDto API endpoints.
 *
 * PHP version 8.4
 *
 * @category Tests
 * @package  App\Tests\Api
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @version  GIT: <git_id>
 * @link     https://apiplatform.com
 */

namespace App\Tests\Api;

use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use App\Tests\Api\AbstractApiResourceTest;
use Symfony\Component\HttpFoundation\Response;

/**
 * GuestReservationDtoTest class for testing the GuestReservationDto API endpoints.
 *
 * Test Commands:
 * 
 * 1. Run all tests in this class (100% coverage of all test methods):
 *    APP_ENV=test bin/phpunit --filter GuestReservationDtoTest tests/Api/GuestReservationDtoTest.php
 *
 * 2. Run data-provider based tests only (tests multiple scenarios from endpointProvider):
 *    APP_ENV=test bin/phpunit --filter testEndpoint tests/Api/GuestReservationDtoTest.php
 *
 * 3. Run collection endpoint test only (tests GET on collection endpoint):
 *    APP_ENV=test bin/phpunit --filter testCollectionEndpoint tests/Api/GuestReservationDtoTest.php
 *
 * 4. Run item endpoint test only (tests GET on specific item endpoint):
 *    APP_ENV=test bin/phpunit --filter testItemEndpoint tests/Api/GuestReservationDtoTest.php
 *
 * Adding Custom Tests:
 * To add a new test case, either:
 * 1. Add a new entry to the endpointProvider() method with your test parameters
 * 2. Create a new test method with a descriptive name prefixed with 'test'
 * 
 * Example of adding a custom test method:
 * 
 * <pre>
 * // Test custom scenario
 * //
 * // @test
 * // @return void
 * public function testCustomScenario(): void
 * {
 *     // Your test implementation
 * }
 * </pre>
 *
 * @category Tests
 * @package  App\Tests\Api
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class GuestReservationDtoTest extends AbstractApiResourceTest
{
    /**
     * Set up the test
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resourceUri = '/api/guest_reservation_dtos';
        $this->resourceName = 'GuestReservationDto';
        $this->resourceRole = 'ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS';
    }



    /**
     * Data provider for API endpoint tests
     *
     * @return array<array<string, mixed>>
     */
    public function endpointProvider(): array
    {
        return [
            'collection_endpoint_with_access' => [
                'method' => 'GET',
                'url' => '/api/guest_reservation_dtos',
                'userRoles' => ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'],
                'portal' => 'workspace',
                'expectedStatusCode' => Response::HTTP_OK,
                'expectedTotalItems' => 3,
                'isCollection' => true,
                'expectedFields' => ['id', 'reservationId'],
                'unexpectedFields' => []
            ],
            'item_endpoint_with_access' => [
                'method' => 'GET',
                'url' => '/api/guest_reservation_dtos/G001',
                'userRoles' => ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'],
                'portal' => 'workspace',
                'expectedStatusCode' => Response::HTTP_OK,
                'expectedTotalItems' => null,
                'isCollection' => false,
                'expectedFields' => ['id', 'reservationId'],
                'unexpectedFields' => []
            ],
            'item_endpoint_without_access_role' => [
                'method' => 'GET',
                'url' => '/api/guest_reservation_dtos/G001',
                'userRoles' => ['ROLE_USER'],
                'portal' => 'workspace',
                'expectedStatusCode' => Response::HTTP_NOT_FOUND,
                'expectedTotalItems' => null,
                'isCollection' => false,
                'expectedFields' => [],
                'unexpectedFields' => []
            ]
        ];
    }

    /**
     * Test API endpoints using the data provider
     *
     * @param string   $method             HTTP method
     * @param string   $url                API endpoint URL
     * @param array    $userRoles          User roles for JWT token
     * @param string   $portal             Portal for JWT token
     * @param int      $expectedStatusCode Expected HTTP status code
     * @param int|null $expectedTotalItems Expected totalItems for collections
     * @param bool     $isCollection       Whether this is a collection endpoint
     * @param array    $expectedFields     Fields that should be present in the response
     * @param array    $unexpectedFields   Fields that should not be present in the response
     *
     * @return void
     * 
     * @dataProvider endpointProvider
     * @test
     * @example      command: APP_ENV=test bin/phpunit --filter testEndpoint tests/Api/GuestReservationDtoTest.php
     */
    public function testEndpoint(
        string $method,
        string $url,
        array $userRoles,
        string $portal,
        int $expectedStatusCode,
        ?int $expectedTotalItems,
        bool $isCollection,
        array $expectedFields = [],
        array $unexpectedFields = []
    ): void {
        // Debug the environment
        $this->assertEquals('test', $_SERVER['APP_ENV'], 'APP_ENV should be "test"');
        $this->assertEquals('SystemBFF', $_SERVER['APP_BFF_NAME'], 'APP_BFF_NAME should be "SystemBFF"');
        $this->assertEquals(true, $_SERVER['APP_USE_MOCK_DATA'], 'APP_USE_MOCK_DATA should be true');

        // Create a client
        $client = static::createClient();

        // Get the logger
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->info('Starting GuestReservationDto test', [
            'environment' => $_SERVER['APP_ENV'],
            'bffName' => $_SERVER['APP_BFF_NAME'],
            'useMockData' => $_SERVER['APP_USE_MOCK_DATA'],
            'testMethod' => __METHOD__,
            'endpoint' => $url,
            'userRoles' => $userRoles,
            'portal' => $portal
        ]);

        // Create a JWT token with roles and portal information
        $payload = [
            'sub' => '1',  // Subject (user ID)
            'roles' => $userRoles,
            'portal' => $portal,
            'iat' => time(),  // Issued at time
            'exp' => time() + 3600  // Expiration time (1 hour from now)
        ];

        $token = JWT::encode($payload, 'test_secret_key', 'HS256');

        // Make the request with JWT token in Authorization header
        $client->request(
            $method,
            $url,
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        // Assert the status code
        $this->assertEquals(
            $expectedStatusCode,
            $client->getResponse()->getStatusCode(),
            sprintf('The endpoint %s should return a %d response', $url, $expectedStatusCode)
        );

        // If we're expecting a non-successful response, we're done
        if ($expectedStatusCode !== Response::HTTP_OK) {
            return;
        }

        // For successful responses only, assert the content type
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
        $logger->info('GuestReservationDto test response received', [
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
            $this->assertArrayHasKey('totalItems', $jsonResponse, 'Collection should have totalItems key');
            $this->assertArrayHasKey('member', $jsonResponse, 'Collection should have member key');

            if ($expectedTotalItems !== null) {
                $this->assertEquals($expectedTotalItems, $jsonResponse['totalItems']);
            }

            // Check that member array exists and has items
            $this->assertNotEmpty($jsonResponse['member'], 'Collection should have members');

            // Assert the structure of each member item in the collection
            foreach ($jsonResponse['member'] as $member) {
                $this->assertArrayHasKey('@id', $member);
                $this->assertArrayHasKey('@type', $member);
                $this->assertArrayHasKey('id', $member);
                $this->assertArrayHasKey('reservationId', $member);
                $this->assertCount(4, $member);
            }
        }
        // Item-specific assertions
        else {
            $this->assertArrayHasKey('id', $jsonResponse, 'Item should have id field');
            $this->assertArrayHasKey('reservationId', $jsonResponse, 'Item should have reservationId field');
            
            // Check expected fields
            foreach ($expectedFields as $field) {
                $this->assertArrayHasKey($field, $jsonResponse, "Response should have {$field} field");
            }
            
            // Check unexpected fields are not present
            foreach ($unexpectedFields as $field) {
                $this->assertArrayNotHasKey($field, $jsonResponse, "Response should not have {$field} field");
            }
        }
    }



    /**
     * Test collection endpoint using direct method
     *
     * @test
     * @example command: APP_ENV=test bin/phpunit --filter testCollectionEndpoint tests/Api/GuestReservationDtoTest.php
     *
     * @return void
     */
    public function testCollectionEndpoint(): void
    {
        $this->testGetCollection(
            ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'],
            'workspace',
            Response::HTTP_OK,
            3,
            ['id', 'reservationId']
        );
    }

    /**
     * Test item endpoint with workspace portal
     *
     * @test
     * @example command: APP_ENV=test bin/phpunit --filter testItemEndpoint tests/Api/GuestReservationDtoTest.php
     *
     * @return void
     */
    public function testItemEndpoint(): void
    {
        $this->testGetItem(
            'G001',
            ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'],
            'workspace',
            Response::HTTP_OK,
            ['id', 'reservationId']
        );
    }
}
