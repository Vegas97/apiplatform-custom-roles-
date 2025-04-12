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
 * @link     https://apiplatform.com
 * @version  GIT: <git_id>
 */

namespace App\Tests\Api;

use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * GuestReservationDtoTest class for testing the GuestReservationDto API endpoints.
 *
 * @category Tests
 * @package  App\Tests\Api
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class GuestReservationDtoTest extends WebTestCase
{
    /**
     * Test that the GuestReservationDto collection endpoint returns a 200 response.
     *
     * @example command: APP_ENV=test bin/phpunit --filter testGetCollection tests/Api/GuestReservationDtoTest.php
     * 
     * @return void
     */
    public function testGetCollection(): void
    {
        // Debug the environment
        $this->assertEquals('test', $_SERVER['APP_ENV'], 'APP_ENV should be "test"');

        $client = static::createClient();

        // Now that the client is created, we can safely get the logger
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->info('Starting GuestReservationDto collection test', [
            'environment' => $_SERVER['APP_ENV'],
            'testMethod' => __METHOD__
        ]);
        $client->request('GET', '/api/guest_reservation_dtos');

        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode(),
            'The GuestReservationDto collection endpoint should return a 200 OK response'
        );

        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json; charset=utf-8',
            'The response content type should be application/ld+json'
        );

        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent, 'Response should be valid JSON');

        $jsonResponse = json_decode($responseContent, true);
        $this->assertArrayHasKey('@context', $jsonResponse, 'Response should have @context key');
        $this->assertArrayHasKey('member', $jsonResponse, 'Response should have member key');

        // Log the test results using the same logger instance
        $logger->info('GuestReservationDto collection test completed', [
            'statusCode' => $client->getResponse()->getStatusCode(),
            'responseType' => $client->getResponse()->headers->get('content-type'),
            'memberCount' => count($jsonResponse['member'] ?? [])
        ]);
    }

    /**
     * Test that the GuestReservationDto item endpoint returns fields based on portal and roles.
     *
     * @example command: APP_ENV=test bin/phpunit --filter testGetItemWithPortalAndRoles tests/Api/GuestReservationDtoTest.php
     * 
     * @return void
     */
    public function testGetItemsWithPortalAndRoles(): void
    {
        // todo: 1 fix if portal is not something valid, ruturn 404 not found
        // todo: 2 fix merge logic (so if you have more then 1 entityMapping )
        // todo: 3 add new context context_all_ids

        $userRoles = ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'];
        $portal = 'workspace'; // distributor, workspace, admin, selfcheckin

        // Debug the environment
        $this->assertEquals('test', $_SERVER['APP_ENV'], 'APP_ENV should be "test"');
        $this->assertEquals('SystemBFF', $_SERVER['APP_BFF_NAME'], 'APP_BFF_NAME should be "SystemBFF"');
        $this->assertEquals(true, $_SERVER['APP_USE_MOCK_DATA'], 'APP_USE_MOCK_DATA should be true');

        // Debug the environment variable
        dump('------------------------------');
        dump($_SERVER['APP_ENV']);
        dump($_SERVER['APP_BFF_NAME']);
        dump($_SERVER['APP_USE_MOCK_DATA']);
        dump($userRoles);
        dump($portal);
        dump('------------------------------');

        // Create a client first before accessing the container
        $client = static::createClient();

        // Now that the client is created, we can safely get the logger
        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->info('Starting GuestReservationDto item test', [
            'environment' => $_SERVER['APP_ENV'],
            'bffName' => $_SERVER['APP_BFF_NAME'],
            'useMockData' => $_SERVER['APP_USE_MOCK_DATA'],
            'testMethod' => __METHOD__
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

        // Test with JWT token in Authorization header - using collection endpoint
        $client->request(
            'GET',
            '/api/guest_reservation_dtos',  // Use collection endpoint
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode(),
            'The GuestReservationDto collection endpoint should return a 200 OK response'
        );

        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json; charset=utf-8',
            'The response content type should be application/ld+json'
        );

        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent, 'Response should be valid JSON');

        $jsonResponse = json_decode($responseContent, true);

        // Log the response data using the same logger instance
        $logger->info('GuestReservationDto item test response received', [
            'statusCode' => $client->getResponse()->getStatusCode(),
            'responseType' => $client->getResponse()->headers->get('content-type'),
            'responseData' => $jsonResponse
        ]);

        // Assert the structure of the response
        $this->assertArrayHasKey('@context', $jsonResponse, 'Response should have @context key');
        $this->assertArrayHasKey('@id', $jsonResponse, 'Response should have @id key');
        $this->assertArrayHasKey('@type', $jsonResponse, 'Response should have @type key');
        $this->assertArrayHasKey('totalItems', $jsonResponse, 'Response should have totalItems key');
        $this->assertArrayHasKey('member', $jsonResponse, 'Response should have member key');

        // Assert the values of the response
        $this->assertEquals('/api/contexts/GuestReservationDto', $jsonResponse['@context'], '@context should be /api/contexts/GuestReservationDto');
        $this->assertEquals('/api/guest_reservation_dtos', $jsonResponse['@id'], '@id should be /api/guest_reservation_dtos');
        $this->assertEquals('Collection', $jsonResponse['@type'], '@type should be Collection');
        $this->assertEquals(3, $jsonResponse['totalItems'], 'totalItems should be 3');
        $this->assertCount(3, $jsonResponse['member'], 'member should have 3 items');

        // Assert the structure of each member item in the collection
        foreach ($jsonResponse['member'] as $member) {
            $this->assertArrayHasKey('@id', $member, 'Member should have @id key');
            $this->assertArrayHasKey('@type', $member, 'Member should have @type key');
            $this->assertArrayHasKey('id', $member, 'Member should have id key');
            $this->assertArrayHasKey('reservationId', $member, 'Member should have reservationId key');

            // Assert that each member has exactly 4 fields
            $this->assertCount(4, $member, 'Each member should have exactly 4 fields');

            // Assert that the @type is GuestReservationDto
            $this->assertEquals('GuestReservationDto', $member['@type'], 'Member @type should be GuestReservationDto');

            // Assert that email is not present (restricted fields)
            $this->assertArrayNotHasKey('email', $member, 'Member should NOT have email field');
            $this->assertArrayNotHasKey('birthDate', $member, 'Member should NOT have birthDate field');
            $this->assertArrayNotHasKey('roomNumber', $member, 'Member should NOT have roomNumber field');
            $this->assertArrayNotHasKey('name', $member, 'Member should NOT have name field');
            $this->assertArrayNotHasKey('nationality', $member, 'Member should NOT have nationality field');
            $this->assertArrayNotHasKey('checkInDate', $member, 'Member should NOT have checkInDate field');
            $this->assertArrayNotHasKey('checkOutDate', $member, 'Member should NOT have checkOutDate field');
        }

        // Assert specific values for each member
        $this->assertEquals('/api/guest_reservation_dtos/G001', $jsonResponse['member'][0]['@id']);
        $this->assertEquals('G001', $jsonResponse['member'][0]['id']);
        $this->assertEquals('R101', $jsonResponse['member'][0]['reservationId']);

        $this->assertEquals('/api/guest_reservation_dtos/G002', $jsonResponse['member'][1]['@id']);
        $this->assertEquals('G002', $jsonResponse['member'][1]['id']);
        $this->assertEquals('R102', $jsonResponse['member'][1]['reservationId']);

        $this->assertEquals('/api/guest_reservation_dtos/G003', $jsonResponse['member'][2]['@id']);
        $this->assertEquals('G003', $jsonResponse['member'][2]['id']);
        $this->assertEquals('R103', $jsonResponse['member'][2]['reservationId']);

        // Log the test completion using the same logger instance
        $logger->info('GuestReservationDto item test completed', [
            'portal' => 'distributor',
            'accessibleFields' => ['id', 'reservationId', 'name'],
            'restrictedFields' => ['email', 'birthDate', 'roomNumber', 'nationality', 'checkInDate', 'checkOutDate']
        ]);
    }
}
