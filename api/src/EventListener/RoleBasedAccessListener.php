<?php
/**
 * Role-Based Access Control Listener for API Platform.
 *
 * PHP version 8.4
 *
 * @category EventListener
 * @package  App\EventListener
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */

namespace App\EventListener;

use App\Utility\RoleAccessChecker;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * Event listener to filter API Platform resources based on role-based access control.
 *
 * This listener filters the properties of resources based on the user's roles
 * and the portal they are accessing from.
 *
 * @category EventListener
 * @package  App\EventListener
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */
class RoleBasedAccessListener
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
     * Event handler for kernel.view event.
     *
     * This method is called after the controller has been executed, but before
     * the response is created. It filters the data based on role-based access control.
     *
     * @param ViewEvent $event The view event
     *
     * @return void
     */
    public function onKernelView(ViewEvent $event): void
    {
        $request = $this->_requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        // Only process API Platform requests
        $route = $request->attributes->get('_route');
        if (!$route || !str_starts_with($route, 'api_')) {
            return;
        }

        $data = $event->getControllerResult();
        if (null === $data) {
            return;
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
                'route' => $route,
                'userRoles' => $userRoles,
                'portal' => $portal,
            ]
        );

        // Apply role-based access control
        $filteredData = $this->_applyRoleBasedAccess($data, $userRoles, $portal);
        
        // Set the filtered data as the controller result
        $event->setControllerResult($filteredData);
    }

    /**
     * Apply role-based access control to the data.
     *
     * @param mixed  $data      The data to filter
     * @param array  $userRoles The user roles
     * @param string $portal    The portal
     *
     * @return mixed The filtered data
     */
    private function _applyRoleBasedAccess(mixed $data, array $userRoles, string $portal): mixed
    {
        if (is_array($data) && isset($data['hydra:member'])) {
            // Collection resource
            foreach ($data['hydra:member'] as $key => $item) {
                $data['hydra:member'][$key] = $this->_filterResource($item, $userRoles, $portal);
            }
            return $data;
        }

        // Single resource
        return $this->_filterResource($data, $userRoles, $portal);
    }

    /**
     * Filter a resource based on role-based access control.
     *
     * @param mixed  $resource  The resource to filter
     * @param array  $userRoles The user roles
     * @param string $portal    The portal
     *
     * @return mixed The filtered resource
     */
    private function _filterResource(mixed $resource, array $userRoles, string $portal): mixed
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
