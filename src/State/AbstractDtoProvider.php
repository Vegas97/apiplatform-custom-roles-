<?php

/**
 * Abstract DTO Provider for API Platform.
 *
 * PHP version 8.4
 *
 * @category State
 * @package  App\State
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @version  GIT: <git_id>
 * @link     https://apiplatform.com
 */

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Attribute\MicroserviceField;
use App\Attribute\MicroserviceRelationship;
use App\Config\MicroserviceEntityMapping;
use App\Dto\EntityMappingDto;
use App\Service\FieldAccessResolver;
use App\Service\JwtService;
use App\Service\MicroserviceClient;
use App\Service\MockMicroserviceClient;
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
abstract class AbstractDtoProvider
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
     * Microservice client service.
     *
     * @var MicroserviceClient
     */
    protected MicroserviceClient $microserviceClient;

    /**
     * Mock microservice client service.
     *
     * @var MockMicroserviceClient
     */
    protected MockMicroserviceClient $mockClient;

    /**
     * Constructor.
     *
     * @param FieldAccessResolver    $fieldAccessResolver Field access resolver service
     * @param RequestStack           $requestStack        Request stack service
     * @param LoggerInterface        $logger              Logger service
     * @param JwtService             $jwtService          JWT service
     * @param ParameterBagInterface  $parameterBag        Parameter bag for configuration
     * @param MicroserviceClient     $microserviceClient  Microservice client service
     * @param MockMicroserviceClient $mockClient          Mock microservice client service
     */
    public function __construct(
        FieldAccessResolver $fieldAccessResolver,
        RequestStack $requestStack,
        LoggerInterface $logger,
        JwtService $jwtService,
        ParameterBagInterface $parameterBag,
        MicroserviceClient $microserviceClient,
        MockMicroserviceClient $mockClient
    ) {
        $this->fieldAccessResolver = $fieldAccessResolver;
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->jwtService = $jwtService;
        $this->parameterBag = $parameterBag;
        $this->microserviceClient = $microserviceClient;
        $this->mockClient = $mockClient;
    }

    /**
     * Determines whether to use real microservice data or mock data.
     *
     * @return bool True if real data should be used, false for mock data
     */
    protected function shouldUseRealData(): bool
    {
        // Check for environment variable that controls mocking
        $useMock = $this->parameterBag->has('app.use_mock_data')
            ? $this->parameterBag->get('app.use_mock_data')
            : false;

        // Allow override via query parameter for testing
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->query->has('use_mock')) {
            $useMock = $request->query->get('use_mock') === 'true';
        }

        return !$useMock;
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
    protected function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
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
                $this->logger->warning(
                    'User has no roles assigned',
                    [
                        'portal' => $portal
                    ]
                );
            }

            $this->logger->info(
                'Authentication successful',
                [
                    'portal' => $portal,
                    'roles' => $userRoles
                ]
            );
        } catch (UnauthorizedHttpException $e) {
            $this->logger->error(
                'Authentication error',
                [
                    'error' => $e->getMessage()
                ]
            );
            throw $e;
        }

        // Log the request parameters
        $this->logger->info(
            'Processing request',
            [
                'operation' => $operation->getName(),
                'portal' => $portal,
                'userRoles' => $userRoles
            ]
        );

        // Get the DTO class name
        $dtoClass = $this->getDtoClass();

        // Get accessible fields and entity mappings
        $accessibleFields = $this->fieldAccessResolver->getAccessibleFields(
            $dtoClass,
            $userRoles,
            $portal
        );

        // Get entity mappings with accessible fields
        $entityMappings = $this->getEntityMappings($accessibleFields);

        // Fetch data from microservices if mappings are available, otherwise use sample data
        $items = !empty($entityMappings) ? $this->fetchFromMicroservices($entityMappings) : $this->getItems();

        // If it's a collection operation
        if ($operation->getName() === 'get_collection') {
            // Filter each item to only include accessible fields
            return array_map(
                function ($item) use ($accessibleFields) {
                    return $this->filterItemFields($item, $accessibleFields);
                },
                $items
            );
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
     * @param object $item             DTO object to filter
     * @param array  $accessibleFields List of accessible field names
     *
     * @return object|null Filtered DTO object or null if no fields are accessible
     */
    protected function filterItemFields(object $item, array $accessibleFields): ?object
    {
        // If no fields are accessible, return null (no access)
        if (empty($accessibleFields)) {
            $this->logger->warning(
                'No accessible fields for item',
                [
                    'itemId' => $item->id,
                    'accessibleFields' => $accessibleFields
                ]
            );
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
        $this->logger->info(
            'Filtered item',
            [
                'itemId' => $item->id,
                'accessibleFields' => $accessibleFields,
                'result' => json_encode($filteredItem)
            ]
        );

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

    /**
     * Get entity mappings with accessible fields.
     *
     * @param array $accessibleFields List of accessible field names
     *
     * @return array Array of EntityMappingDto objects
     */
    protected function getEntityMappings(array $accessibleFields): array
    {
        if (empty($accessibleFields)) {
            return [];
        }

        $dtoClass = $this->getDtoClass();
        $reflection = new ReflectionClass($dtoClass);
        $groupedFields = [];

        // Group fields by microservice and entity
        foreach ($accessibleFields as $field) {
            if (!$reflection->hasProperty($field)) {
                continue;
            }

            $property = $reflection->getProperty($field);

            // Check for MicroserviceRelationship attributes first
            $relationshipAttributes = $property->getAttributes(MicroserviceRelationship::class);
            $microserviceFields = [];

            if (!empty($relationshipAttributes)) {
                // Process fields from the relationship attribute
                $relationship = $relationshipAttributes[0]->newInstance();
                $microserviceFields = $relationship->getFields();
                $this->logger->info(
                    'Found MicroserviceRelationship attribute',
                    [
                        'property' => $field,
                        'fieldCount' => count($microserviceFields)
                    ]
                );
            } else {
                // Fall back to individual MicroserviceField attributes
                $microserviceFieldAttributes = $property->getAttributes(MicroserviceField::class);

                if (empty($microserviceFieldAttributes)) {
                    continue;
                }

                // Convert attribute instances to MicroserviceField objects
                foreach ($microserviceFieldAttributes as $attributeInstance) {
                    $microserviceFields[] = $attributeInstance->newInstance();
                }
            }

            // Process all microservice fields
            foreach ($microserviceFields as $microserviceField) {
                // Ensure we're working with a MicroserviceField object
                if (!($microserviceField instanceof MicroserviceField)) {
                    $this->logger->warning(
                        'Expected MicroserviceField object but got something else',
                        [
                            'type' => gettype($microserviceField)
                        ]
                    );
                    continue;
                }

                $microservice = $microserviceField->getMicroservice();
                $entity = $microserviceField->getEntity();
                $entityField = $microserviceField->getField();

                // Check if this field is not available in any context using the MicroserviceEntityMapping
                if (!MicroserviceEntityMapping::isFieldAvailable($microservice, $entity, $entityField)) {
                    $this->logger->warning(
                        'Field not available in any context',
                        [
                            'microservice' => $microservice,
                            'entity' => $entity,
                            'field' => $entityField
                        ]
                    );
                    continue;
                }

                // Group by microservice and entity
                $key = $microservice . ':' . $entity;
                if (!isset($groupedFields[$key])) {
                    $groupedFields[$key] = [];
                }

                // Store the entity field name, not the MicroserviceField object
                if (!in_array($entityField, $groupedFields[$key])) {
                    $groupedFields[$key][] = $entityField;
                }

                // Store field mapping
                if (!isset($fieldMap[$field])) {
                    $fieldMap[$field] = [];
                }
                $fieldMap[$field][] = [
                    'microservice' => $microservice,
                    'entity' => $entity,
                    'field' => $entityField
                ];
                continue;
            }
        }

        // Create EntityMappingDto objects for each microservice/entity combination
        $entityMappings = [];

        foreach ($groupedFields as $key => $fields) {
            list($microservice, $entity) = explode(':', $key);

            // No need for context map anymore as we use MicroserviceEntityMapping

            // Create field map for this entity
            $entityFieldMap = [];
            foreach ($fields as $entityField) {
                // Find the DTO property name that maps to this entity field
                foreach ($fieldMap as $property => $propertyMappings) {
                    foreach ($propertyMappings as $mapping) {
                        if (
                            $mapping['microservice'] === $microservice
                            && $mapping['entity'] === $entity
                            && $mapping['field'] === $entityField
                        ) {
                            $entityFieldMap[$property] = $entityField;
                            break 2;
                        }
                    }
                }
            }

            // Get the endpoint from the entity mapping
            $endpoint = MicroserviceEntityMapping::getEndpointForEntity($microservice, $entity);

            // Throw an exception if the endpoint is not defined in the mapping
            if (null === $endpoint) {
                throw new \RuntimeException(
                    sprintf(
                        'Missing endpoint configuration for entity "%s" in microservice "%s"',
                        $entity,
                        $microservice
                    )
                );
            }

            // Get entity fields from the field map for context determination
            $entityFields = array_values($entityFieldMap);

            // Determine the appropriate context for these fields
            $context = MicroserviceEntityMapping::determineContextForFields(
                $microservice,
                $entity,
                $entityFields
            );

            $entityMappings[] = new EntityMappingDto(
                $microservice,
                $entity,
                $endpoint,
                $fields,
                $entityFieldMap,
                $context
            );
        }

        return $entityMappings;
    }

    /**
     * Fetch data from microservices based on entity mappings.
     *
     * @param array $entityMappings Array of EntityMappingDto objects
     *
     * @return array Array of DTO objects
     */
    protected function fetchFromMicroservices(array $entityMappings): array
    {
        if (empty($entityMappings)) {
            return [];
        }

        $dtoClass = $this->getDtoClass();
        $results = [];
        $microserviceData = [];

        // Get the current request
        $request = $this->requestStack->getCurrentRequest();

        // Extract query parameters from the request to pass to microservices
        $queryParameters = [];
        foreach ($request->query->all() as $key => $value) {
            if ($key !== 'context') {
                $queryParameters[$key] = $value;
            }
        }

        //TODO: this is not that good needs to be improved we need to figure it out later
        // Get the ID from the request path if available
        $id = null;
        $pathInfo = $request->getPathInfo();
        $pathParts = explode('/', trim($pathInfo, '/'));
        $lastPart = end($pathParts);

        // Check if the last part is a numeric ID
        if (is_numeric($lastPart)) {
            $id = $lastPart;
        }

        // Fetch data from each microservice
        foreach ($entityMappings as $mapping) {
            if (!$mapping instanceof EntityMappingDto) {
                $this->logger->warning(
                    'Invalid entity mapping',
                    [
                        'mapping' => get_class($mapping)
                    ]
                );
                continue;
            }

            // Use either real or mock client based on configuration
            $data = $this->shouldUseRealData()
                ? $this->microserviceClient->fetchEntityData($mapping, $queryParameters, $id)
                : $this->mockClient->fetchEntityData($mapping, $queryParameters, $id);

            // Store the data with the entity name as key
            $microserviceData[$mapping->entity] = $data;
        }

        // If we have data from multiple microservices, merge them
        if (count($microserviceData) > 1) {
            $mergeKeys = $this->getMergeKeys();

            if (!empty($mergeKeys) && count($mergeKeys) >= 2) {
                // Get the first two entity names and their data
                $entityNames = array_keys($microserviceData);
                $entity1 = $entityNames[0];
                $entity2 = $entityNames[1];

                // Extract the key fields
                $keyField1 = $mergeKeys[0];
                $keyField2 = $mergeKeys[1];

                // Merge the data
                $mergedData = $this->mergeData(
                    $microserviceData[$entity1],
                    $microserviceData[$entity2],
                    $keyField1,
                    $keyField2
                );

                // Convert merged data to DTOs
                $results = $this->convertToDtos($mergedData, $dtoClass, $entityMappings);
            } else {
                $this->logger->error(
                    'Merge keys not properly defined',
                    [
                        'mergeKeys' => $mergeKeys
                    ]
                );
            }
        } elseif (count($microserviceData) === 1) {
            // If we only have data from one microservice, convert it directly
            $entityName = array_key_first($microserviceData);
            $data = $microserviceData[$entityName];

            // Convert data to DTOs
            $results = $this->convertToDtos($data, $dtoClass, $entityMappings);
        }

        return $results;
    }

    /**
     * Convert microservice data to DTO objects.
     *
     * @param array  $data           Data from microservices
     * @param string $dtoClass       DTO class name
     * @param array  $entityMappings Entity mappings
     *
     * @return array Array of DTO objects
     */
    protected function convertToDtos(array $data, string $dtoClass, array $entityMappings): array
    {
        $results = [];

        // Create a field mapping from all entity mappings
        $fieldMap = [];
        foreach ($entityMappings as $mapping) {
            foreach ($mapping->fieldMap as $dtoField => $entityField) {
                if (!isset($fieldMap[$dtoField])) {
                    $fieldMap[$dtoField] = [];
                }

                $fieldMap[$dtoField][] = [
                    'microservice' => $mapping->microservice,
                    'entity' => $mapping->entity,
                    'field' => $entityField
                ];
            }
        }

        // Convert each data item to a DTO
        foreach ($data as $item) {
            $dtoData = [];

            // Map fields from microservice response to DTO fields
            foreach ($fieldMap as $dtoField => $mappings) {
                foreach ($mappings as $mapping) {
                    $entityField = $mapping['field'];

                    // Check if this entity/field combination is in the current data item
                    if (isset($item[$entityField])) {
                        $dtoData[$dtoField] = $item[$entityField];
                        // Once we've found a value, we can stop looking for this DTO field
                        break;
                    }
                }
            }

            // Create a new DTO instance
            $dto = new $dtoClass();

            // Set the DTO properties
            $reflection = new ReflectionClass($dtoClass);
            foreach ($dtoData as $field => $value) {
                if ($reflection->hasProperty($field)) {
                    $property = $reflection->getProperty($field);
                    if ($property->isPublic()) {
                        $property->setValue($dto, $value);
                    }
                }
            }

            $results[] = $dto;
        }

        return $results;
    }

    /**
     * Get merge keys for joining data from multiple microservices.
     *
     * @return array Array of merge keys in the format ['entity1:field1', 'entity2:field2']
     */
    protected function getMergeKeys(): array
    {
        $dtoClass = $this->getDtoClass();
        $reflection = new ReflectionClass($dtoClass);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        // First, look for properties with MicroserviceRelationship attributes
        foreach ($properties as $property) {
            $relationshipAttributes = $property->getAttributes(MicroserviceRelationship::class);

            if (!empty($relationshipAttributes)) {
                $relationship = $relationshipAttributes[0]->newInstance();
                $fields = $relationship->getFields();

                if (count($fields) >= 2) {
                    $mergeKeys = [];

                    foreach ($fields as $field) {
                        $entity = $field->getEntity();
                        $fieldName = $field->getField();
                        $mergeKeys[] = $entity . ':' . $fieldName;
                    }

                    $this->logger->info(
                        'Found explicit relationship merge keys',
                        [
                            'property' => $property->getName(),
                            'mergeKeys' => $mergeKeys
                        ]
                    );
                    return $mergeKeys;
                }
            }
        }

        // Then, look for properties with multiple MicroserviceField attributes (legacy support)
        foreach ($properties as $property) {
            $microserviceFieldAttributes = $property->getAttributes(MicroserviceField::class);

            if (count($microserviceFieldAttributes) > 1) {
                // Found a property with multiple MicroserviceField attributes - explicit merge key
                $mergeKeys = [];

                foreach ($microserviceFieldAttributes as $attribute) {
                    $microserviceField = $attribute->newInstance();
                    $entity = $microserviceField->getEntity();
                    $field = $microserviceField->getField();
                    $mergeKeys[] = $entity . ':' . $field;
                }

                // If we have at least two keys, return them
                if (count($mergeKeys) >= 2) {
                    $this->logger->info(
                        'Found explicit merge keys from multiple attributes',
                        [
                            'property' => $property->getName(),
                            'mergeKeys' => $mergeKeys
                        ]
                    );
                    return $mergeKeys;
                }
            }
        }

        // If no explicit merge keys found, fall back to the heuristic approach
        // Look for properties with the MicroserviceField attribute that have matching field names
        $potentialKeys = [];
        $fieldToEntityMap = [];

        // First pass: collect all fields and their entity mappings
        foreach ($properties as $property) {
            $microserviceFieldAttributes = $property->getAttributes(MicroserviceField::class);

            if (empty($microserviceFieldAttributes)) {
                continue;
            }

            // Use the first attribute for the heuristic approach
            $microserviceField = $microserviceFieldAttributes[0]->newInstance();
            $microservice = $microserviceField->getMicroservice();
            $entity = $microserviceField->getEntity();
            $field = $microserviceField->getField();

            // Store mapping of field to entity and microservice
            $fieldToEntityMap[$field] = [
                'entity' => $entity,
                'microservice' => $microservice
            ];
        }

        // Second pass: look for fields that end with 'Id' and check if there's a matching field
        foreach ($properties as $property) {
            $microserviceFieldAttributes = $property->getAttributes(MicroserviceField::class);

            if (empty($microserviceFieldAttributes)) {
                continue;
            }

            // Use the first attribute for the heuristic approach
            $microserviceField = $microserviceFieldAttributes[0]->newInstance();
            $microservice = $microserviceField->getMicroservice();
            $entity = $microserviceField->getEntity();
            $field = $microserviceField->getField();

            // Check if this field ends with 'Id'
            if (substr($field, -2) === 'Id') {
                $baseField = substr($field, 0, -2);

                // Check if there's a matching field in another entity
                foreach ($fieldToEntityMap as $otherField => $otherEntityInfo) {
                    if (
                        $otherField === 'id'
                        && ($otherEntityInfo['entity'] !== $entity
                            || $otherEntityInfo['microservice'] !== $microservice)
                    ) {
                        // Found a potential match: this field is a foreign key to another entity
                        $potentialKeys[] = $entity . ':' . $field;
                        $potentialKeys[] = $otherEntityInfo['entity'] . ':' . $otherField;
                        break;
                    }
                }
            }
        }

        // If we found potential keys, return them
        if (count($potentialKeys) >= 2) {
            $this->logger->info(
                'Found potential merge keys using heuristic approach',
                [
                    'mergeKeys' => $potentialKeys
                ]
            );
            return $potentialKeys;
        }

        // Default implementation returns empty array
        // Override in concrete providers to define merge keys
        return [];
    }

    /**
     * Merge data from multiple microservices based on key fields.
     *
     * @param array  $data1     Data from first microservice
     * @param array  $data2     Data from second microservice
     * @param string $keyField1 Key field in first dataset
     * @param string $keyField2 Key field in second dataset
     *
     * @return array Merged data
     */
    protected function mergeData(
        array $data1,
        array $data2,
        string $keyField1,
        string $keyField2
    ): array {
        $result = [];

        // Extract the actual field names without entity prefix
        $field1 = explode(':', $keyField1)[1] ?? $keyField1;
        $field2 = explode(':', $keyField2)[1] ?? $keyField2;

        // Create a lookup map for the second dataset
        $data2Map = [];
        foreach ($data2 as $item) {
            if (isset($item[$field2])) {
                $data2Map[$item[$field2]] = $item;
            }
        }

        // Merge data based on the key fields
        foreach ($data1 as $item1) {
            if (!isset($item1[$field1])) {
                continue;
            }

            $key = $item1[$field1];

            if (isset($data2Map[$key])) {
                // Merge the two items
                $mergedItem = array_merge($item1, $data2Map[$key]);
                $result[] = $mergedItem;
            } else {
                // Include item1 even if no match in data2
                $result[] = $item1;
            }
        }

        return $result;
    }
}
