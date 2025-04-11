<?php

/**
 * Microservice Entity Mapping Configuration.
 *
 * PHP version 8.4
 *
 * @category Config
 * @package  App\Config
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 * @version  GIT: <git_id>
 */

declare(strict_types=1);

namespace App\Config;

/**
 * Configuration class for microservice entity mappings.
 *
 * This class defines the mapping between microservice entities and their fields,
 * including the context levels available for each field.
 *
 * @category Config
 * @package  App\Config
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class MicroserviceEntityMapping
{
    /**
     * Get the microservice base URLs.
     *
     * @return array The microservice base URLs
     */
    public static function getMicroserviceBaseUrls(): array
    {
        return [
            'guest-service' => 'https://guest-service.domain',
            'reservation-service' => 'https://reservation-service.domain'
            // ...
        ];
    }

    /**
     * Get the entity routes.
     *
     * @return array The entity routes
     */
    public static function getEntityRoutes(): array
    {
        return [
            'guest-service' => [
                'Guest' => '/api/guests',
                // 'GuestPreference' => '/api/guest-preferences'
                // ...
            ],
            'reservation-service' => [
                'Reservation' => '/api/reservations',
                // 'Room' => '/api/rooms'
                // ...
            ]
        ];
    }

    /**
     * Get the entity endpoints.
     *
     * @return array The entity endpoints
     */
    public static function getEntityEndpoints(): array
    {
        $baseUrls = self::getMicroserviceBaseUrls();
        $routes = self::getEntityRoutes();
        $endpoints = [];

        foreach ($routes as $microservice => $entities) {
            if (!isset($baseUrls[$microservice])) {
                continue;
            }

            $baseUrl = $baseUrls[$microservice];
            $endpoints[$microservice] = [];

            foreach ($entities as $entity => $route) {
                $endpoints[$microservice][$entity] = $route;
            }
        }

        return $endpoints;
    }

    /**
     * Get the entity field mappings with their contexts.
     *
     * @return array The entity field mappings
     */
    public static function getEntityFieldMappings(): array
    {
        return [
            'guest-service' => [
                'Guest' => [
                    'id' => ['context_ids', 'context_mini', 'context_normal', 'context_full'],
                    'fullName' => ['context_mini', 'context_normal', 'context_full'],
                    'email' => ['context_mini', 'context_normal', 'context_full'],
                    'countryCode' => ['context_mini', 'context_full'],
                    'dateOfBirth' => ['context_normal', 'context_full'],
                    'documentId' => ['context_normal', 'context_full'],
                    'phoneNumber' => ['context_normal', 'context_full'],
                    'reservationId' => ['context_mini', 'context_normal', 'context_full'],
                    'address' => ['context_full'],
                    'createdAt' => ['context_full'],
                    'updatedAt' => ['context_full']
                ]
            ],
            'reservation-service' => [
                'Reservation' => [
                    'id' => ['context_ids', 'context_mini', 'context_normal', 'context_full'],
                    'guestDocumentId' => ['context_ids', 'context_mini', 'context_normal', 'context_full'],
                    'arrivalDate' => ['context_mini', 'context_normal', 'context_full'],
                    'departureDate' => ['context_mini', 'context_normal', 'context_full'],
                    'roomNumber' => ['context_normal', 'context_full'],
                    'status' => ['context_mini', 'context_normal', 'context_full'],
                    'paymentStatus' => ['context_normal', 'context_full'],
                    'totalAmount' => ['context_normal', 'context_full'],
                    'specialRequests' => ['context_full'],
                    'createdAt' => ['context_full'],
                    'updatedAt' => ['context_full']
                ]
            ]
        ];
    }

    /**
     * Get the highest available context for a field.
     *
     * @param string $microservice The microservice name
     * @param string $entity       The entity name
     * @param string $field        The field name
     *
     * @return string|null The highest available context or null if not found
     */
    public static function getHighestContextForField(
        string $microservice,
        string $entity,
        string $field
    ): ?string {
        $mappings = self::getEntityFieldMappings();

        if (!isset($mappings[$microservice][$entity][$field])) {
            return null;
        }

        $contexts = $mappings[$microservice][$entity][$field];

        // Return the highest context (last in the array)
        return end($contexts);
    }

    /**
     * Get the lowest available context for a field.
     *
     * @param string $microservice The microservice name
     * @param string $entity       The entity name
     * @param string $field        The field name
     *
     * @return string|null The lowest available context or null if not found
     */
    public static function getLowestContextForField(
        string $microservice,
        string $entity,
        string $field
    ): ?string {
        $mappings = self::getEntityFieldMappings();

        if (!isset($mappings[$microservice][$entity][$field])) {
            return null;
        }

        $contexts = $mappings[$microservice][$entity][$field];

        // Return the lowest context (first in the array)
        return reset($contexts);
    }

    /**
     * Check if a field is available in a specific context.
     *
     * @param string $microservice The microservice name
     * @param string $entity       The entity name
     * @param string $field        The field name
     * @param string $context      The context to check
     *
     * @return bool True if the field is available in the context, false otherwise
     */
    public static function isFieldAvailableInContext(
        string $microservice,
        string $entity,
        string $field,
        string $context
    ): bool {
        $mappings = self::getEntityFieldMappings();

        if (!isset($mappings[$microservice][$entity][$field])) {
            return false;
        }

        return in_array($context, $mappings[$microservice][$entity][$field]);
    }

    /**
     * Check if a field is available in any context.
     *
     * @param string $microservice The microservice name
     * @param string $entity       The entity name
     * @param string $field        The field name
     *
     * @return bool True if the field is available in any context, false otherwise
     */
    public static function isFieldAvailable(
        string $microservice,
        string $entity,
        string $field
    ): bool {
        $mappings = self::getEntityFieldMappings();

        return isset($mappings[$microservice][$entity][$field]);
    }

    /**
     * Get the endpoint for a specific entity.
     *
     * This method combines the microservice base URL and the entity route
     * to create a full endpoint URL.
     *
     * @param string $microservice The microservice name
     * @param string $entity       The entity name
     *
     * @return string|null The full endpoint URL or null if not found
     */
    public static function getEndpointForEntity(
        string $microservice,
        string $entity
    ): ?string {
        $routes = self::getEntityRoutes();

        if (!isset($routes[$microservice][$entity])) {
            return null;
        }

        return $routes[$microservice][$entity];
    }

    /**
     * Get the full URL for a specific entity.
     *
     * This method combines the microservice base URL and the entity route
     * to create a full endpoint URL.
     *
     * @param string $microservice The microservice name
     * @param string $entity       The entity name
     *
     * @return string|null The full endpoint URL or null if not found
     */
    public static function getFullUrlForEntity(
        string $microservice,
        string $entity
    ): ?string {
        $baseUrls = self::getMicroserviceBaseUrls();
        $routes = self::getEntityRoutes();

        if (!isset($baseUrls[$microservice]) || !isset($routes[$microservice][$entity])) {
            return null;
        }

        $baseUrl = rtrim($baseUrls[$microservice], '/');
        $route = ltrim($routes[$microservice][$entity], '/');

        return $baseUrl . '/' . $route;
    }
    
    /**
     * Determine the appropriate context level for a set of fields.
     *
     * This method finds the minimal context level that includes all the required fields.
     * It starts with the lowest context level and checks if all fields are available.
     * If not, it moves to a higher context level until all fields are available.
     *
     * @param string $microservice   The microservice name
     * @param string $entity         The entity name
     * @param array  $requiredFields The fields that need to be included
     * @param string $defaultContext Default context to use if no fields are specified
     *
     * @return string The determined context level
     */
    public static function determineContextForFields(
        string $microservice,
        string $entity,
        array $requiredFields,
        string $defaultContext = 'context_normal'
    ): string {
        // If no fields are specified, return the default context
        if (empty($requiredFields)) {
            return $defaultContext;
        }
        
        // Get the context levels in order from lowest to highest
        $contextLevels = ['context_ids', 'context_mini', 'context_normal', 'context_full'];
        
        // For each context level, check if all required fields are available
        foreach ($contextLevels as $contextLevel) {
            $allFieldsAvailable = true;
            
            // Check each required field
            foreach ($requiredFields as $field) {
                // Skip empty fields
                if (empty($field)) {
                    continue;
                }
                
                // Check if the field is available in this context level
                if (!self::isFieldAvailableInContext($microservice, $entity, $field, $contextLevel)) {
                    $allFieldsAvailable = false;
                    break;
                }
            }
            
            // If all fields are available at this context level, return it
            if ($allFieldsAvailable) {
                return $contextLevel;
            }
        }
        
        // If no suitable context level is found, return the highest level
        return end($contextLevels);
    }
    
    // The determineContext method has been removed as it was redundant with determineContextForFields
}
