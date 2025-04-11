<?php

/**
 * Abstract DTO Provider for API Platform.
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
use App\Service\FieldAccessResolver;
use App\Service\JwtService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use ReflectionClass;
use ReflectionProperty;

/**
 * Abstract provider for DTO resources with role-based access control.
 *
 * This abstract class provides common functionality for all DTO providers,
 * including authentication, field access resolution, and field filtering.
 *
 * @category State
 * @package  App\State
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
abstract class AbstractDtoProvider implements ProviderInterface
{
    /**
     * Field access resolver service.
     *
     * @var FieldAccessResolver
     */
    protected FieldAccessResolver $fieldAccessResolver;

    /**
     * Request stack service.
     *
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    /**
     * Logger service.
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * JWT service.
     *
     * @var JwtService
     */
    protected JwtService $jwtService;
    
    /**
     * Parameter bag for accessing configuration.
     *
     * @var ParameterBagInterface
     */
    protected ParameterBagInterface $parameterBag;

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
        $this->jwtService = $jwtService;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Provides DTO objects for API Platform with role-based access control.
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
        $isTestEnv = $this->parameterBag->get('kernel.environment') === 'test';
        
        try {
            // Use the JwtService to extract authentication data (portal and roles)
            // This handles both JWT tokens and query parameters for testing
            $authData = $this->jwtService->extractAuthData($authHeader, $request, $isTestEnv);
            
            // Dynamically extract portal and roles from the authentication data
            $portal = $authData['portal'] ?? null;
            $userRoles = $authData['roles'] ?? [];
            
            // Validate that we have the required authentication data
            if (null === $portal) {
                throw new UnauthorizedHttpException('Bearer', 'Missing portal information in authentication data');
            }
            
            if (empty($userRoles)) {
                $this->logger->warning('User has no roles assigned', [
                    'portal' => $portal
                ]);
            }
            
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

        // Get the DTO class name
        $dtoClass = $this->getDtoClass();

        // Get accessible fields
        $accessibleFields = $this->fieldAccessResolver->getAccessibleFields(
            $dtoClass,
            $userRoles,
            $portal
        );

        // Generate data
        $items = $this->getItems();

        // If it's a collection operation
        if ($operation->getName() === 'get_collection') {
            // Filter each item to only include accessible fields
            return array_map(function ($item) use ($accessibleFields) {
                return $this->filterItemFields($item, $accessibleFields);
            }, $items);
        }

        // If it's an item operation
        $id = $uriVariables['id'] ?? null;
        if ($id) {
            foreach ($items as $item) {
                if ($item->id === $id) {
                    return $this->filterItemFields($item, $accessibleFields);
                }
            }
        }

        return null;
    }

    /**
     * Filter item fields based on accessible fields.
     *
     * @param object $item            DTO object to filter
     * @param array  $accessibleFields List of accessible field names
     *
     * @return object|null Filtered DTO object or null if no fields are accessible
     */
    protected function filterItemFields(object $item, array $accessibleFields): ?object
    {
        // If no fields are accessible, return null (no access)
        if (empty($accessibleFields)) {
            $this->logger->warning('No accessible fields for item', [
                'itemId' => $item->id,
                'accessibleFields' => $accessibleFields
            ]);
            return null;
        }

        $dtoClass = $this->getDtoClass();
        
        // Create a new DTO instance with only the accessible fields
        $filteredItem = new $dtoClass();

        // Use reflection to set only the accessible properties
        $reflection = new ReflectionClass($dtoClass);

        foreach ($accessibleFields as $field) {
            if ($reflection->hasProperty($field)) {
                $property = $reflection->getProperty($field);
                if ($property->isPublic()) {
                    $property->setValue($filteredItem, $property->getValue($item));
                }
            }
        }

        // Log the filtered item
        $this->logger->info('Filtered item', [
            'itemId' => $item->id,
            'accessibleFields' => $accessibleFields,
            'result' => json_encode($filteredItem)
        ]);

        return $filteredItem;
    }

    /**
     * Get the DTO class name.
     *
     * @return string The fully qualified class name of the DTO
     */
    abstract protected function getDtoClass(): string;

    /**
     * Get items for the provider.
     *
     * @return array Array of DTO objects
     */
    abstract protected function getItems(): array;
}
