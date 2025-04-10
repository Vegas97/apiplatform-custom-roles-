<?php

/**
 * Custom attribute for role-based access control in API Platform.
 *
 * PHP version 8.4
 *
 * @category Attribute
 * @package  App\Attribute
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */

namespace App\Attribute;

use Attribute;
use LogicException;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * AllowedRoles attribute for defining role-based access control on entity properties.
 *
 * This attribute allows specifying which roles can access a property
 * through different portals (admin, workspace, distributor, etc.).
 *
 * @category Attribute
 * @package  App\Attribute
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */
class AllowedRoles
{
    /**
     * Constructor for the AllowedRoles attribute.
     *
     * @param array<string, array<string>> $portalRoles Map of portal names to their required roles
     */
    public function __construct(
        private readonly array $portalRoles = []
    ) {}

    /**
     * Get all portal roles.
     *
     * @return array<string, array<string>>
     */
    public function getPortalRoles(): array
    {
        return $this->portalRoles;
    }

    /**
     * Get roles for a specific portal.
     *
     * @param string $portalName The name of the portal to get roles for
     *
     * @return array<string> List of roles required for the specified portal
     */
    public function getRolesForPortal(string $portalName): array
    {
        return $this->portalRoles[$portalName] ?? [];
    }

    /**
     * Check if portal exists in the configuration.
     *
     * @param string $portalName The name of the portal to check
     *
     * @return bool True if the portal is configured, false otherwise
     */
    public function hasPortal(string $portalName): bool
    {
        return isset($this->portalRoles[$portalName]);
    }

    /**
     * Get all configured portal names.
     *
     * @return array<string>
     */
    public function getPortalNames(): array
    {
        return array_keys($this->portalRoles);
    }

    /**
     * Check if a user with the given roles has access via the specified portal.
     *
     * @param array<string> $userRoles  The roles of the current user
     * @param string        $portalName The portal name to check access for
     * @param string|null   $entityName The name of the entity class (optional)
     * @param string|null   $bffName    The name of the BFF (optional)
     *
     * @return bool True if access is granted, false otherwise
     * @throws LogicException If BFF name or entity name is missing
     */
    public function hasAccess(
        array $userRoles,
        string $portalName,
        ?string $entityName = null,
        ?string $bffName = null
    ): bool {
        // If the portal isn't defined in our configuration, deny access
        if (!$this->hasPortal($portalName)) {
            return false;
        }

        // Check if BFF name and entity name are provided
        if (!$bffName || !$entityName) {
            throw new LogicException(
                'Both BFF name and entity name must be provided for role-based access control'
            );
        }

        // Get the required roles for this portal
        $requiredRoles = $this->getRolesForPortal($portalName);

        // If no roles are required, deny access (empty array means no access)
        if (empty($requiredRoles)) {
            return false;
        }

        // Build the expected role name: ROLE_BFFNAME_ENTITYNAME_ACCESS
        $expectedRole = 'ROLE_' . strtoupper($bffName) . '-' . strtoupper($entityName) . '_ACCESS';

        // Check if the expected role is in the required roles for this portal
        if (!in_array($expectedRole, $requiredRoles, true)) {
            return false;
        }

        // Check if the user has the expected role
        return in_array($expectedRole, $userRoles, true);
    }
}
