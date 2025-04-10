<?php

/**
 * Tests for the UserDto API endpoints.
 *
 * @category Tests
 * @package  App\Tests\Api
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */

namespace App\Tests\Api;

use Firebase\JWT\JWT;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * UserDtoTest class for testing the UserDto API endpoints.
 *
 * @category Tests
 * @package  App\Tests\Api
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class UserDtoTest extends WebTestCase
{
    /**
     * Test that the UserDto collection endpoint returns a 200 response.
     *
     * @example command: APP_ENV=test bin/phpunit --filter testGetCollection tests/Api/UserDtoTest.php
     * 
     * @return void
     */
    public function testGetCollection(): void
    {

        // Debug the environment
        $this->assertEquals('test', $_SERVER['APP_ENV'], 'APP_ENV should be "test"');

        $client = static::createClient();
        $client->request('GET', '/api/user_dtos');

        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode(),
            'The UserDto collection endpoint should return a 200 OK response'
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
     * Test that the UserDto item endpoint returns fields based on portal and roles.
     *
     * @example command: APP_ENV=test bin/phpunit --filter testGetItemWithPortalAndRoles tests/Api/UserDtoTest.php
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
            'roles' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS'],
            'portal' => 'distributor',
            'iat' => time(),  // Issued at time
            'exp' => time() + 3600  // Expiration time (1 hour from now)
        ];

        $token = JWT::encode($payload, 'test_secret_key', 'HS256');

        $client = static::createClient();

        // Test with JWT token in Authorization header
        $client->request(
            'GET',
            '/api/user_dtos/1',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertEquals(
            Response::HTTP_OK,
            $client->getResponse()->getStatusCode(),
            'The UserDto item endpoint should return a 200 OK response'
        );

        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json; charset=utf-8',
            'The response content type should be application/ld+json'
        );

        $responseContent = $client->getResponse()->getContent();
        $this->assertJson($responseContent, 'Response should be valid JSON');

        $jsonResponse = json_decode($responseContent, true);

        // Assert that id and username are present
        $this->assertArrayHasKey('id', $jsonResponse, 'Response should have id field');
        $this->assertArrayHasKey('username', $jsonResponse, 'Response should have username field');

        // Assert that email and birthDate are present but empty
        $this->assertArrayNotHasKey('email', $jsonResponse, 'Response should NOT have email field for distributor portal');
        $this->assertArrayNotHasKey('birthDate', $jsonResponse, 'Response should NOT have birthDate field for distributor portal');
    }
}
