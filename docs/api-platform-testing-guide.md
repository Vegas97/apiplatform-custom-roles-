# API Platform Testing Guide

This technical guide provides a comprehensive overview of testing strategies and tools for API Platform applications built with Symfony. It covers everything from unit testing to functional testing to end-to-end testing, with practical examples and best practices.

> **Important Note**: All commands in this guide should be run from the API folder of your API Platform project (typically the `/api` directory in a standard API Platform installation).

## Table of Contents

- [Testing Stack Overview](#testing-stack-overview)
- [Setting Up the Testing Environment](#setting-up-the-testing-environment)
- [Data Fixtures](#data-fixtures)
- [Functional Testing](#functional-testing)
- [Unit Testing](#unit-testing)
- [Continuous Integration](#continuous-integration)
- [Additional Testing Tools](#additional-testing-tools)
- [End-to-End Testing](#end-to-end-testing)
- [Best Practices](#best-practices)

## Testing Stack Overview

API Platform provides a robust testing framework built on top of PHPUnit and Symfony's testing components. The core testing stack includes:

- **PHPUnit**: The primary testing framework for both unit and functional tests
- **Symfony HttpClient**: Used to make HTTP requests to your API endpoints
- **DoctrineFixturesBundle**: For loading test data into your database
- **Foundry**: An expressive fixture generator that simplifies test data creation
- **DAMADoctrineTestBundle**: Manages database state between tests

This stack allows for comprehensive testing of your API at multiple levels, ensuring both correctness and performance.

## Setting Up the Testing Environment

### Required Dependencies

To set up a proper testing environment for API Platform, you need to install the following packages:

```bash
# Install the Symfony test pack and HTTP client
composer require --dev symfony/test-pack symfony/http-client

# Install DAMADoctrineTestBundle for database management
composer require --dev dama/doctrine-test-bundle

# Optional: Install JSON Schema for schema validation
composer require --dev justinrainbow/json-schema
```

### Configure PHPUnit

The DAMADoctrineTestBundle needs to be activated in your `phpunit.xml.dist` file:

```xml
<!-- phpunit.xml.dist -->
<phpunit>
    <!-- ... -->
    <extensions>
        <extension class="DAMA\\DoctrineTestBundle\\PHPUnit\\PHPUnitExtension"/>
    </extensions>
</phpunit>
```

This extension will automatically wrap each test in a transaction that is rolled back after the test completes, ensuring test isolation.

## Data Fixtures

### Using Foundry for Test Data

[Foundry](https://github.com/zenstruck/foundry) provides an elegant way to create test data. First, create a factory for each entity:

```php
// src/Factory/BookFactory.php
namespace App\Factory;

use App\Entity\Book;
use Zenstruck\Foundry\ModelFactory;

final class BookFactory extends ModelFactory
{
    protected function getDefaults(): array
    {
        return [
            'isbn' => self::faker()->isbn13(),
            'title' => self::faker()->sentence(3),
            'description' => self::faker()->paragraph(),
            'author' => self::faker()->name(),
            'publicationDate' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-30 years', 'now')
            ),
        ];
    }

    protected static function getClass(): string
    {
        return Book::class;
    }
}
```

Then use these factories in your tests to create test data:

```php
// Create a single book
$book = BookFactory::createOne(['title' => 'Custom Title']);

// Create multiple books
$books = BookFactory::createMany(10);
```

## Functional Testing

Functional tests verify that your API endpoints work correctly by making actual HTTP requests and asserting on the responses.

### Example: Testing CRUD Operations

Here's a comprehensive example of testing CRUD operations on a Book resource:

```php
<?php
// tests/Api/BooksTest.php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Book;
use App\Factory\BookFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BooksTest extends ApiTestCase
{
    // Reset the database before each test
    use ResetDatabase, Factories;

    public function testGetCollection(): void
    {
        // Create test data
        BookFactory::createMany(100);

        // Make a request to the API
        $response = static::createClient()->request('GET', '/books');

        // Assert on the response
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        
        // Assert on the response structure
        $this->assertJsonContains([
            '@context' => '/contexts/Book',
            '@id' => '/books',
            '@type' => 'Collection',
            'totalItems' => 100,
        ]);
        
        // Assert that the response matches the JSON Schema
        $this->assertMatchesResourceCollectionJsonSchema(Book::class);
    }

    public function testCreateBook(): void
    {
        $response = static::createClient()->request('POST', '/books', ['json' => [
            'isbn' => '9780451524935',
            'title' => '1984',
            'description' => 'A dystopian novel by George Orwell.',
            'author' => 'George Orwell',
            'publicationDate' => '1949-06-08T00:00:00+00:00',
        ]]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        
        $this->assertJsonContains([
            '@context' => '/contexts/Book',
            '@type' => 'Book',
            'isbn' => '9780451524935',
            'title' => '1984',
            'author' => 'George Orwell',
        ]);
        
        $this->assertMatchesResourceItemJsonSchema(Book::class);
    }

    public function testUpdateBook(): void
    {
        // Create a book with a specific ISBN
        BookFactory::createOne(['isbn' => '9780451524935']);

        $client = static::createClient();
        
        // Find the IRI of the book
        $iri = $this->findIriBy(Book::class, ['isbn' => '9780451524935']);

        // Update the book using PATCH
        $client->request('PATCH', $iri, [
            'json' => [
                'title' => 'Updated Title',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'title' => 'Updated Title',
        ]);
    }

    public function testDeleteBook(): void
    {
        // Create a book with a specific ISBN
        BookFactory::createOne(['isbn' => '9780451524935']);

        $client = static::createClient();
        
        // Find the IRI of the book
        $iri = $this->findIriBy(Book::class, ['isbn' => '9780451524935']);

        // Delete the book
        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        
        // Verify the book was deleted
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(Book::class)->findOneBy(['isbn' => '9780451524935'])
        );
    }

    public function testInvalidInput(): void
    {
        $response = static::createClient()->request('POST', '/books', ['json' => [
            'isbn' => 'invalid-isbn',
        ]]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        
        $this->assertJsonContains([
            '@context' => '/contexts/ConstraintViolationList',
            '@type' => 'ConstraintViolationList',
        ]);
    }
}
```

### Key Testing Assertions

API Platform provides several specialized assertions for testing API responses:

- `assertResponseIsSuccessful()`: Asserts that the response status code is in the 2xx range
- `assertResponseStatusCodeSame(int $expectedCode)`: Asserts that the response status code matches the expected code
- `assertResponseHeaderSame(string $headerName, string $expectedValue)`: Asserts that a response header matches the expected value
- `assertJsonContains(array $subset)`: Asserts that the response JSON contains the given subset
- `assertMatchesResourceCollectionJsonSchema(string $resourceClass)`: Asserts that the response matches the JSON Schema for a collection of resources
- `assertMatchesResourceItemJsonSchema(string $resourceClass)`: Asserts that the response matches the JSON Schema for a single resource item

### Testing Authentication and Authorization

To test endpoints that require authentication:

```php
public function testAccessProtectedResource(): void
{
    // Create a user
    $user = UserFactory::createOne(['email' => 'user@example.com', 'password' => 'password']);
    
    // Authenticate
    $token = $this->getToken('user@example.com', 'password');
    
    // Access a protected resource
    $client = static::createClient();
    $client->request('GET', '/protected-resource', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
        ],
    ]);
    
    $this->assertResponseIsSuccessful();
}

private function getToken(string $username, string $password): string
{
    $response = static::createClient()->request('POST', '/authentication_token', [
        'json' => [
            'email' => $username,
            'password' => $password,
        ],
    ]);
    
    return $response->toArray()['token'];
}
```

## Unit Testing

While functional tests verify the API as a whole, unit tests focus on testing individual components in isolation.

### Testing Custom Data Providers

```php
namespace App\Tests\DataProvider;

use App\DataProvider\BookDataProvider;
use App\Entity\Book;
use PHPUnit\Framework\TestCase;

class BookDataProviderTest extends TestCase
{
    public function testGetCollection(): void
    {
        $mockRepository = $this->createMock(BookRepository::class);
        $mockRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([new Book(), new Book()]);
            
        $dataProvider = new BookDataProvider($mockRepository);
        $result = $dataProvider->getCollection(Book::class);
        
        $this->assertCount(2, $result);
    }
}
```

### Testing Custom Data Persisters

```php
namespace App\Tests\DataPersister;

use App\DataPersister\BookDataPersister;
use App\Entity\Book;
use PHPUnit\Framework\TestCase;

class BookDataPersisterTest extends TestCase
{
    public function testPersist(): void
    {
        $book = new Book();
        $book->setTitle('Test Book');
        
        $mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $mockEntityManager->expects($this->once())
            ->method('persist')
            ->with($book);
        $mockEntityManager->expects($this->once())
            ->method('flush');
            
        $dataPersister = new BookDataPersister($mockEntityManager);
        $result = $dataPersister->persist($book);
        
        $this->assertSame($book, $result);
    }
}
```

## Continuous Integration

API Platform projects can be easily integrated with CI/CD pipelines. The API Platform distribution includes a GitHub Actions workflow that:

1. Builds Docker images
2. Performs smoke tests
3. Runs the PHPUnit test suite

Here's an example GitHub Actions workflow for an API Platform project:

```yaml
# .github/workflows/ci.yml
name: CI

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          coverage: none
          
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
        
      - name: Run tests
        run: bin/phpunit
```

## Additional Testing Tools

Beyond the core testing stack, several additional tools can enhance your testing strategy:

### Hoppscotch

[Hoppscotch](https://docs.hoppscotch.io/features/tests) provides a user-friendly interface for creating and running API tests. It integrates with Swagger/OpenAPI and can be run in CI/CD pipelines using its command-line tool.

### Behat

[Behat](https://behat.org) enables behavior-driven development (BDD) by allowing you to write tests in natural language. This can be particularly useful for collaborating with non-technical stakeholders.

### PHP Matcher

[PHP Matcher](https://github.com/coduo/php-matcher) provides powerful pattern matching for JSON responses, allowing for more flexible assertions than strict equality checks.

## End-to-End Testing

For comprehensive end-to-end testing that verifies the entire stack (including database, web server, and caching layers), consider:

### Playwright

[Playwright](https://playwright.dev/) is recommended for testing JavaScript-heavy or Progressive Web Applications (PWAs). It provides a powerful API for automating browsers and can test across multiple browser engines.

### Symfony Panther

[Symfony Panther](https://github.com/symfony/panther) is ideal for testing Twig-based applications. It combines the power of Symfony's BrowserKit with real browser automation through WebDriver.

### Docker-Based Testing

For the most realistic testing environment, use the production Docker Compose setup locally:

```bash
# Start the production-like environment
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Run end-to-end tests against it
docker-compose exec php bin/phpunit
```

## Best Practices

1. **Test Isolation**: Each test should be independent and not rely on the state from previous tests.
2. **Use Factories**: Use Foundry factories to create test data instead of manually creating entities.
3. **Test Edge Cases**: Don't just test the happy path; test validation errors, authorization failures, etc.
4. **Mock External Services**: Use mocks or test doubles for external services to avoid flaky tests.
5. **Test Coverage**: Aim for high test coverage, but focus on critical paths and business logic.
6. **Performance Testing**: Include tests that verify your API meets performance requirements.
7. **Security Testing**: Include tests for authorization and authentication.
8. **API Contract Testing**: Ensure your API adheres to its OpenAPI specification.

By following these practices and using the tools described in this guide, you can build a comprehensive testing strategy for your API Platform application that ensures reliability, performance, and correctness.
