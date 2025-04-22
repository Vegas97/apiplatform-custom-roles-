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
     * Get test cases for different portals
     *
     * This method provides test data for different portal contexts, allowing
     * tests to be run against specific portals or all portals.
     *
     * @param string|null $portal Portal to get test cases for ('workspace', 'selfcheckin', or 'all')
     *
     * @return array<string, array<string, mixed>> Array of test cases organized by portal
     */
    private function _getProviderCases($portal = null): array
    {
        $cases = [];

        // workspace
        $cases['workspace'] = [
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
            // 'item_endpoint_with_access' => [
            //     'method' => 'GET',
            //     'url' => '/api/guest_reservation_dtos/R101',
            //     'userRoles' => ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'],
            //     'portal' => 'workspace',
            //     'expectedStatusCode' => Response::HTTP_OK,
            //     'expectedTotalItems' => null,
            //     'isCollection' => false,
            //     'expectedFields' => ['reservationId', 'roomNumber'],
            //     'unexpectedFields' => []
            // ],
            // 'item_endpoint_without_access_role' => [
            //     'method' => 'GET',
            //     'url' => '/api/guest_reservation_dtos/G001',
            //     'userRoles' => ['ROLE_USER'],
            //     'portal' => 'workspace',
            //     'expectedStatusCode' => Response::HTTP_NOT_FOUND,
            //     'expectedTotalItems' => null,
            //     'isCollection' => false,
            //     'expectedFields' => [],
            //     'unexpectedFields' => []
            // ]
        ];

        // // admin
        // $cases['admin'] = [
        //     'item_endpoint_with_access' => [
        //         'method' => 'GET',
        //         'url' => '/api/guest_reservation_dtos/G001',
        //         'userRoles' => ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'],
        //         'portal' => 'admin',
        //         'expectedStatusCode' => Response::HTTP_OK,
        //         'expectedTotalItems' => null,
        //         'isCollection' => false,
        //         'expectedFields' => ['reservationId', 'roomNumber'],
        //         'unexpectedFields' => []
        //     ]
        // ];

        // distributor
        $cases['distributor'] = [
            'item_endpoint_with_access' => [
                'method' => 'GET',
                'url' => '/api/guest_reservation_dtos/G001',
                'userRoles' => ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'],
                'portal' => 'distributor',
                'expectedStatusCode' => Response::HTTP_OK,
                'expectedTotalItems' => null,
                'isCollection' => false,
                'expectedFields' => ['id', 'reservationId', 'name'],
                'unexpectedFields' => []
            ]
        ];

        // selfcheckin
        $cases['selfcheckin'] = [
            'item_endpoint_with_access' => [
                'method' => 'GET',
                'url' => '/api/guest_reservation_dtos/G001',
                'userRoles' => ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'],
                'portal' => 'selfcheckin',
                'expectedStatusCode' => Response::HTTP_OK,
                'expectedTotalItems' => null,
                'isCollection' => false,
                'expectedFields' => ['id', 'reservationId', 'name', 'roomNumber'],
                'unexpectedFields' => []
            ]
        ];

        switch ($portal) {
            case 'admin':
                return $cases['admin'];
            case 'workspace':
                return $cases['workspace'];
            case 'distributor':
                return $cases['distributor'];
            case 'selfcheckin':
                return $cases['selfcheckin'];
            default:
                return [
                    ...$cases['admin'],
                    ...$cases['workspace'],
                    ...$cases['distributor'],
                    ...$cases['selfcheckin']
                ];
        }
    }


    /**
     * Data provider for API test cases
     * 
     * Provides test cases for the testCase method in the parent class.
     * This implementation returns all test cases from all portals.
     *
     * Example of how to run the test case directly
     * 
     * @example command: APP_ENV=test bin/phpunit --filter testCase tests/Api/GuestReservationDtoTest.php
     * 
     * @return array<string, array<string, mixed>>
     */
    public function casesProvider(): array
    {
        // Get all test cases from all portals
        // This could be filtered by portal if needed // admin, workspace, distributor, selfcheckin
        return $this->_getProviderCases('workspace');
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
