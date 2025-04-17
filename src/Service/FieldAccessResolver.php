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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

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
    private LoggerInterface $_logger;

    /**
     * Parameter bag for accessing environment variables.
     *
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $_parameterBag;

    /**
     * Constructor.
     *
     * @param LoggerInterface       $logger      Logger service
     * @param ParameterBagInterface $parameterBag Parameter bag for accessing env vars
     */
    public function __construct(LoggerInterface $logger, ParameterBagInterface $parameterBag)
    {
        $this->_logger = $logger;
        $this->_parameterBag = $parameterBag;
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

        // Get BFF name from environment variable, default to null if not set
        $bffName = $this->_parameterBag->has('app.bff_name')
            ? $this->_parameterBag->get('app.bff_name')
            : null;

        // Extract the short class name
        $parts = explode('\\', $className);
        $shortClassName = end($parts);

        foreach ($properties as $property) {
            $allowedRolesAttribute = $property->getAttributes(AllowedRoles::class)[0];

            if (empty($allowedRolesAttribute)) {
                // If no AllowedRoles attribute, assume accessible // TODO: not sure about this
                $accessibleFields[] = $property->getName();
                continue;
            }

            $allowedRoles = $allowedRolesAttribute->newInstance();

            // Pass the class name and BFF name to the hasAccess method for more specific role checking
            if ($allowedRoles->hasAccess($userRoles, $portal, $shortClassName, $bffName)) {
                $accessibleFields[] = $property->getName();
                continue;
            }
        }

        // Log accessible fields
        $this->_logger->info('Accessible fields: $accessibleFields', $accessibleFields);

        return $accessibleFields;
    }
}
