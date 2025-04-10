<?php

/**
 * Field Access Resolver Service for role-based access control.
 *
 * PHP version 8.4
 *
 * @category Service
 * @package  App\Service
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */

namespace App\Service;

use App\Attribute\AllowedRoles;
use ReflectionClass;
use ReflectionProperty;
use Psr\Log\LoggerInterface;

/**
 * Determines which fields are accessible based on portal and user roles.
 *
 * @category Service
 * @package  App\Service
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://api-platform.com
 */
class FieldAccessResolver
{
    /**
     * Logger instance.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger Logger service
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get accessible fields for a class based on user roles and portal.
     *
     * @param string $className Class name to analyze
     * @param array  $userRoles User roles to check access against
     * @param string $portal    Portal context
     *
     * @return array List of accessible field names
     */
    public function getAccessibleFields(string $className, array $userRoles, string $portal): array
    {
        $reflection = new ReflectionClass($className);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $accessibleFields = [];

        // Check each property (field) for allowed roles
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(AllowedRoles::class);

            if (empty($attributes)) {
                // If no AllowedRoles attribute, assume accessible
                $accessibleFields[] = $property->getName();
                continue;
            }

            // Check each attribute for allowed roles, must be just 1 attribute
            foreach ($attributes as $attribute) {

                // get Maps of portal to roles
                $allowedRoles = $attribute->newInstance();

                if ($allowedRoles->hasAccess($userRoles, $portal)) {
                    $accessibleFields[] = $property->getName();
                    break;
                }
            }
        }

        // Log the accessible fields
        $this->logger->info('Accessible fields for {class} with portal {portal}', [
            'class' => $className,
            'portal' => $portal,
            'userRoles' => $userRoles,
            'accessibleFields' => $accessibleFields
        ]);

        return $accessibleFields;
    }
}
