<?php
// api/tests/BooksTest.php

namespace App\Tests;

use App\Entity\Book;
use App\Factory\BookFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Symfony\Component\HttpClient\Exception\ClientException;

class BooksTest extends ApiTestCase
{
    // This trait provided by Foundry will take care of refreshing the database content to a known state before each test
    use ResetDatabase, Factories;

    public function testGetCollection(): void
    {
        // Create 100 books using our factory
        BookFactory::createMany(100);

        // The client implements Symfony HttpClient's `HttpClientInterface`, and the response `ResponseInterface`
        $response = static::createClient()->request('GET', '/books');

        $this->assertResponseIsSuccessful();
        // Asserts that the returned content type is JSON-LD (the default)
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        // Asserts that the returned JSON is a superset of this one
        $this->assertJsonContains([
            '@context' => '/contexts/Book',
            '@id' => '/books',
            '@type' => 'Collection',
            'totalItems' => 100,
            'view' => [
                '@id' => '/books?page=1',
                '@type' => 'PartialCollectionView',
                'first' => '/books?page=1',
                'last' => '/books?page=4',
                'next' => '/books?page=2',
            ],
        ]);

        // Because test fixtures are automatically loaded between each test, you can assert on them
        $this->assertCount(30, $response->toArray()['member']);

        // Asserts that the returned JSON is validated by the JSON Schema generated for this resource by API Platform
        // This generated JSON Schema is also used in the OpenAPI spec!
        $this->assertMatchesResourceCollectionJsonSchema(Book::class);
    }

    public function testCreateBook(): void
    {
        $response = static::createClient()->request('POST', '/books', [
            'json' => [
                'isbn' => '0099740915',
                'title' => 'The Handmaid\'s Tale',
                'description' => 'Brilliantly conceived and executed, this powerful evocation of twenty-first century America gives full rein to Margaret Atwood\'s devastating irony, wit and astute perception.',
                'author' => 'Margaret Atwood',
                'publicationDate' => '1985-07-31T00:00:00+00:00',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json'
            ]
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Book',
            '@type' => 'Book',
            'isbn' => '0099740915',
            'title' => 'The Handmaid\'s Tale',
            'description' => 'Brilliantly conceived and executed, this powerful evocation of twenty-first century America gives full rein to Margaret Atwood\'s devastating irony, wit and astute perception.',
            'author' => 'Margaret Atwood',
            'publicationDate' => '1985-07-31T00:00:00+00:00',
            'reviews' => [],
        ]);
        $this->assertMatchesRegularExpression('~^/books/\d+$~', $response->toArray()['@id']);
        $this->assertMatchesResourceItemJsonSchema(Book::class);
    }

    public function testCreateInvalidBook(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/books', [
            'json' => [
                'isbn' => 'invalid',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ]
        ]);

        $this->assertResponseStatusCodeSame(422);

        // Get the response content as an array
        $responseData = $response->toArray(false);

        // Check that the response contains the validation errors
        $this->assertArrayHasKey('violations', $responseData);
        $violations = $responseData['violations'];

        // Create a map of property paths to their violation messages
        $violationMap = [];
        foreach ($violations as $violation) {
            $violationMap[$violation['propertyPath']] = $violation['message'];
        }

        // Assert that all expected violations are present
        $this->assertArrayHasKey('isbn', $violationMap);
        $this->assertArrayHasKey('title', $violationMap);
        $this->assertArrayHasKey('description', $violationMap);
        $this->assertArrayHasKey('author', $violationMap);
        $this->assertArrayHasKey('publicationDate', $violationMap);

        $this->assertStringContainsString('valid ISBN', $violationMap['isbn']);
        $this->assertStringContainsString('should not be blank', $violationMap['title']);
        $this->assertStringContainsString('should not be blank', $violationMap['description']);
        $this->assertStringContainsString('should not be blank', $violationMap['author']);
        $this->assertStringContainsString('should not be null', $violationMap['publicationDate']);
    }

    public function testUpdateBook(): void
    {
        // Only create the book we need with a given ISBN
        BookFactory::createOne(['isbn' => '9781344037075']);

        $client = static::createClient();
        // findIriBy allows to retrieve the IRI of an item by searching for some of its properties.
        $iri = $this->findIriBy(Book::class, ['isbn' => '9781344037075']);

        // Use the PATCH method here to do a partial update
        $client->request('PATCH', $iri, [
            'json' => [
                'title' => 'updated title',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'isbn' => '9781344037075',
            'title' => 'updated title',
        ]);
    }

    public function testDeleteBook(): void
    {
        // Only create the book we need with a given ISBN
        BookFactory::createOne(['isbn' => '9781344037075']);

        $client = static::createClient();
        $iri = $this->findIriBy(Book::class, ['isbn' => '9781344037075']);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            // Through the container, you can access all your services from the tests, including the ORM, the mailer, remote API clients...
            static::getContainer()->get('doctrine')->getRepository(Book::class)->findOneBy(['isbn' => '9781344037075'])
        );
    }
}
