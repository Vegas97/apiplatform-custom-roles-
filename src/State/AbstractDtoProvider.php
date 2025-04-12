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

        // Determine if this is a collection operation
        $operationName = $operation->getName();
        $isCollectionOperation = strpos($operationName, 'collection') !== false || $operationName === 'get_collection';
        $isItemOperation = !$isCollectionOperation;

        // Log the request parameters
        $this->logger->info(
            'Processing request',
            [
                'operation' => $operationName,
                'isCollection' => $isCollectionOperation,
                'isItemOperation' => $isItemOperation,
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

        // Check if we need multiple entities but have no relationships defined
        if (count($entityMappings) > 1) {
            $relationshipFields = $this->getRelationshipFields();

            // Validate entity relationships and optimize mappings
            $entityMappings = $this->validateAndOptimizeEntityMappings(
                $entityMappings,
                $accessibleFields,
                $relationshipFields
            );
        }

        // Fetch data from microservices if mappings are available, otherwise use sample data
        $items = !empty($entityMappings) ? $this->fetchFromMicroservices(
            $entityMappings,
            $isCollectionOperation,
            $isItemOperation,
            $operationName,
            $uriVariables
        ) : $this->getItems();

        // If it's a collection operation
        if ($isCollectionOperation) {
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

            // dump('------------------------------------------------');
            // dump($microservice, $entity, $entityFields, $context);

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
    /**
     * Get relationship fields between entities.
     *
     * This method analyzes the DTO class to find relationship fields between entities
     * by looking for MicroserviceRelationship attributes and fields that follow naming
     * conventions like foreign keys ending with 'Id'.
     *
     * @return array Array of relationship field definitions
     */
    protected function getRelationshipFields(): array
    {
        $relationships = [];
        $dtoClass = $this->getDtoClass();
        $reflection = new ReflectionClass($dtoClass);
        $properties = $reflection->getProperties();

        // First look for explicit MicroserviceRelationship attributes
        foreach ($properties as $property) {
            $relationshipAttributes = $property->getAttributes(MicroserviceRelationship::class);

            if (!empty($relationshipAttributes)) {
                $relationship = $relationshipAttributes[0]->newInstance();
                $fields = $relationship->getFields();

                if (count($fields) >= 2) {
                    // We have a relationship with at least two fields
                    $sourceField = $fields[0]->getField();
                    $targetField = $fields[1]->getField();
                    $sourceEntity = $fields[0]->getEntity();
                    $targetEntity = $fields[1]->getEntity();

                    $relationships[] = [
                        'sourceField' => $sourceField,
                        'targetField' => $targetField,
                        'sourceEntity' => $sourceEntity,
                        'relatedEntity' => $targetEntity,
                        'propertyName' => $property->getName()
                    ];
                }
            }
        }

        // If no explicit relationships found, look for fields with naming conventions
        if (empty($relationships)) {
            // Map to track fields that could be foreign keys
            $foreignKeyFields = [];
            $entityFields = [];

            // First pass: identify potential foreign keys and entity fields
            foreach ($properties as $property) {
                $microserviceFieldAttributes = $property->getAttributes(MicroserviceField::class);

                if (empty($microserviceFieldAttributes)) {
                    continue;
                }

                $microserviceField = $microserviceFieldAttributes[0]->newInstance();
                $entity = $microserviceField->getEntity();
                $field = $microserviceField->getField();

                // Store all fields with their entities
                $entityFields[$property->getName()] = [
                    'entity' => $entity,
                    'field' => $field
                ];

                // Check if this field ends with 'Id' - potential foreign key
                if (substr($field, -2) === 'Id' || substr($property->getName(), -2) === 'Id') {
                    $foreignKeyFields[$property->getName()] = [
                        'entity' => $entity,
                        'field' => $field
                    ];
                }
            }

            // Second pass: match foreign keys with their target entities
            foreach ($foreignKeyFields as $propertyName => $foreignKeyInfo) {
                $sourceEntity = $foreignKeyInfo['entity'];
                $sourceField = $foreignKeyInfo['field'];

                // Try to find the target entity by removing 'Id' from the field name
                $baseFieldName = substr($propertyName, 0, -2);

                // Look for a property that matches the base name
                foreach ($entityFields as $targetPropertyName => $targetInfo) {
                    // Skip if it's the same entity
                    if ($targetInfo['entity'] === $sourceEntity) {
                        continue;
                    }

                    // Check if the property name matches our base field name pattern
                    if (
                        $targetPropertyName === $baseFieldName ||
                        strtolower($targetPropertyName) === strtolower($baseFieldName)
                    ) {
                        $relationships[] = [
                            'sourceField' => $sourceField,
                            'targetField' => 'id', // Assume the target field is 'id'
                            'sourceEntity' => $sourceEntity,
                            'relatedEntity' => $targetInfo['entity'],
                            'propertyName' => $propertyName
                        ];
                        break;
                    }
                }
            }
        }

        return $relationships;
    }

    /**
     * Merge data from multiple microservice sources.
     *
     * @param array $microserviceData   Data from multiple microservices
     * @param array $relationshipFields Relationship field definitions
     *
     * @return array Merged data
     */
    protected function mergeDataFromMultipleSources(array $microserviceData, array $relationshipFields): array
    {
        // If we have explicit relationship fields, use them for merging
        if (!empty($relationshipFields)) {
            $primaryEntity = array_key_first($microserviceData);
            $result = $microserviceData[$primaryEntity];

            // Create a dependency graph of entities
            $entityGraph = $this->buildEntityRelationshipGraph($relationshipFields);

            // Track which entities have been merged already
            $mergedEntities = [$primaryEntity => true];
            $pendingEntities = $this->findRelatedEntities($primaryEntity, $entityGraph);

            // Continue merging until we've processed all related entities
            $iterationCount = 0;
            $maxIterations = count($microserviceData) * 2; // Safety limit to prevent infinite loops

            while (!empty($pendingEntities) && $iterationCount < $maxIterations) {
                $iterationCount++;
                $nextEntity = array_shift($pendingEntities);

                // Skip if already merged or no data available
                if (isset($mergedEntities[$nextEntity]) || !isset($microserviceData[$nextEntity])) {
                    continue;
                }

                // Find relationships between already merged entities and this entity
                $relationshipsToMerge = [];
                foreach ($relationshipFields as $relationship) {
                    $sourceEntity = $relationship['sourceEntity'];
                    $relatedEntity = $relationship['relatedEntity'];

                    // Check if this relationship connects our already merged entities with the next entity
                    if ((isset($mergedEntities[$sourceEntity]) && $relatedEntity === $nextEntity) ||
                        (isset($mergedEntities[$relatedEntity]) && $sourceEntity === $nextEntity)
                    ) {
                        $relationshipsToMerge[] = $relationship;
                    }
                }

                // Merge this entity's data using the identified relationships
                foreach ($relationshipsToMerge as $relationship) {
                    $sourceEntity = $relationship['sourceEntity'];
                    $relatedEntity = $relationship['relatedEntity'];
                    $sourceField = $relationship['sourceField'];
                    $targetField = $relationship['targetField'];

                    // Determine which entity is already merged and which is being added
                    if (isset($mergedEntities[$sourceEntity])) {
                        $existingData = $result;
                        $newEntityData = $microserviceData[$relatedEntity];
                        $lookupField = $sourceField;
                        $matchField = $targetField;
                    } else {
                        $existingData = $result;
                        $newEntityData = $microserviceData[$sourceEntity];
                        $lookupField = $targetField;
                        $matchField = $sourceField;
                    }

                    // Create a lookup map for the new entity data
                    $newEntityMap = [];
                    foreach ($newEntityData as $item) {
                        if (isset($item[$matchField])) {
                            $newEntityMap[$item[$matchField]] = $item;
                        }
                    }

                    // Merge the new entity data into the result
                    foreach ($result as $key => $item) {
                        if (isset($item[$lookupField]) && isset($newEntityMap[$item[$lookupField]])) {
                            $relatedItem = $newEntityMap[$item[$lookupField]];
                            foreach ($relatedItem as $field => $value) {
                                // Avoid overwriting existing fields
                                if (!isset($item[$field])) {
                                    $result[$key][$field] = $value;
                                }
                            }
                        }
                    }
                }

                // Mark this entity as merged
                $mergedEntities[$nextEntity] = true;

                // Add any new related entities to the pending list
                $newRelatedEntities = $this->findRelatedEntities($nextEntity, $entityGraph);
                foreach ($newRelatedEntities as $entity) {
                    if (!isset($mergedEntities[$entity]) && !in_array($entity, $pendingEntities)) {
                        $pendingEntities[] = $entity;
                    }
                }

                $this->logger->debug(
                    'Entity merge progress',
                    [
                        'iteration' => $iterationCount,
                        'merged' => array_keys($mergedEntities),
                        'pending' => $pendingEntities
                    ]
                );
            }

            if ($iterationCount >= $maxIterations) {
                $this->logger->warning(
                    'Reached maximum iterations when merging entities',
                    [
                        'mergedEntities' => array_keys($mergedEntities),
                        'pendingEntities' => $pendingEntities
                    ]
                );
            }

            return $result;
        }

        // Fall back to the old merge keys approach if no relationship fields
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
            return $this->mergeData(
                $microserviceData[$entity1],
                $microserviceData[$entity2],
                $keyField1,
                $keyField2
            );
        } else {
            $this->logger->error(
                'No relationship fields or merge keys defined for merging data',
                [
                    'entities' => array_keys($microserviceData)
                ]
            );

            // Return the primary data if we can't merge
            return $microserviceData[array_key_first($microserviceData)] ?? [];
        }
    }

    /**
     * Build a graph of entity relationships.
     *
     * @param array $relationshipFields Array of relationship field definitions
     *
     * @return array Entity relationship graph
     */
    private function buildEntityRelationshipGraph(array $relationshipFields): array
    {
        $graph = [];

        foreach ($relationshipFields as $relationship) {
            $sourceEntity = $relationship['sourceEntity'];
            $relatedEntity = $relationship['relatedEntity'];

            // Add bidirectional relationship
            if (!isset($graph[$sourceEntity])) {
                $graph[$sourceEntity] = [];
            }
            if (!isset($graph[$relatedEntity])) {
                $graph[$relatedEntity] = [];
            }

            if (!in_array($relatedEntity, $graph[$sourceEntity])) {
                $graph[$sourceEntity][] = $relatedEntity;
            }
            if (!in_array($sourceEntity, $graph[$relatedEntity])) {
                $graph[$relatedEntity][] = $sourceEntity;
            }
        }

        return $graph;
    }

    /**
     * Find all entities related to a given entity.
     *
     * @param string $entity Entity name
     * @param array  $graph  Entity relationship graph
     *
     * @return array Related entity names
     */
    private function findRelatedEntities(string $entity, array $graph): array
    {
        return $graph[$entity] ?? [];
    }

    /**
     * Fetch data from microservices based on entity mappings.
     *
     * @param array   $entityMappings       Array of EntityMappingDto objects
     * @param bool    $isCollectionOperation Whether this is a collection operation
     * @param bool    $isItemOperation       Whether this is an item operation
     * @param string  $operationName         The name of the operation
     * @param array   $uriVariables         The URI variables
     *
     * @return array Array of DTO objects
     */
    protected function fetchFromMicroservices(
        array $entityMappings,
        bool $isCollectionOperation,
        bool $isItemOperation,
        string $operationName,
        array $uriVariables = []
    ): array {
        if (empty($entityMappings)) {
            return [];
        }

        $dtoClass = $this->getDtoClass();
        $results = [];
        $microserviceData = [];
        $relationshipFields = $this->getRelationshipFields();

        // Get the current request
        $request = $this->requestStack->getCurrentRequest();

        // Extract query parameters from the request to pass to microservices
        $queryParameters = [];
        foreach ($request->query->all() as $key => $value) {
            if ($key !== 'context') {
                $queryParameters[$key] = $value;
            }
        }

        // Get the ID from URI variables if this is an item operation
        $id = $isItemOperation && !empty($uriVariables['id']) ? $uriVariables['id'] : null;

        // Enhanced logging for operation details
        $this->logger->info(
            '[AbstractDtoProvider] Operation details',
            [
                'operationType' => $operationName,
                'isCollectionOperation' => $isCollectionOperation,
                'isItemOperation' => $isItemOperation,
                'id' => $id,
                'dtoClass' => $dtoClass,
                'entityMappingsCount' => count($entityMappings),
                'relationshipFieldsCount' => count($relationshipFields)
            ]
        );

        // Log the entity mappings for debugging
        $mappingSummary = [];
        foreach ($entityMappings as $key => $mapping) {
            if ($mapping instanceof EntityMappingDto) {
                $mappingSummary[$key] = [
                    'microservice' => $mapping->microservice,
                    'entity' => $mapping->entity,
                    'endpoint' => $mapping->endpoint
                ];
            }
        }
        $this->logger->debug('[AbstractDtoProvider] Entity mappings', ['mappings' => $mappingSummary]);

        // Create a working copy of entity mappings to preserve the original for later use
        $workingEntityMappings = $entityMappings;

        // Fetch data from primary microservice first
        $primaryMapping = reset($workingEntityMappings);
        $primaryKey = key($workingEntityMappings);

        if (!$primaryMapping instanceof EntityMappingDto) {
            $this->logger->warning(
                'Invalid primary entity mapping',
                [
                    'mapping' => get_class($primaryMapping)
                ]
            );
            return [];
        }

        // Log which client we're using
        $useRealData = $this->shouldUseRealData();
        $this->logger->info(
            '[AbstractDtoProvider] Fetching primary data',
            [
                'useRealData' => $useRealData,
                'microservice' => $primaryMapping->microservice,
                'entity' => $primaryMapping->entity,
                'endpoint' => $primaryMapping->endpoint,
                'id' => $id
            ]
        );

        // Use either real or mock client based on configuration
        $primaryData = $useRealData
            ? $this->microserviceClient->fetchEntityData($primaryMapping, $queryParameters, $id)
            : $this->mockClient->fetchEntityData($primaryMapping, $queryParameters, $id);

        // Log the result of the primary data fetch
        $this->logger->info(
            '[AbstractDtoProvider] Primary data fetched',
            [
                'dataCount' => is_array($primaryData) ? count($primaryData) : 'not an array',
                'dataType' => gettype($primaryData)
            ]
        );

        // Store the data with the entity name as key
        $microserviceData[$primaryMapping->entity] = $primaryData;

        // Remove the primary mapping from the working copy to avoid fetching it again
        unset($workingEntityMappings[$primaryKey]);

        // Process remaining mappings based on whether we have relationship fields and primary data
        if (!empty($relationshipFields) && !empty($primaryData) && !empty($workingEntityMappings)) {
            // Process related entities using relationship fields
            $this->logger->info('Processing related entities using relationship fields');

            // Create a map of entity names to their mappings for faster lookup
            $entityMappingsByName = [];
            foreach ($workingEntityMappings as $mapping) {
                if ($mapping instanceof EntityMappingDto) {
                    $entityMappingsByName[$mapping->entity] = $mapping;
                }
            }

            // Process each relationship field
            foreach ($relationshipFields as $relationField) {
                $relatedEntityName = $relationField['relatedEntity'];

                // Skip if we don't have a mapping for this entity
                if (!isset($entityMappingsByName[$relatedEntityName])) {
                    $this->logger->warning(
                        'No mapping found for related entity',
                        [
                            'relatedEntity' => $relatedEntityName,
                            'availableEntities' => array_keys($entityMappingsByName)
                        ]
                    );
                    continue;
                }

                $relatedMapping = $entityMappingsByName[$relatedEntityName];
                $sourceField = $relationField['sourceField'];

                // Extract IDs from primary data for the relationship field
                $relatedIds = [];
                foreach ($primaryData as $item) {
                    if (isset($item[$sourceField]) && !empty($item[$sourceField])) {
                        $relatedIds[] = $item[$sourceField];
                    }
                }

                // Skip if no related IDs found
                if (empty($relatedIds)) {
                    $this->logger->info(
                        'No related IDs found for relationship',
                        [
                            'sourceField' => $sourceField,
                            'relatedEntity' => $relatedEntityName,
                            'primaryDataCount' => count($primaryData)
                        ]
                    );
                    continue;
                }

                // Add the IDs as a filter parameter
                $relatedQueryParams = $queryParameters;
                $relatedQueryParams['ids'] = implode(',', array_unique($relatedIds));

                // Fetch related data
                $relatedData = $this->shouldUseRealData()
                    ? $this->microserviceClient->fetchEntityData($relatedMapping, $relatedQueryParams, null)
                    : $this->mockClient->fetchEntityData($relatedMapping, $relatedQueryParams, null);

                // Store the data with the entity name as key
                $microserviceData[$relatedMapping->entity] = $relatedData;

                // Remove this entity from the mappings as we've processed it
                unset($entityMappingsByName[$relatedEntityName]);
            }

            // Process any remaining entity mappings that weren't handled by relationships
            foreach ($entityMappingsByName as $entityName => $mapping) {
                $this->logger->info(
                    'Processing remaining entity mapping not handled by relationships',
                    ['entity' => $entityName]
                );

                $data = $this->shouldUseRealData()
                    ? $this->microserviceClient->fetchEntityData($mapping, $queryParameters, $id)
                    : $this->mockClient->fetchEntityData($mapping, $queryParameters, $id);

                $microserviceData[$mapping->entity] = $data;
            }
        } else {
            // Process remaining mappings without relationship context
            if (!empty($workingEntityMappings)) {
                $this->logger->info('Processing entity mappings without relationship context', [
                    'hasPrimaryData' => !empty($primaryData),
                    'hasRelationshipFields' => !empty($relationshipFields),
                    'remainingMappings' => count($workingEntityMappings)
                ]);

                foreach ($workingEntityMappings as $mapping) {
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
            }
        }

        // If we have data from multiple microservices, merge them
        if (count($microserviceData) > 1) {
            $mergedData = $this->mergeDataFromMultipleSources($microserviceData, $relationshipFields);
            // Convert merged data to DTOs - use original entityMappings for complete field mapping
            $results = $this->convertToDtos($mergedData, $dtoClass, $entityMappings);
        } elseif (count($microserviceData) === 1) {
            // If we only have data from one microservice, we still need to ensure proper structure
            $entityName = array_key_first($microserviceData);
            $data = $microserviceData[$entityName];

            // For item operations, ensure data is in the expected format
            if ($isItemOperation && !empty($data) && !isset($data[0]) && is_array($data)) {
                // If it's a single item, wrap it in an array to match the expected format
                $data = [$data];
                $this->logger->info('Wrapped single item in array for DTO conversion');
            }

            // For collection operations, the data structure might be different
            if ($isCollectionOperation && !empty($data)) {
                // Check if this is a hydra collection format
                if (isset($data['hydra:member']) && is_array($data['hydra:member'])) {
                    $this->logger->info('Processing hydra:member from collection data', [
                        'memberCount' => count($data['hydra:member'])
                    ]);
                    $data = $data['hydra:member'];
                }
            }

            // Convert data to DTOs - use original entityMappings for complete field mapping
            $results = $this->convertToDtos($data, $dtoClass, $entityMappings, $isCollectionOperation);
        }

        $this->logger->info('[AbstractDtoProvider] Completed microservice data fetching and DTO conversion', [
            'resultCount' => count($results),
            'microserviceCount' => count($microserviceData),
            'dtoClass' => $dtoClass,
            'isCollectionOperation' => $isCollectionOperation,
            'isItemOperation' => $isItemOperation,
            'hasRelationshipFields' => !empty($relationshipFields)
        ]);

        return $results;
    }

    /**
     * Convert microservice data to DTO objects.
     *
     * @param array  $data           Data from microservices
     * @param string $dtoClass       DTO class name
     * @param array  $entityMappings Entity mappings
     * @param bool   $isCollectionOperation Whether this is a collection operation
     *
     * @return array Array of DTO objects
     */
    protected function convertToDtos(
        array $data,
        string $dtoClass,
        array $entityMappings,
        bool $isCollectionOperation = false
    ): array {
        // Log the data structure for debugging
        $this->logger->info('[AbstractDtoProvider] Starting DTO conversion', [
            'dataCount' => count($data),
            'dtoClass' => $dtoClass,
            'entityMappingsCount' => count($entityMappings),
            'isCollectionOperation' => $isCollectionOperation,
            'dataStructure' => $this->getDataStructureInfo($data)
        ]);

        // Handle hydra:member collection format if present
        if ($isCollectionOperation && isset($data['hydra:member']) && is_array($data['hydra:member'])) {
            $this->logger->info('[AbstractDtoProvider] Processing hydra:member collection', [
                'memberCount' => count($data['hydra:member'])
            ]);
            $data = $data['hydra:member'];
        }

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

        // For collection operations, ensure we're processing the correct data structure
        if ($isCollectionOperation && isset($data['hydra:member']) && is_array($data['hydra:member'])) {
            $this->logger->info('Processing hydra:member collection data', [
                'memberCount' => count($data['hydra:member'])
            ]);
            $data = $data['hydra:member'];
        }

        // Convert each data item to a DTO
        foreach ($data as $item) {
            // Skip if item is not an array (can't process it)
            if (!is_array($item)) {
                $this->logger->warning('Skipping non-array item in data', [
                    'itemType' => gettype($item)
                ]);
                continue;
            }

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

            // Ensure ID is set - if we have an 'id' field in the item, make sure it's in the DTO data
            if (isset($item['id']) && !empty($item['id']) && (!isset($dtoData['id']) || empty($dtoData['id']))) {
                $dtoData['id'] = $item['id'];
                $this->logger->debug('Setting ID from source data', ['id' => $item['id']]);
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

        // Log the final conversion results
        $this->logger->info('[AbstractDtoProvider] DTO conversion completed', [
            'resultCount' => count($results),
            'dtoClass' => $dtoClass
        ]);

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

    /**
     * Validates entity relationships and throws an error if multiple entities are required
     * but no relationships are defined. Then optimizes entity mappings to only include
     * entities that actually need to be fetched.
     *
     * @param array $entityMappings     The original entity mappings
     * @param array $accessibleFields   The accessible fields for the current request
     * @param array $relationshipFields The relationship fields between entities
     * 
     * @return array The validated and optimized entity mappings
     * @throws \LogicException If multiple entities are required but no relationships are defined
     */
    protected function validateAndOptimizeEntityMappings(
        array $entityMappings,
        array $accessibleFields,
        array $relationshipFields
    ): array {
        // Check if we actually need to fetch from all entities or just one
        $requiredEntities = [];
        foreach ($entityMappings as $mapping) {
            $requiredEntities[$mapping->getEntity()] = true;
        }

        // If we need multiple entities but have no relationships, throw an error
        if (count($requiredEntities) > 1 && empty($relationshipFields)) {
            $this->logger->error(
                'Multiple entities required but no relationships defined',
                [
                    'entities' => array_keys($requiredEntities),
                    'dtoClass' => $this->getDtoClass()
                ]
            );

            throw new \LogicException(
                'Multiple entities required but no relationships defined. ' .
                    'Please define relationships between ' . implode(', ', array_keys($requiredEntities))
            );
        }

        // Optimize entity mappings to only include entities we actually need to fetch
        return $this->optimizeEntityMappings($entityMappings, $accessibleFields, $relationshipFields);
    }

    /**
     * Optimizes entity mappings to only include entities that actually need to be fetched.
     * 
     * This handles the case where we have fields from multiple entities in the DTO,
     * but we don't actually need to fetch all of those entities. For example, if we have
     * guest.id and guest.reservationId, we only need to fetch the guest entity, not the
     * reservation entity.
     *
     * @param array $entityMappings     The original entity mappings
     * @param array $accessibleFields   The accessible fields for the current request
     * @param array $relationshipFields The relationship fields between entities
     * 
     * @return array The optimized entity mappings
     */
    protected function optimizeEntityMappings(
        array $entityMappings,
        array $accessibleFields,
        array $relationshipFields
    ): array {
        if (empty($entityMappings) || count($entityMappings) <= 1) {
            return $entityMappings;
        }

        // Map each entity to the fields it provides
        $entityFields = [];
        $fieldToEntity = [];

        foreach ($entityMappings as $mapping) {
            $entityName = $mapping->getEntity();
            $entityFields[$entityName] = [];

            // Get all fields from this entity
            $reflectionClass = new \ReflectionClass($this->getDtoClass());
            foreach ($reflectionClass->getProperties() as $property) {
                foreach ($property->getAttributes(MicroserviceField::class) as $attribute) {
                    $microserviceField = $attribute->newInstance();

                    if ($microserviceField->getEntity() === $entityName) {
                        $fieldName = $property->getName();
                        $entityFields[$entityName][] = $fieldName;
                        $fieldToEntity[$fieldName] = $entityName;
                    }
                }
            }
        }

        // Get all fields that are actually being requested in this call
        $requestedFields = [];
        foreach ($accessibleFields as $field) {
            $requestedFields[$field] = true;
        }

        // Check which entities are actually needed based on the requested fields
        $requiredEntities = [];
        foreach ($requestedFields as $field => $value) {
            if (isset($fieldToEntity[$field])) {
                $requiredEntities[$fieldToEntity[$field]] = true;
            }
        }

        $this->logger->info(
            'Required entities based on requested fields',
            [
                'requiredEntities' => array_keys($requiredEntities),
                'requestedFields' => array_keys($requestedFields),
                'hasRelationships' => !empty($relationshipFields)
            ]
        );

        // If we only need one entity, optimize the mappings
        if (count($requiredEntities) === 1) {
            $requiredEntity = array_key_first($requiredEntities);

            $this->logger->info(
                'Optimizing entity mappings to a single entity',
                [
                    'requiredEntity' => $requiredEntity
                ]
            );

            // Filter mappings to only include the required entity
            return array_filter($entityMappings, function ($mapping) use ($requiredEntity) {
                return $mapping->getEntity() === $requiredEntity;
            });
        }

        // If we need multiple entities, return all mappings to ensure we have all the data we need
        return $entityMappings;
    }

    /**
     * Analyze data structure for logging purposes.
     *
     * @param array $data The data to analyze
     *
     * @return array Information about the data structure
     */
    protected function getDataStructureInfo(array $data): array
    {
        $info = [
            'type' => 'array',
            'count' => count($data),
            'keys' => []
        ];

        // Get the first few keys for debugging
        $keys = array_keys($data);
        $info['keys'] = array_slice($keys, 0, min(5, count($keys)));

        // Check if this might be a hydra collection
        if (isset($data['hydra:member']) && is_array($data['hydra:member'])) {
            $info['hasHydraMember'] = true;
            $info['memberCount'] = count($data['hydra:member']);
        } else {
            $info['hasHydraMember'] = false;
        }

        // Check the first item if it's an array of items
        if (!empty($data) && isset($data[0]) && is_array($data[0])) {
            $firstItem = $data[0];
            $info['firstItemKeys'] = array_slice(array_keys($firstItem), 0, min(5, count($firstItem)));
        }

        return $info;
    }
}
