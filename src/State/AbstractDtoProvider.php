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

use ReflectionClass;
use ReflectionProperty;
use App\Service\JwtService;
use Psr\Log\LoggerInterface;
use App\Dto\EntityMappingDto;
use ApiPlatform\Metadata\Operation;
use App\Service\MicroserviceClient;
use App\Attribute\MicroserviceField;
use App\Service\FieldAccessResolver;
use App\Service\MockMicroserviceClient;
use ApiPlatform\State\ProviderInterface;
use App\Config\MicroserviceEntityMapping;
use App\Attribute\MicroserviceRelationship;
use Symfony\Component\HttpFoundation\RequestStack;
use ApiPlatform\Metadata\CollectionOperationInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

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
     * @logic:
     *  1) auth jwt get portal and userRoles
     *  2) get dto class
     *  3) get Action + get isCollection + get isItem
     *  4) get accessible fields
     *  5) get entity calls + get relationships
     *  6) fetch data from microservices (+ merge logic)
     *  7) filter out data + formatting
     *  8) return data
     *
     * @param Operation $operation    The operation
     * @param array     $uriVariables The URI variables
     * @param array     $context      The context
     *
     * @return object|array|null The data
     */
    protected function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // STEP 1: Authenticate

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
            $userRoles = $authData['userRoles'] ?? [];

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

        // STEP 2: Get Input Data
        // get operation action method (GET Collection, GET Item, POST, PUT, PATCH, DELETE)
        // if GET, we need to know if it's a collection or item
        // final input data: actionMethod, isCollection, userRoles, portal, dtoClass


        $operationMethod = $operation->getMethod();
        $isCollectionOperation = $operation instanceof CollectionOperationInterface;

        // Get the DTO class name
        $dtoClass = $this->getDtoClass();

        // Log the request parameters
        $this->logger->info(
            'Processing request',
            [
                'actionMethod' => $operationMethod,
                'isCollection' => $isCollectionOperation,
                'portal' => $portal,
                'userRoles' => $userRoles,
                'dtoClass' => $dtoClass
            ]
        );

        // Get accessible fields and entity mappings
        $accessibleFields = $this->fieldAccessResolver->getAccessibleFields(
            $dtoClass,
            $userRoles,
            $portal
        );

        // Log accessible fields
        $this->logger->info('Accessible fields: $accessibleFields', $accessibleFields);

        // Get entity mappings with accessible fields
        $entityMappings = $this->getEntityMappings($accessibleFields);

        // Log entity mappings
        $this->logger->info('Entity mappings $entityMappings', $entityMappings);

        // Check if we need multiple entities but have no relationships defined
        if (count($entityMappings) > 1) {
            $relationshipFields = $this->getRelationshipFields();

            // Log relationship fields
            $this->logger->info('Relationship fields $relationshipFields', $relationshipFields);

            // Validate entity relationships and optimize mappings
            $entityMappings = $this->validateAndOptimizeEntityMappings(
                $entityMappings,
                $accessibleFields,
                $relationshipFields
            );

            // Log optimized entity mappings
            $this->logger->info('Optimized entity mappings $entityMappings', $entityMappings);
        }

        // Fetch data from microservices if mappings are available, otherwise use sample data
        $items = !empty($entityMappings) ? $this->fetchFromMicroservices(
            $entityMappings,
            $isCollectionOperation,
            $operationMethod,
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
     * by looking for MicroserviceRelationship attributes. These relationships are used
     * to join data from different microservices when building composite DTOs.
     *
     * The returned array has a hierarchical structure:
     * [
     *     'propertyName' => [                      // e.g., 'reservation'
     *         'source' => [
     *             'entity' => 'sourceEntityName',  // e.g., 'Guest'
     *             'field'  => 'sourceFieldName'    // e.g., 'reservationId'
     *         ],
     *         'target' => [
     *             'entity' => 'targetEntityName',  // e.g., 'Reservation'
     *             'field'  => 'targetFieldName'    // e.g., 'id'
     *         ]
     *     ]
     * ]
     *
     * @return array Hierarchical array of relationship field definitions
     */
    protected function getRelationshipFields(): array
    {
        $relationships = [];
        $reflection = new ReflectionClass($this->getDtoClass());
        $properties = $reflection->getProperties();

        // Look for explicit MicroserviceRelationship attributes
        foreach ($properties as $property) {
            $relationshipAttributes = $property->getAttributes(MicroserviceRelationship::class);

            if (empty($relationshipAttributes)) {
                continue;
            }

            $relationshipAttribute = $relationshipAttributes[0]->newInstance();
            $relationshipFields = $relationshipAttribute->getFields();

            // A valid relationship requires at least two fields (source and target)
            if (count($relationshipFields) < 2) {
                $this->logger->warning(
                    'Invalid relationship attribute: requires at least two fields',
                    ['property' => $property->getName()]
                );
                continue;
            }

            // Extract source and target field information
            $sourceFieldObj = $relationshipFields[0];
            $targetFieldObj = $relationshipFields[1];
            $propertyName = $property->getName();

            // Create a hierarchical relationship definition
            $relationships[$propertyName] = [
                'source' => [
                    'entity' => $sourceFieldObj->getEntity(),
                    'field'  => $sourceFieldObj->getField()
                ],
                'target' => [
                    'entity' => $targetFieldObj->getEntity(),
                    'field'  => $targetFieldObj->getField()
                ]
            ];
        }

        return $relationships;
    }

    /**
     * Fetch data from microservices based on entity mappings.
     *
     * @param array   $entityMappings       Array of EntityMappingDto objects
     * @param bool    $isCollectionOperation Whether this is a collection operation
     * @param string  $operationName         The name of the operation
     * @param array   $uriVariables         The URI variables
     *
     * @return array Array of DTO objects
     */
    protected function fetchFromMicroservices(
        array $entityMappings,
        bool $isCollectionOperation,
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

        return [];
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
