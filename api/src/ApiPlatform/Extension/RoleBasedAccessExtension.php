<?php
/**
 * Role-Based Access Control Extension for API Platform.
 *
 * PHP version 8.4
 *
 * @category ApiPlatform\Extension
 * @package  App\ApiPlatform\Extension
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */

namespace App\ApiPlatform\Extension;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Attribute\AllowedRoles;
use App\Utility\RoleAccessChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\CollectionProvider;

/**
 * Extension to filter API Platform resources based on role-based access control.
 *
 * This extension filters the properties of resources based on the user's roles
 * and the portal they are accessing from.
 *
 * @category ApiPlatform\Extension
 * @package  App\ApiPlatform\Extension
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */
class RoleBasedAccessExtension implements ProviderInterface
{
    /**
     * Logger for debugging access control.
     *
     * @var LoggerInterface
     */
    private $_logger;

    /**
     * The request stack.
     *
     * @var RequestStack
     */
    private $_requestStack;

    /**
     * Constructor.
     *
     * @param RequestStack    $requestStack The request stack
     * @param LoggerInterface $logger       Logger for debugging
     */
    public function __construct(
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {
        $this->_requestStack = $requestStack;
        $this->_logger = $logger;
    }

    /**
     * Provides data with role-based access control.
     *
     * @param Operation $operation The operation
     * @param array     $uriVariables URI variables
     * @param array     $context The context
     *
     * @return object|array|null The data
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Get the original data from the default provider
        $collectionProvider = new CollectionProvider(new Pagination());
        $data = $collectionProvider->provide($operation, $uriVariables, $context);
        
        if (null === $data) {
            return null;
        }

        $request = $this->_requestStack->getCurrentRequest();
        if (null === $request) {
            return $data;
        }

        // Get user roles from request header (in a real app, this would come from security context)
        $userRoles = $request->headers->get('X-USER-ROLES', '');
        $userRoles = $userRoles ? explode(',', $userRoles) : [];

        // Get portal from request header
        $portal = $request->headers->get('X-PORTAL', 'admin');

        // Log access information
        $this->_logger->info(
            sprintf("userRole: [%s]\nportal: %s", implode(', ', $userRoles), $portal),
            [
                'operation' => $operation->getName(),
                'userRoles' => $userRoles,
                'portal' => $portal,
            ]
        );

        // Handle both collection and item resources
        if (is_array($data) && isset($data['hydra:member'])) {
            // Collection resource
            foreach ($data['hydra:member'] as $key => $item) {
                $data['hydra:member'][$key] = $this->_filterResource($item, $userRoles, $portal);
            }
            return $data;
        }

        // Item resource
        return $this->_filterResource($data, $userRoles, $portal);
    }

    /**
     * Filter a resource based on role-based access control.
     *
     * @param object|array $resource  The resource to filter
     * @param array        $userRoles The user roles
     * @param string       $portal    The portal
     *
     * @return object|array The filtered resource
     */
    private function _filterResource(object|array $resource, array $userRoles, string $portal): object|array
    {
        if (is_object($resource)) {
            $resourceClass = get_class($resource);
            $accessResults = RoleAccessChecker::checkEntityAccess(
                $resourceClass,
                $userRoles,
                $portal
            );

            // Log access results
            $allowedFields = [];
            foreach ($accessResults as $property => $accessInfo) {
                if ($accessInfo['hasAccess']) {
                    $allowedFields[] = $property;
                }
            }
            
            $this->_logger->info(
                sprintf("allowed:\n%s", implode(",\n", $allowedFields)),
                ['accessResults' => $accessResults]
            );

            // Filter properties based on access results
            foreach ($accessResults as $property => $accessInfo) {
                if (!$accessInfo['hasAccess'] && property_exists($resource, $property)) {
                    // Remove property that the user doesn't have access to
                    unset($resource->$property);
                }
            }
        } elseif (is_array($resource)) {
            // For array data, we need to determine the entity class
            // This is a simplification - in a real app, you might need more complex logic
            $resourceClass = $resource['@type'] ?? null;
            
            if ($resourceClass) {
                // Convert API Platform type to class name (e.g., 'Book' -> 'App\Entity\Book')
                $entityClass = 'App\\Entity\\' . $resourceClass;
                
                if (class_exists($entityClass)) {
                    $accessResults = RoleAccessChecker::checkEntityAccess(
                        $entityClass,
                        $userRoles,
                        $portal
                    );

                    // Log access results
                    $this->_logger->debug(
                        'Access check results',
                        ['accessResults' => $accessResults]
                    );

                    // Filter properties based on access results
                    foreach ($accessResults as $property => $accessInfo) {
                        if (!$accessInfo['hasAccess'] && isset($resource[$property])) {
                            // Remove property that the user doesn't have access to
                            unset($resource[$property]);
                        }
                    }
                }
            }
        }

        return $resource;
    }
}
