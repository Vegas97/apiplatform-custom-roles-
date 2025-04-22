<?php

/**
 * Abstract DTO Provider for API Platform.
 *
 * Process flow:
 * 1- Get operation/request Data
 * 2- get validate and Get auth data (portal, userRoles)
 * 3- get accessible fields - what are the allowed fields of the current dto called 
 * 4- get the entityMapping (that we need to rename in entityMappingCalls) is basically an array of calls that we need to do, each of them has endpoint url to call, context param, ...
 * 5- get relationships 
 *
 * 6- finally we do the calls o fetch.
 *
 * 7- format the response to double check that we return only the fields allowed
 *
 * 8- return
 *
 * ----
 *
 * for step 6 we go in detials.
 *
 * 1- we do the main call (the first element in entityMappingCalls
 * 2- we now check if we have more than 0 relationships, if yes contunue otherwise return
 * 3- loop throught the realtionships, let's go inside
 * 4- the get current realtionship data.
 * 5- check if we are in a colleciton or single item, based on that we need to get from the mainCallFetchedData the ids of all items if is a collection, or just 1 id if is a single item.
 * 6- make a new call folowoitng the current realtionship data, that is telling which is the entity to call  and which param (ids, id, ..) to pass.
 * 7- now the loop restart. (keep in mind that when reach the logic it's alays the current realtionship that tell us from which data to get the params for the next call)
 *
 * 8- we finish all the fetches, we need to merge them with logic
 * 9- new loop following again the realtionships, we go inside
 * 10- grab the current realtionship info, pick the fetched data and merge to the second one specified, by the ids specified.
 * 11- continue the loop
 *
 * 12- now we need to format the response.
 * 13- return
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
     * Primary entity microservice name.
     *
     * @var string|null
     */
    protected ?string $primaryEntityMicroservice = null;

    /**
     * Primary entity name.
     *
     * @var string|null
     */
    protected ?string $primaryEntityName = null;

    /**
     * Primary identifier field name in the entity.
     *
     * @var string|null
     */
    protected ?string $primaryIdentifierField = null;

    /**
     * Primary identifier property name in the DTO.
     *
     * @var string|null
     */
    protected ?string $primaryIdentifierPropertyName = null;

    /**
     * URI variables from the current request.
     *
     * @var array
     */
    protected array $uriVariables = [];

    /**
     * Whether the current operation is a collection operation.
     *
     * @var bool
     */
    protected bool $isCollectionOperation = false;

    /**
     * The current operation method (GET, POST, etc.).
     *
     * @var string
     */
    protected string $operationMethod = '';

    /**
     * Relationship fields between entities.
     *
     * @var array
     */
    protected array $relationshipFields = [];

    /**
     * Accessible fields for the current request.
     *
     * @var array
     */
    protected array $accessibleFields = [];

    /**
     * Entity mappings for the current request.
     *
     * @var array
     */
    protected array $entityMappings = [];

    /**
     * User roles for the current request.
     *
     * @var array
     */
    protected array $userRoles = [];

    /**
     * Portal for the current request.
     *
     * @var string|null
     */
    protected ?string $portal = null;

    /**
     * Operation context from the current request.
     *
     * @var array
     */
    protected array $operationContext = [];

    /**
     * DTO class name.
     *
     * @var string
     */
    protected string $dtoClass = '';

    /**
     * Whether to use mock data.
     *
     * @var bool
     */
    protected bool $useMockData = false;

    /**
     * ID for single item requests.
     *
     * @var string|int|null
     */
    protected string|int|null $id;

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
    protected function shouldUseMockData(): bool
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

        return !!$useMock;
    }

    /**
     * Provides DTO objects for API Platform with role-based access control.
     * 
     * @logic:
     *  1) auth jwt get portal and userRoles
     *  2) get dto class
     *  3) get HTTP method + get isCollection + get isItem
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
        // authenticate the request and get the portal and user roles
        try {
            // Store URI variables and context at instance level
            $this->uriVariables = $uriVariables;
            $this->operationContext = $context;
            $this->dtoClass = $this->getDtoClass();
            $this->useMockData = $this->shouldUseMockData();
            $this->operationMethod = $operation->getMethod();
            $this->isCollectionOperation = $operation instanceof CollectionOperationInterface;

            // get the ID (uriVariables are not query parameters)
            $this->id = $uriVariables['id'] ?? null;

            if (!$this->isCollectionOperation && $this->id === null) {
                throw new \RuntimeException('ID is required for item operations');
            }

            // Get JWT token from request
            $token = $this->jwtService->getTokenFromRequest($this->requestStack->getCurrentRequest());

            // Validate token and extract claims
            $claims = $this->jwtService->validateToken($token);

            // Extract portal and roles from claims
            $this->portal = $claims['portal'];
            $this->userRoles = $claims['userRoles'];

            $this->logger->info(
                'Authentication successful',
                [
                    'portal' => $this->portal,
                    'roles' => $this->userRoles
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
                'actionMethod' => $this->operationMethod,
                'isCollection' => $this->isCollectionOperation,
                'portal' => $this->portal,
                'userRoles' => $this->userRoles,
                'dtoClass' => $this->dtoClass
            ]
        );

        // Get accessible fields and entity mappings
        $this->accessibleFields = $this->fieldAccessResolver->getAccessibleFields(
            $this->dtoClass,
            $this->userRoles,
            $this->portal
        );

        // Get entity mappings and relationship fields, then optimize them if needed
        list($this->entityMappings, $this->relationshipFields) = $this->getEntityMappings();

        // Fetch data from microservices if mappings are available
        if (!empty($this->entityMappings)) {
            $items = $this->fetchFromMicroservices();
        } else {
            throw new \RuntimeException('No entity mappings found');
        }

        // filter  $this->filterItemFields( ....)

        return $items;
    }

    /**
     * Filter item fields based on accessible fields.
     *
     * @param object $item             DTO object to filter
     * @param array  $accessibleFields List of accessible field names
     *
     * @return object|null Filtered DTO object or null if no fields are accessible
     */
    protected function filterItemFields(object $item): ?object
    {
        // If no fields are accessible, return null (no access)
        if (empty($this->accessibleFields)) {
            $this->logger->warning(
                'No accessible fields for item',
                [
                    'itemId' => $item->id,
                    'accessibleFields' => $this->accessibleFields
                ]
            );
            return null;
        }

        $dtoClass = $this->getDtoClass();

        // Create a new DTO instance with only the accessible fields
        $filteredItem = new $dtoClass();

        // Use reflection to set only the accessible properties
        $reflection = new ReflectionClass($dtoClass);

        foreach ($this->accessibleFields as $field) {
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
                'accessibleFields' => $this->accessibleFields,
                'result' => json_encode($filteredItem)
            ]
        );

        return $filteredItem;
    }

    /**
     * Validates the consistency between entity mappings and relationships.
     * 
     * This method ensures that the number of relationships is consistent with the number of entity mappings:
     * - If there's only one entity mapping, there should be no relationships
     * - If there are N entity mappings, there should be N-1 relationships
     *
     * @param array $entityMappings    The entity mappings to validate
     * @param array $relationshipFields The relationship fields to validate
     * 
     * @return void
     * @throws \RuntimeException If the validation fails
     */
    protected function validateEntityMappingsAndRelationships(array $entityMappings, array $relationshipFields): void
    {
        $entityCount = count($entityMappings);
        $relationshipCount = count($relationshipFields);

        // If there's only one entity mapping, there should be no relationships
        if ($entityCount === 1 && $relationshipCount > 0) {
            $this->logger->warning(
                'Validation failed: Single entity mapping should have no relationships',
                [
                    'entityCount' => $entityCount,
                    'relationshipCount' => $relationshipCount,
                    'entity' => $entityMappings[0]->getEntity()
                ]
            );
            throw new \RuntimeException(
                'Invalid relationship configuration: Single entity mapping should have no relationships'
            );
        }

        // If there are N entity mappings, there should be N-1 relationships
        if ($entityCount > 1 && $relationshipCount !== ($entityCount - 1)) {
            $this->logger->warning(
                'Validation failed: Number of relationships should be one less than number of entity mappings',
                [
                    'entityCount' => $entityCount,
                    'relationshipCount' => $relationshipCount,
                    'expectedRelationshipCount' => $entityCount - 1,
                    'entities' => array_map(function ($mapping) {
                        return $mapping->getEntity();
                    }, $entityMappings)
                ]
            );
            throw new \RuntimeException(
                sprintf(
                    'Invalid relationship configuration: Expected %d relationships for %d entity mappings, got %d',
                    $entityCount - 1,
                    $entityCount,
                    $relationshipCount
                )
            );
        }

        $this->logger->info(
            'Entity mappings and relationships validation passed',
            [
                'entityCount' => $entityCount,
                'relationshipCount' => $relationshipCount
            ]
        );
    }

    /**
     * Get entity mappings with accessible fields and relationship fields.
     *
     * @return array An array containing two elements:
     *               - The first element is an array of EntityMappingDto objects
     *               - The second element is an array of relationship fields or null if none exist
     */
    protected function getEntityMappings(): array
    {
        if (empty($this->accessibleFields)) {
            return [[], []];
        }

        $reflection = new ReflectionClass($this->dtoClass);
        $groupedFields = [];
        $fieldMap = [];
        $relationshipFields = [];

        // Variables to track primary entity information
        $primaryEntityMicroservice = null;
        $primaryEntityName = null;
        $primaryIdentifierField = null;
        $primaryIdentifierPropertyName = null;

        // Process all properties (fields) in the DTO class
        $properties = $reflection->getProperties();

        // First pass: collect all relationships and entity mappings
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $isAccessibleField = in_array($propertyName, $this->accessibleFields);

            // Check for MicroserviceRelationship attributes first
            $relationshipAttributes = $property->getAttributes(MicroserviceRelationship::class);

            if (!empty($relationshipAttributes)) {
                $relationshipAttribute = $relationshipAttributes[0]->newInstance();
                $relationshipFieldObjs = $relationshipAttribute->getFields();

                // Process relationship fields for entity mappings if this is an accessible field
                if ($isAccessibleField) {
                    $this->logger->info(
                        'Found MicroserviceRelationship attribute',
                        [
                            'property' => $propertyName,
                            'fieldCount' => count($relationshipFieldObjs)
                        ]
                    );

                    // Process fields from the relationship attribute for entity mappings
                    foreach ($relationshipFieldObjs as $microserviceField) {
                        $this->processEntityMappingField($microserviceField, $propertyName, $groupedFields, $fieldMap);
                    }
                }

                // Process relationship fields for relationship mapping
                // A valid relationship requires at least two fields (source and target)
                if (count($relationshipFieldObjs) >= 2) {
                    $sourceFieldObj = $relationshipFieldObjs[0];
                    $targetFieldObj = $relationshipFieldObjs[1];

                    // Create a hierarchical relationship definition
                    $relationshipFields[$propertyName] = [
                        'source' => [
                            'entity' => $sourceFieldObj->getEntity(),
                            'field'  => $sourceFieldObj->getField()
                        ],
                        'target' => [
                            'entity' => $targetFieldObj->getEntity(),
                            'field'  => $targetFieldObj->getField()
                        ]
                    ];
                } else {
                    $this->logger->warning(
                        'Invalid relationship attribute: requires at least two fields',
                        ['property' => $propertyName]
                    );
                }
            } else if ($isAccessibleField) {
                // Fall back to individual MicroserviceField attributes for accessible fields
                $microserviceFieldAttributes = $property->getAttributes(MicroserviceField::class);

                if (empty($microserviceFieldAttributes)) {
                    continue;
                }

                // Convert attribute instances to MicroserviceField objects
                foreach ($microserviceFieldAttributes as $attributeInstance) {
                    $microserviceField = $attributeInstance->newInstance();
                    $this->processEntityMappingField($microserviceField, $propertyName, $groupedFields, $fieldMap);

                    // Check if this field is marked as the primary identifier
                    if ($microserviceField->isPrimaryIdentifier()) {
                        $this->logger->info(
                            'Found primary identifier field',
                            [
                                'property' => $propertyName,
                                'microservice' => $microserviceField->getMicroservice(),
                                'entity' => $microserviceField->getEntity(),
                                'field' => $microserviceField->getField()
                            ]
                        );

                        // Store primary entity information
                        $primaryEntityMicroservice = $microserviceField->getMicroservice();
                        $primaryEntityName = $microserviceField->getEntity();
                        $primaryIdentifierField = $microserviceField->getField();
                        $primaryIdentifierPropertyName = $propertyName;
                    }
                }
            }
        }

        // Create EntityMappingDto objects for each microservice/entity combination
        $entityMappings = [];

        foreach ($groupedFields as $key => $fields) {
            list($microservice, $entity) = explode(':', $key);

            // Create field map for this entity
            $entityFieldMap = [];
            foreach ($fields as $propertyName) {
                // The $propertyName is now the DTO property name
                if (isset($fieldMap[$propertyName])) {
                    foreach ($fieldMap[$propertyName] as $mapping) {
                        if (
                            $mapping['microservice'] === $microservice
                            && $mapping['entity'] === $entity
                        ) {
                            $entityFieldMap[$propertyName] = $mapping['field'];
                            break;
                        }
                    }
                }
            }

            // Get the endpoint from the entity mapping
            $endpoint = MicroserviceEntityMapping::getEndpointForEntity($microservice, $entity);

            // Throw an exception if the endpoint is not defined
            if (!$endpoint) {
                throw new \RuntimeException(
                    "No endpoint defined for entity {$entity} in microservice {$microservice}"
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

            // Check if this is the primary entity
            $isPrimaryEntity = ($microservice === $primaryEntityMicroservice && $entity === $primaryEntityName);

            // Get the primary identifier field name for this entity if it's the primary entity
            $primaryIdField = null;
            if ($isPrimaryEntity && $primaryIdentifierField) {
                $primaryIdField = $primaryIdentifierField;
                $this->logger->info(
                    'Creating primary entity mapping',
                    [
                        'microservice' => $microservice,
                        'entity' => $entity,
                        'primaryIdentifierField' => $primaryIdField,
                        'primaryIdentifierPropertyName' => $primaryIdentifierPropertyName
                    ]
                );
            }

            // Create the EntityMappingDto with DTO property names as accessibleFields
            $entityMappings[] = new EntityMappingDto(
                $microservice,
                $entity,
                $endpoint,
                $fields, // These are now the DTO property names
                $entityFieldMap,
                $context,
                $isPrimaryEntity,
                $primaryIdField
            );
        }

        // check until here 

        // Optimize entity mappings to reduce unnecessary microservice calls
        if (count($entityMappings) > 1) {
            $entityMappings = $this->optimizeEntityMappings($entityMappings, $this->accessibleFields, $relationshipFields);
        }

        // Store primary entity information as class properties for later use
        if ($primaryEntityMicroservice && $primaryEntityName) {
            $this->primaryEntityMicroservice = $primaryEntityMicroservice;
            $this->primaryEntityName = $primaryEntityName;
            $this->primaryIdentifierField = $primaryIdentifierField;
            $this->primaryIdentifierPropertyName = $primaryIdentifierPropertyName;
        } else {
            // If no primary entity was explicitly marked, use the first entity as primary
            if (!empty($entityMappings)) {
                $this->logger->warning(
                    'No primary entity explicitly marked, using first entity as primary',
                    ['entity' => reset($entityMappings)->getEntity()]
                );
                $this->primaryEntityMicroservice = reset($entityMappings)->getMicroservice();
                $this->primaryEntityName = reset($entityMappings)->getEntity();
                // We don't know which field is the primary identifier in this case
            }
        }

        // Return entity mappings and relationship fields
        return [$entityMappings, $relationshipFields];
    }

    /**
     * Process a microservice field for entity mappings.
     *
     * @param MicroserviceField $microserviceField The microservice field to process
     * @param string $propertyName The name of the property containing the field
     * @param array &$groupedFields Reference to the grouped fields array
     * @param array &$fieldMap Reference to the field map array
     *
     * @return void
     */
    protected function processEntityMappingField(
        MicroserviceField $microserviceField,
        string $propertyName,
        array &$groupedFields,
        array &$fieldMap
    ): void {
        // Ensure we're working with a MicroserviceField object
        if (!($microserviceField instanceof MicroserviceField)) {
            $this->logger->warning(
                'Expected MicroserviceField object but got something else',
                [
                    'type' => gettype($microserviceField)
                ]
            );
            return;
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
            return;
        }

        // Group by microservice and entity
        $key = $microservice . ':' . $entity;
        if (!isset($groupedFields[$key])) {
            $groupedFields[$key] = [];
        }

        // Store the entity field name, not the MicroserviceField object
        if (!in_array($propertyName, $groupedFields[$key])) {
            $groupedFields[$key][] = $propertyName;
        }

        // Store field mapping
        if (!isset($fieldMap[$propertyName])) {
            $fieldMap[$propertyName] = [];
        }
        $fieldMap[$propertyName][] = [
            'microservice' => $microservice,
            'entity' => $entity,
            'field' => $entityField
        ];
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
     * @param array &$relationshipFields The relationship fields between entities (passed by reference)
     * 
     * @return array The optimized entity mappings
     */
    protected function optimizeEntityMappings(
        array $entityMappings,
        array $accessibleFields,
        array &$relationshipFields
    ): array {
        // If we don't have multiple entity mappings, no optimization needed
        if (count($entityMappings) <= 1) {
            return $entityMappings;
        }

        // Verify that we have the expected number of relationship fields
        // For n entity mappings, we should have n-1 relationship fields to connect them
        if (count($relationshipFields) !== count($entityMappings) - 1) {
            // If relationship count doesn't match expected, we can't safely optimize
            return $entityMappings;
        }

        // Keep track of entities that can be removed
        $entitiesToRemove = [];
        $optimizedMappings = $entityMappings;

        // First, identify entities that might be removable
        // An entity is removable if its fields are only used in relationships and
        // those fields can be retrieved from other entities through the relationships
        foreach ($relationshipFields as $relationshipName => $relationship) {
            $sourceEntity = $relationship['source']['entity'];
            $sourceField = $relationship['source']['field'];
            $targetEntity = $relationship['target']['entity'];
            $targetField = $relationship['target']['field'];

            // For each entity mapping, check if it contains only relationship fields
            foreach ($optimizedMappings as $index => $mapping) {
                $entity = $mapping->getEntity();
                $fields = $mapping->getFields();

                // Skip if this entity is already marked for removal
                if (in_array($entity, $entitiesToRemove, true)) {
                    continue;
                }

                // If this is the source entity in the relationship
                if ($entity === $sourceEntity) {
                    // Check if the source field is the only field from this entity that we need
                    $entityFieldsInAccessible = array_filter($accessibleFields, function ($field) use ($fields) {
                        return in_array($field, $fields, true);
                    });

                    // If the only field we need from this entity is the relationship field,
                    // and that field can be retrieved from the target entity, we can remove this entity
                    if (count($entityFieldsInAccessible) === 1 && in_array($sourceField, $entityFieldsInAccessible, true)) {
                        $entitiesToRemove[] = $entity;
                        // Remove this relationship as it's no longer needed
                        unset($relationshipFields[$relationshipName]);
                    }
                }
                // Same check for target entity
                elseif ($entity === $targetEntity) {
                    $entityFieldsInAccessible = array_filter($accessibleFields, function ($field) use ($fields) {
                        return in_array($field, $fields, true);
                    });

                    if (count($entityFieldsInAccessible) === 1 && in_array($targetField, $entityFieldsInAccessible, true)) {
                        $entitiesToRemove[] = $entity;
                        // Remove this relationship as it's no longer needed
                        unset($relationshipFields[$relationshipName]);
                    }
                }
            }
        }

        // Remove the entities that we've identified as unnecessary
        if (!empty($entitiesToRemove)) {
            $optimizedMappings = array_filter($optimizedMappings, function ($mapping) use ($entitiesToRemove) {
                return !in_array($mapping->getEntity(), $entitiesToRemove, true);
            });
        }

        return $optimizedMappings;
    }

    /**
     * Fetch data from microservices based on entity mappings.
     *
     * @param array  $entityMappings       Array of EntityMappingDto objects
     * @param bool   $isCollectionOperation Whether this is a collection operation
     * @param string $operationName         The name of the operation
     * @param array  $uriVariables          The URI variables
     *
     * @return array Array of DTO objects
     */
    protected function fetchFromMicroservices(): array
    {
        if (empty($this->entityMappings)) {
            return [];
        }

        // Get the current request
        $request = $this->requestStack->getCurrentRequest();

        // Extract query parameters from the request to pass to microservices
        $queryParameters = [];
        foreach ($request->query->all() as $key => $value) {
            if ($key !== 'context') {
                $queryParameters[$key] = $value;
            }
        }

        $callsDone = [];
        $fetchedData = [];

        // Find the primary entity mapping (entity marked as primary)
        $primaryEntityMapping = null;
        foreach ($this->entityMappings as $entityMapping) {
            if ($entityMapping->isPrimaryEntity()) {
                $primaryEntityMapping = $entityMapping;
                break;
            }
        }

        // Fallback to the first entity if no primary entity is explicitly marked
        if ($primaryEntityMapping === null) {
            $primaryEntityMapping = reset($this->entityMappings);
            $this->logger->warning('No primary entity explicitly marked, using first entity', [
                'microservice' => $primaryEntityMapping->microservice,
                'entity' => $primaryEntityMapping->entity
            ]);
        }

        $this->logger->info('Making primary entity call', [
            'microservice' => $primaryEntityMapping->microservice,
            'entity' => $primaryEntityMapping->entity,
            'endpoint' => $primaryEntityMapping->endpoint,
            'isPrimary' => $primaryEntityMapping->isPrimaryEntity() ? 'true' : 'false',
            'primaryIdentifierField' => $primaryEntityMapping->getPrimaryIdentifierField()
        ]);

        // Prepare parameters for the API call
        $idParam = $this->id;
        $idField = null;

        // If this is a primary entity with a defined primary identifier field,
        // use that field name for the ID parameter
        if ($primaryEntityMapping->isPrimaryEntity() && $primaryEntityMapping->getPrimaryIdentifierField() !== null) {
            $idField = $primaryEntityMapping->getPrimaryIdentifierField();
            $this->logger->info('Using primary identifier field for ID parameter', [
                'idField' => $idField,
                'idValue' => $idParam
            ]);
        }

        // Determine whether to use mock data or real data
        if ($this->useMockData) {
            $this->logger->info('Using mock data for primary entity');
            $primaryData = $this->mockClient->fetchEntityData(
                $primaryEntityMapping,
                $queryParameters,
                $idParam,
                $idField
            );
        } else {
            $this->logger->info('Using real data for primary entity');
            $primaryData = $this->microserviceClient->fetchEntityData(
                $primaryEntityMapping,
                $queryParameters,
                $idParam,
                $idField
            );
        }

        // Store the primary data
        $fetchedData[$primaryEntityMapping->microservice . '_' . $primaryEntityMapping->entity] = $primaryData;
        $callsDone[] = [
            'microservice' => $primaryEntityMapping->microservice,
            'entity' => $primaryEntityMapping->entity,
            'endpoint' => $primaryEntityMapping->endpoint,
            'params' => $queryParameters,
            'result_count' => is_array($primaryData) ? count($primaryData) : 0
        ];

        // If we have relationships, process them
        if (!empty($this->relationshipFields)) {
            $this->logger->info('Processing relationships', [
                'count' => count($this->relationshipFields)
            ]);

            // Loop through each relationship
            foreach ($this->relationshipFields as $relationshipField) {
                $this->logger->debug('Processing relationship', [
                    'field' => $relationshipField->name
                ]);

                // Get the source entity data
                $sourceEntityKey = $relationshipField->sourceEntity->microservice . '_' . $relationshipField->sourceEntity->entity;

                // Skip if we don't have the source data
                if (!isset($fetchedData[$sourceEntityKey])) {
                    $this->logger->warning('Source entity data not found for relationship', [
                        'sourceEntity' => $sourceEntityKey,
                        'relationship' => $relationshipField->name
                    ]);
                    continue;
                }

                $sourceData = $fetchedData[$sourceEntityKey];

                // Extract IDs from source data based on the source field
                $ids = [];
                $sourceField = $relationshipField->sourceField;

                // Handle collection vs single item
                if (is_array($sourceData)) {
                    if (isset($sourceData['hydra:member']) && is_array($sourceData['hydra:member'])) {
                        // Handle hydra collection
                        foreach ($sourceData['hydra:member'] as $item) {
                            if (isset($item[$sourceField]) && !empty($item[$sourceField])) {
                                $ids[] = $item[$sourceField];
                            }
                        }
                    } else {
                        // Handle regular array
                        foreach ($sourceData as $item) {
                            if (is_array($item) && isset($item[$sourceField]) && !empty($item[$sourceField])) {
                                $ids[] = $item[$sourceField];
                            }
                        }
                    }
                } elseif (is_object($sourceData) && isset($sourceData->$sourceField)) {
                    // Handle single object
                    $ids[] = $sourceData->$sourceField;
                }

                // Skip if no IDs found
                if (empty($ids)) {
                    $this->logger->warning('No IDs found for relationship', [
                        'relationship' => $relationshipField->name,
                        'sourceField' => $sourceField
                    ]);
                    continue;
                }

                // Remove duplicates and prepare for API call
                $ids = array_unique($ids);
                $this->logger->debug('Found IDs for relationship', [
                    'relationship' => $relationshipField->name,
                    'idCount' => count($ids)
                ]);

                // Prepare query parameters for the target entity
                $targetParams = $queryParameters;
                $targetParams[$relationshipField->targetParam] = implode(',', $ids);

                // Get the target entity mapping
                $targetEntityMapping = $relationshipField->targetEntity;

                // Make the call to the target entity
                $this->logger->info('Making relationship entity call', [
                    'microservice' => $targetEntityMapping->microservice,
                    'entity' => $targetEntityMapping->entity,
                    'endpoint' => $targetEntityMapping->endpoint,
                    'targetParam' => $relationshipField->targetParam,
                    'idCount' => count($ids)
                ]);

                // Fetch data from target entity
                // Check if the target entity has a primary identifier field
                $targetIdField = null;
                if ($targetEntityMapping->isPrimaryEntity() && $targetEntityMapping->getPrimaryIdentifierField() !== null) {
                    $targetIdField = $targetEntityMapping->getPrimaryIdentifierField();
                    $this->logger->debug('Using primary identifier field for relationship target', [
                        'relationship' => $relationshipField->name,
                        'targetIdField' => $targetIdField
                    ]);
                } else {
                    // Use the target parameter as the ID field if no primary identifier is set
                    $targetIdField = $relationshipField->targetParam;
                    $this->logger->debug('Using target parameter as ID field for relationship', [
                        'relationship' => $relationshipField->name,
                        'targetIdField' => $targetIdField
                    ]);
                }

                if ($this->shouldUseMockData()) {
                    $targetData = $this->mockClient->fetchEntityData(
                        $targetEntityMapping,
                        $targetParams,
                        null,  // No specific ID since we're using the targetParams
                        $targetIdField
                    );
                } else {
                    $targetData = $this->microserviceClient->fetchEntityData(
                        $targetEntityMapping,
                        $targetParams,
                        null,  // No specific ID since we're using the targetParams
                        $targetIdField
                    );
                }

                // Store the target data
                $targetEntityKey = $targetEntityMapping->microservice . '_' . $targetEntityMapping->entity;
                $fetchedData[$targetEntityKey] = $targetData;

                $callsDone[] = [
                    'microservice' => $targetEntityMapping->microservice,
                    'entity' => $targetEntityMapping->entity,
                    'endpoint' => $targetEntityMapping->endpoint,
                    'params' => $targetParams,
                    'result_count' => is_array($targetData) ? count($targetData) : 0,
                    'relationship' => $relationshipField->name,
                    'source_entity' => $sourceEntityKey,
                    'target_param' => $relationshipField->targetParam,
                    'target_id_field' => $targetIdField,
                    'ids_used' => $ids
                ];
            }
        }

        // Debug output for calls made
        $this->logger->info('Completed all microservice calls', [
            'callCount' => count($callsDone),
            'entitiesFetched' => array_keys($fetchedData)
        ]);

        // Map field names from microservice response to DTO field names and apply access control
        $fetchedData = $this->mapAndFilterFields($fetchedData);

        return $fetchedData;
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

    /**
     * Map field names from microservice response to DTO field names and apply access control.
     *
     * This method combines field mapping and access control in a single pass for better performance
     * and to prevent field name conflicts when merging data from multiple microservices.
     *
     * @param array $fetchedData Raw data from microservices
     *
     * @return array Data with field names mapped to DTO field names and filtered by access control
     */
    protected function mapAndFilterFields(array $fetchedData): array
    {
        $mappedData = [];

        // Create a field mapping from all entity mappings
        $fieldMap = [];
        $accessibleFields = [];

        // Get the current DTO class for access control
        $dtoClass = $this->dtoClass ?: get_class($this->resource ?? new \stdClass());

        // Get accessible fields based on user roles and portal
        if (!empty($this->userRoles) && !empty($this->portal)) {
            $accessibleFields = $this->fieldAccessResolver->getAccessibleFields(
                $dtoClass,
                $this->userRoles,
                $this->portal
            );

            $this->logger->debug('Access control enabled', [
                'dtoClass' => $dtoClass,
                'accessibleFieldCount' => count($accessibleFields),
                'userRoles' => $this->userRoles,
                'portal' => $this->portal
            ]);
        } else {
            $this->logger->debug('Access control disabled - missing user roles or portal');
        }

        // Build field mapping
        foreach ($this->entityMappings as $mapping) {
            $entityKey = $mapping->microservice . '_' . $mapping->entity;

            // Store the field map for this entity
            if (!isset($fieldMap[$entityKey])) {
                $fieldMap[$entityKey] = [];
            }

            // Map microservice field names to DTO field names
            foreach ($mapping->fieldMap as $dtoField => $entityField) {
                // Only include fields that are accessible based on user roles
                if (empty($accessibleFields) || in_array($dtoField, $accessibleFields, true)) {
                    $fieldMap[$entityKey][$entityField] = $dtoField;
                } else {
                    $this->logger->debug('Field filtered by access control', [
                        'entityKey' => $entityKey,
                        'field' => $entityField,
                        'dtoField' => $dtoField
                    ]);
                }
            }
        }

        $this->logger->debug('Field mapping created with access control', [
            'entityCount' => count($fieldMap),
            'entities' => array_keys($fieldMap),
            'isCollectionOperation' => $this->isCollectionOperation,
            'accessibleFieldCount' => count($accessibleFields)
        ]);

        // Process each entity's data
        foreach ($fetchedData as $entityKey => $entityData) {
            if (!isset($fieldMap[$entityKey]) || empty($fieldMap[$entityKey])) {
                // No mapping for this entity or all fields filtered out, keep minimal data
                // For collections, we still need the structure but with filtered content
                if ($this->isCollectionOperation && isset($entityData['hydra:member'])) {
                    $mappedData[$entityKey] = [
                        'hydra:member' => []
                    ];

                    // Copy hydra metadata
                    foreach ($entityData as $key => $value) {
                        if ($key !== 'hydra:member') {
                            $mappedData[$entityKey][$key] = $value;
                        }
                    }
                } else {
                    // For non-collections with no mapping, skip entirely
                    $this->logger->debug('No field mapping found for entity or all fields filtered', ['entityKey' => $entityKey]);
                    continue;
                }
            } else {
                // Process based on data structure
                if (isset($entityData['hydra:member']) && is_array($entityData['hydra:member'])) {
                    // It's in hydra format
                    $mappedData[$entityKey] = [
                        'hydra:member' => []
                    ];

                    // Copy other hydra metadata
                    foreach ($entityData as $key => $value) {
                        if ($key !== 'hydra:member') {
                            $mappedData[$entityKey][$key] = $value;
                        }
                    }

                    $collectionData = $entityData['hydra:member'];

                    // Map and filter each item in the collection
                    foreach ($collectionData as $item) {
                        if (!is_array($item)) {
                            // Skip non-array items
                            continue;
                        }

                        $mappedItem = [];

                        // Map fields using the field map (already filtered by access control)
                        foreach ($item as $field => $value) {
                            if (isset($fieldMap[$entityKey][$field])) {
                                $mappedField = $fieldMap[$entityKey][$field];
                                $mappedItem[$mappedField] = $value;
                            }
                        }

                        // Only add the item if it has data after filtering
                        if (!empty($mappedItem)) {
                            $mappedData[$entityKey]['hydra:member'][] = $mappedItem;
                        }
                    }

                    $this->logger->debug('Mapped and filtered hydra collection', [
                        'entityKey' => $entityKey,
                        'originalCount' => count($entityData['hydra:member']),
                        'mappedCount' => count($mappedData[$entityKey]['hydra:member'])
                    ]);
                } elseif (is_array($entityData) && !$this->isAssociativeArray($entityData)) {
                    // It's a non-hydra collection (indexed array)
                    $mappedData[$entityKey] = [];

                    // Map and filter each item in the collection
                    foreach ($entityData as $item) {
                        if (!is_array($item)) {
                            // Skip non-array items
                            continue;
                        }

                        $mappedItem = [];

                        // Map fields using the field map (already filtered by access control)
                        foreach ($item as $field => $value) {
                            if (isset($fieldMap[$entityKey][$field])) {
                                $mappedField = $fieldMap[$entityKey][$field];
                                $mappedItem[$mappedField] = $value;
                            }
                        }

                        // Only add the item if it has data after filtering
                        if (!empty($mappedItem)) {
                            $mappedData[$entityKey][] = $mappedItem;
                        }
                    }

                    $this->logger->debug('Mapped and filtered non-hydra collection', [
                        'entityKey' => $entityKey,
                        'originalCount' => count($entityData),
                        'mappedCount' => count($mappedData[$entityKey])
                    ]);
                } else {
                    // Handle single item
                    if (is_array($entityData)) {
                        $mappedItem = [];

                        // Map fields using the field map (already filtered by access control)
                        foreach ($entityData as $field => $value) {
                            if (isset($fieldMap[$entityKey][$field])) {
                                $mappedField = $fieldMap[$entityKey][$field];
                                $mappedItem[$mappedField] = $value;
                            }
                        }

                        $mappedData[$entityKey] = $mappedItem;

                        $this->logger->debug('Mapped and filtered single item data', [
                            'entityKey' => $entityKey,
                            'originalFieldCount' => count($entityData),
                            'mappedFieldCount' => count($mappedItem)
                        ]);
                    } else {
                        // Not an array, keep as is if we have any mapping for this entity
                        $mappedData[$entityKey] = $entityData;
                        $this->logger->debug('Kept non-array data as is', ['entityKey' => $entityKey]);
                    }
                }
            }
        }

        return $mappedData;
    }

    /**
     * Check if an array is associative (has string keys) or sequential (has numeric keys).
     *
     * @param array $array The array to check
     *
     * @return bool True if the array is associative, false if it's sequential
     */
    private function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Map field names from microservice response to DTO field names.
     *
     * @deprecated Use mapAndFilterFields() instead which also applies access control
     * @param array $fetchedData Raw data from microservices
     *
     * @return array Data with field names mapped to DTO field names
     */
    protected function mapFieldNames(array $fetchedData): array
    {
        $this->logger->warning('Using deprecated mapFieldNames method - use mapAndFilterFields instead');
        return $this->mapAndFilterFields($fetchedData);
    }
}
