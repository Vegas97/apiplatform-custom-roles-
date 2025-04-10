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
use DateTime;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
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
     * Constructor.
     *
     * @param FieldAccessResolver $fieldAccessResolver Field access resolver service
     * @param RequestStack        $requestStack        Request stack service
     * @param LoggerInterface     $logger              Logger service
     */
    public function __construct(
        FieldAccessResolver $fieldAccessResolver,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->fieldAccessResolver = $fieldAccessResolver;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
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
        // Get the current portal from request
        $request = $this->requestStack->getCurrentRequest();
        // $portal = $request->query->get('portal', 'workspace');
        // $userRoles = $request->query->get('userRoles', ['ROLE_USER_ACCESS']);

        // testing purpose
        $portal = 'distributor';
        $userRoles = ['ROLE_USER_ACCESS'];

        // In a real app, you'd get user roles from security context
        // For this example, we'll use a query parameter
        if (!is_array($userRoles)) {
            $userRoles = [$userRoles];
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
