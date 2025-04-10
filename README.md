# API Platform Custom Roles Project

A minimal setup project demonstrating custom role implementation in API Platform with Symfony.

## Tech Stack

- **PHP 8.4**: Backend programming language
- **Symfony 6.4**: PHP framework
- **API Platform**: REST and GraphQL framework for Symfony
- **Docker**: Containerization for development environment with Xdebug support
- **No Database**: This project operates without a database connection

## Setup Instructions

1. Initialize a new Symfony project

```bash
composer create-project symfony/skeleton:"^6.4" .
composer install
composer require symfony/debug-bundle
```

2. Add Docker and Debugger

Docker configuration has been set up with PHP 8.4.5 and Xdebug for debugging.

```bash
# Build and start the Docker containers
docker compose up -d --build
```

3. Access the application

The application will be available at [http://localhost:8080](http://localhost:8080)

## Debugging

The project is configured with Xdebug for debugging:

- IDE key: PHPSTORM
- Host: localhost
- Port: 9003

## Docker Configuration

- **PHP 8.4.5**: Latest stable version with Xdebug
- **Nginx**: Web server
- **Volumes**: Local development files are mounted into the containers
