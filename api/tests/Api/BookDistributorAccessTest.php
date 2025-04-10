<?php
/**
 * Book Distributor Access Test.
 *
 * PHP version 8.4
 *
 * @category Tests\Api
 * @package  App\Tests\Api
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Book;
use App\Factory\BookFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Test role-based access control for Book collection with distributor role.
 *
 * @category Tests\Api
 * @package  App\Tests\Api
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */
class BookDistributorAccessTest extends ApiTestCase
{
    use ResetDatabase;
    use Factories;

    /**
     * Test GET /books with ROLE_BOOK_ACCESS from distributor portal.
     *
     * @return void
     */
    public function testGetCollectionWithDistributorAccess(): void
    {

        dump('here');
        dd('also here');
        // Create 10 books using our factory
        BookFactory::createMany(
            10,
            [
                'isbn' => '9780545010221',
                'title' => 'Test Book',
                'description' => 'A test book for role-based access control',
                'author' => 'Test Author',
                'publicationDate' => new \DateTimeImmutable('2020-01-01'),
            ]
        );

        // Create a client with distributor role and portal headers
        $client = static::createClient();

        // Make the request with the distributor role and portal
        $client->request(
            'GET',
            '/books',
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_X-USER-ROLES' => 'ROLE_BOOK_ACCESS',
                'HTTP_X-PORTAL' => 'distributor',
            ]
        );

        // Assert successful response
        $this->assertResponseIsSuccessful();

        // Assert content type is JSON-LD
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        // Get the response content
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        // Log the response for debugging
        echo "\n\nResponse for roles [ROLE_BOOK_ACCESS] in portal 'distributor':\n";
        echo json_encode($content, JSON_PRETTY_PRINT) . "\n";

        // Assert that the response has the expected structure
        $this->assertJsonContains(
            [
                '@context' => '/contexts/Book',
                '@id' => '/books',
                '@type' => 'Collection',
            ]
        );

        // Assert that each book in the collection has the allowed fields
        // and doesn't have the restricted fields for distributor portal
        foreach ($content['hydra:member'] as $book) {
            // Fields that should be present
            $this->assertArrayHasKey('id', $book);
            $this->assertArrayHasKey('isbn', $book);
            $this->assertArrayHasKey('title', $book);
            $this->assertArrayHasKey('description', $book);

            // Fields that should NOT be present for distributor
            $this->assertArrayNotHasKey('author', $book);
            $this->assertArrayNotHasKey('publicationDate', $book);
            $this->assertArrayNotHasKey('reviews', $book);
        }
    }
}
