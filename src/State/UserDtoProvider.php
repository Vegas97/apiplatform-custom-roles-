<?php

/**
 * User DTO Provider for API Platform.
 *
 * @category State
 * @package  App\State
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 * @version  GIT: <git_id>
 */

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\UserDto;
use App\Service\FieldAccessResolver;
use App\Service\JwtService;
use DateTime;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use ReflectionClass;
use ReflectionProperty;

/**
 * Provider for UserDto resources with role-based access control.
 *
 * @category State
 * @package  App\State
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class UserDtoProvider implements ProviderInterface
{
    /**
     * Field access resolver service.
     *
     * @var FieldAccessResolver
     */
    private FieldAccessResolver $fieldAccessResolver;

    /**
     * Request stack service.
     *
     * @var RequestStack
     */
    private RequestStack $requestStack;

    /**
     * Logger service.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * JWT service.
     *
     * @var JwtService
     */
    private JwtService $_jwtService;
    
    /**
     * Parameter bag for accessing configuration.
     *
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $_parameterBag;

    /**
     * Constructor.
     *
     * @param FieldAccessResolver   $fieldAccessResolver Field access resolver service
     * @param RequestStack          $requestStack        Request stack service
     * @param LoggerInterface       $logger              Logger service
     * @param JwtService            $jwtService          JWT service
     * @param ParameterBagInterface $parameterBag        Parameter bag for configuration
     */
    public function __construct(
        FieldAccessResolver $fieldAccessResolver,
        RequestStack $requestStack,
        LoggerInterface $logger,
        JwtService $jwtService,
        ParameterBagInterface $parameterBag
    ) {
        $this->fieldAccessResolver = $fieldAccessResolver;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->_jwtService = $jwtService;
        $this->_parameterBag = $parameterBag;
    }

    /**
     * Provides UserDto objects for API Platform with role-based access control.
     *
     * @param Operation $operation    The operation
     * @param array     $uriVariables The URI variables
     * @param array     $context      The context
     *
     * @return object|array|null The data
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get the current request
        $request = $this->requestStack->getCurrentRequest();
        
        // Get the Authorization header
        $authHeader = $request->headers->get('Authorization');
        
        // Check if we're in test environment
        $isTestEnv = $this->_parameterBag->get('kernel.environment') === 'test';
        
        try {
            // Use the JwtService to extract authentication data (portal and roles)
            // This handles both JWT tokens and query parameters for testing
            $authData = $this->_jwtService->extractAuthData($authHeader, $request, $isTestEnv);
            
            $portal = $authData['portal'];
            $userRoles = $authData['roles'];
            
            $this->logger->info('Authentication successful', [
                'portal' => $portal,
                'roles' => $userRoles
            ]);
        } catch (UnauthorizedHttpException $e) {
            $this->logger->error('Authentication error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }

        // Log the request parameters
        $this->logger->info('Processing request', [
            'operation' => $operation->getName(),
            'portal' => $portal,
            'userRoles' => $userRoles
        ]);

        // Get accessible fields
        $accessibleFields = $this->fieldAccessResolver->getAccessibleFields(
            UserDto::class,
            $userRoles,
            $portal
        );

        // Generate sample data
        $users = $this->getSampleUsers();

        // If it's a collection operation
        if ($operation->getName() === 'get_collection') {
            // Filter each user to only include accessible fields
            return array_map(function ($user) use ($accessibleFields) {
                return $this->filterUserFields($user, $accessibleFields);
            }, $users);
        }

        // If it's an item operation
        $id = $uriVariables['id'] ?? null;
        if ($id) {
            foreach ($users as $user) {
                if ($user->id === $id) {
                    return $this->filterUserFields($user, $accessibleFields);
                }
            }
        }

        return null;
    }

    /**
     * Filter user fields based on accessible fields.
     *
     * @param UserDto $user            User DTO to filter
     * @param array   $accessibleFields List of accessible field names
     *
     * @return UserDto|null Filtered user DTO or null if no fields are accessible
     */
    private function filterUserFields(UserDto $user, array $accessibleFields): ?UserDto
    {
        // If no fields are accessible, return null (no access)
        if (empty($accessibleFields)) {
            $this->logger->warning('No accessible fields for user', [
                'userId' => $user->id,
                'accessibleFields' => $accessibleFields
            ]);
            return null;
        }

        // Create a new UserDto with only the accessible fields
        $filteredUser = new UserDto();

        // Use reflection to set only the accessible properties
        $reflection = new ReflectionClass(UserDto::class);

        foreach ($accessibleFields as $field) {
            if ($reflection->hasProperty($field)) {
                $property = $reflection->getProperty($field);
                if ($property->isPublic()) {
                    $property->setValue($filteredUser, $property->getValue($user));
                }
            }
        }

        // Log the filtered user
        $this->logger->info('Filtered user', [
            'userId' => $user->id,
            'accessibleFields' => $accessibleFields,
            'result' => json_encode($filteredUser)
        ]);

        return $filteredUser;
    }

    /**
     * Get sample users for testing.
     *
     * @return array Array of UserDto objects
     */
    private function getSampleUsers(): array
    {
        // Sample data - in a real app, this would come from a database or other source
        return [
            new UserDto(
                '1',
                'john_doe',
                'john@example.com',
                new DateTime('1990-01-15')
            ),
            new UserDto(
                '2',
                'admin_user',
                'admin@example.com',
                new DateTime('1985-05-22')
            ),
            new UserDto(
                '3',
                'support_agent',
                'support@example.com',
                new DateTime('1992-11-07')
            )
        ];
    }
}
