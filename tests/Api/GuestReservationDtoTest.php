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
    }

    /**
     * Test that the GuestReservationDto item endpoint returns fields based on portal and roles.
     *
     * @example command: APP_ENV=test bin/phpunit --filter testGetItemWithPortalAndRoles tests/Api/GuestReservationDtoTest.php
     * 
     * @return void
     */
    public function testGetItemWithPortalAndRoles(): void
    {
        // Debug the environment
        $this->assertEquals('test', $_SERVER['APP_ENV'], 'APP_ENV should be "test"');
        $this->assertEquals('SystemBFF', $_SERVER['APP_BFF_NAME'], 'APP_BFF_NAME should be "SystemBFF"');

        // Create a JWT token with roles and portal information
        $payload = [
            'sub' => '1',  // Subject (user ID)
            'roles' => ['ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_ACCESS'],
            'portal' => 'distributor',
            'iat' => time(),  // Issued at time
            'exp' => time() + 3600  // Expiration time (1 hour from now)
        ];

        $token = JWT::encode($payload, 'test_secret_key', 'HS256');

        $client = static::createClient();

        // Test with JWT token in Authorization header
        $client->request(
            'GET',
            '/api/guest_reservation_dtos/1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode(),
            'The GuestReservationDto item endpoint should return a 200 OK response'
        );

        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json; charset=utf-8',
            'The response content type should be application/ld+json'
        );

        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent, 'Response should be valid JSON');

        $jsonResponse = json_decode($responseContent, true);

        // Assert that fields accessible to distributor portal are present
        $this->assertArrayHasKey('id', $jsonResponse, 'Response should have id field');
        $this->assertArrayHasKey('reservationId', $jsonResponse, 'Response should have reservationId field');
        $this->assertArrayHasKey('name', $jsonResponse, 'Response should have name field');
        $this->assertArrayHasKey('nationality', $jsonResponse, 'Response should have nationality field');
        $this->assertArrayHasKey('checkInDate', $jsonResponse, 'Response should have checkInDate field');
        $this->assertArrayHasKey('checkOutDate', $jsonResponse, 'Response should have checkOutDate field');


        // Assert that email is not present (restricted from distributor portal)
        $this->assertArrayNotHasKey('email', $jsonResponse, 'Response should NOT have email field for distributor portal');
        $this->assertArrayNotHasKey('birthDate', $jsonResponse, 'Response should NOT have birthDate field for distributor portal');
        $this->assertArrayNotHasKey('roomNumber', $jsonResponse, 'Response should NOT have roomNumber field for distributor portal');
    }
}
