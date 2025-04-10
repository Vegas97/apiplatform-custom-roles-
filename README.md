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

4. Add Health Check

A health check endpoint has been added at `/health`.

5. Install API Platform

```bash
# Inside the Docker container
composer require api-platform/core symfony/twig-bundle symfony/asset symfony/security-bundle nelmio/cors-bundle symfony/expression-language
bin/console cache:clear
bin/console assets:install public
```

API Platform is now installed with all necessary dependencies for the documentation UI.

6. Create DTO Resources

Created a UserDto class in `src/ApiResource` with a custom state provider in `src/State`:

```bash
# Create directories
mkdir -p src/ApiResource
mkdir -p src/State
```

The UserDto includes basic user information (id, username, email, birthDate) without database integration.

## Debugging

The project is configured with Xdebug for debugging:

- IDE key: PHPSTORM
- Host: localhost
- Port: 9003

### VS Code Setup

For VS Code users, debugging configuration is provided in the `.vscode` directory:

1. Install the PHP Debug extension by Felix Becker
2. Set breakpoints in your code
3. Start the Docker containers: `docker compose up -d`
4. Start debugging by pressing F5 or using the Run and Debug panel

## Docker Configuration

- **PHP 8.4.5**: Latest stable version with Xdebug
- **Nginx**: Web server
- **Volumes**: Local development files are mounted into the containers

## API Platform Configuration

- **No Database**: Using custom state providers instead of Doctrine ORM
- **DTO Approach**: Using Data Transfer Objects instead of entities
- **Documentation**: Available at [http://localhost:8080/api](http://localhost:8080/api)
- **Endpoints**:
  - Collection: [http://localhost:8080/api/user_dtos](http://localhost:8080/api/user_dtos)
  - Single item: [http://localhost:8080/api/user_dtos/1](http://localhost:8080/api/user_dtos/1)

## Development Workflow

1. Use the `machine.sh` script to start containers and connect to the PHP container:

```bash
./machine.sh
```

2. After making changes to API resources, clear cache if needed:

```bash
bin/console cache:clear
```

3. Only run `bin/console assets:install public` when installing new bundles with frontend assets
