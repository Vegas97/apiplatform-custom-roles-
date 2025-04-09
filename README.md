<h1 align="center"><a href="https://api-platform.com"><img src="https://api-platform.com/images/logos/Logo_Circle%20webby%20text%20blue.png" alt="API Platform" width="250" height="250"></a></h1>

# API Platform: Technical Guide

Documentation:

[https://api-platform.com/docs/symfony/](https://api-platform.com/docs/symfony/)

API Platform is a powerful full-stack framework designed for building API-first projects. This README provides a comprehensive overview of API Platform's features, architecture, and implementation steps.

## Table of Contents

-   [Core Concepts](#core-concepts)
-   [Installation and Setup](#installation-and-setup)
-   [Data Modeling](#data-modeling)
-   [Persistence Layer](#persistence-layer)
-   [Validation](#validation)
-   [API Features](#api-features)
-   [GraphQL Support](#graphql-support)
-   [Admin Interface](#admin-interface)
-   [Client Applications](#client-applications)
-   [Security and Performance](#security-and-performance)
-   [Custom Roles Implementation](#custom-roles-implementation)

## Core Concepts

API Platform is built on the following core principles:

-   **API-First Development**: Design your API before implementing the underlying logic
-   **Resource-Oriented Architecture**: Based on REST principles and resource modeling
-   **Hypermedia-Driven APIs**: Leverages HATEOAS for discoverable APIs
-   **Content Negotiation**: Supports multiple formats (JSON-LD, Hydra, HAL, JSON:API)
-   **Standards Compliance**: Follows web standards and best practices

## Installation and Setup

### Using Docker (Recommended)

1. Download the API Platform distribution or generate a GitHub repository from the template:

    ```bash
    # Prefer the .tar.gz archive over .zip to avoid permission issues
    ```

2. Build and start the Docker containers:

    ```bash
    docker compose build --no-cache
    docker compose up --wait
    ```

3. Access the different components:
    - API: https://localhost/docs/
    - Admin: https://localhost/admin/
    - PWA: https://localhost/

### Alternative Setup

For non-Docker environments, you can install API Platform with Symfony Flex:

```bash
composer create-project symfony/skeleton my-api
cd my-api
composer require api
```

## Data Modeling

API Platform uses PHP classes with attributes to define your API resources:

```php
// api/src/Entity/Book.php
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** A book. */
#[ORM\Entity]
#[ApiResource]
class Book
{
    /** The ID of this book. */
    #[ORM\Id, ORM\Column, ORM\GeneratedValue]
    private ?int $id = null;

    /** The ISBN of this book (or null if doesn't have one). */
    #[ORM\Column(nullable: true)]
    #[Assert\Isbn]
    public ?string $isbn = null;

    /** The title of this book. */
    #[ORM\Column]
    #[Assert\NotBlank]
    public string $title = '';

    /** The description of this book. */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    public string $description = '';

    /** The author of this book. */
    #[ORM\Column]
    #[Assert\NotBlank]
    public string $author = '';

    /** The publication date of this book. */
    #[ORM\Column]
    #[Assert\NotNull]
    public ?\DateTimeImmutable $publicationDate = null;

    /** @var Review[] Available reviews for this book. */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'book', cascade: ['persist', 'remove'])]
    public iterable $reviews;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
```

## Persistence Layer

### Doctrine ORM (Default)

API Platform integrates seamlessly with Doctrine ORM:

1. Add Doctrine mapping attributes to your entity classes
2. Generate and run migrations:
    ```bash
    bin/console doctrine:migrations:diff
    bin/console doctrine:migrations:migrate
    ```

### Alternative Persistence Systems

API Platform supports multiple persistence systems:

-   Doctrine MongoDB ODM
-   Elasticsearch
-   Custom state providers and processors

## Validation

API Platform uses the Symfony Validator component:

```php
// Add validation constraints using attributes
#[Assert\NotBlank]
public string $title = '';

#[Assert\Range(min: 0, max: 5)]
public int $rating = 0;
```

## API Features

Out of the box, API Platform provides:

-   **CRUD Operations**: Automatically generated for each resource
-   **Pagination**: Built-in support for offset and cursor-based pagination
-   **Filtering**: Extensive filtering capabilities
-   **Sorting**: Sort collections by any property
-   **Documentation**: Auto-generated OpenAPI/Swagger documentation
-   **Content Negotiation**: Support for multiple formats through extensions or Accept headers

## GraphQL Support

Enable GraphQL support by installing the GraphQL package:

```bash
composer require api-platform/graphql
```

This provides:

-   GraphQL API endpoint at `/graphql`
-   GraphiQL UI for testing
-   Support for queries and mutations
-   100% Relay server specification compliance

## Admin Interface

API Platform includes a dynamic admin interface:

-   Built with React Admin
-   Material Design UI
-   Progressive Web App
-   Automatically generated from API documentation
-   Fully customizable

## Client Applications

Generate client applications using the API Platform Client Generator:

```bash
docker compose exec pwa pnpm create @api-platform/client
```

Supported frameworks:

-   Next.js
-   Nuxt.js
-   React/Redux
-   Vue.js
-   Quasar
-   Vuetify
-   React Native

## Security and Performance

API Platform includes:

-   **Authentication**: Support for JWT, OAuth, and HTTP Basic
-   **Authorization**: Fine-grained access control
-   **CORS Support**: Configurable CORS headers
-   **HTTP Caching**: Invalidation-based HTTP caching
-   **Security Headers**: OWASP-compliant security headers

## Custom Roles Implementation

This project focuses on implementing custom roles in API Platform. Key aspects include:

1. **Role-Based Access Control**: Implementing custom roles beyond the default Symfony roles
2. **Resource-Level Permissions**: Controlling access to specific API resources based on roles
3. **Operation-Level Permissions**: Restricting operations (GET, POST, PUT, DELETE) based on user roles
4. **Field-Level Access Control**: Controlling visibility of specific fields based on user roles
5. **Dynamic Permission Resolution**: Runtime permission evaluation based on user context

### Implementation Steps

1. Create custom security voters
2. Configure access control in API resources
3. Implement field-level access control
4. Set up operation-based permissions
5. Create custom security extensions

For more information, visit the [API Platform documentation](https://api-platform.com/docs/).
