<?php
/**
 * Role Access Utility for API Platform custom roles.
 *
 * PHP version 8.4
 *
 * @category Utility
 * @package  App\Utility
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */

namespace App\Utility;

use App\Attribute\AllowedRoles;

/**
 * Utility class for checking role-based access to entity properties.
 *
 * @category Utility
 * @package  App\Utility
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */
class RoleAccessChecker
{
    /**
     * Check access to entity properties based on roles and portal.
     *
     * @param string $entityClass The entity class to check access for
     * @param array  $userRoles   The roles of the current user
     * @param string $portalName  The portal name to check access for
     * 
     * @return array Access check results for each property
     */
    public static function checkEntityAccess(
        string $entityClass,
        array $userRoles,
        string $portalName
    ): array {
        // Get reflection of the entity class
        $reflectionClass = new \ReflectionClass($entityClass);
        
        $results = [];
        
        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes(AllowedRoles::class);
            
            if (!empty($attributes)) {
                $allowedRoles = $attributes[0]->newInstance();
                
                // Check if the user has access to this property via the specified portal
                $hasAccess = $allowedRoles->hasAccess($userRoles, $portalName);
                
                $results[$property->getName()] = [
                    'hasAccess' => $hasAccess,
                    'requiredRoles' => $allowedRoles->getRolesForPortal($portalName),
                    'hasPortalConfig' => $allowedRoles->hasPortal($portalName),
                ];
            } else {
                $results[$property->getName()] = [
                    'hasAccess' => false,
                    'requiredRoles' => [],
                    'hasPortalConfig' => false,
                    'note' => 'No AllowedRoles attribute defined',
                ];
            }
        }
        
        return $results;
    }
}
