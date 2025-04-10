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
}
