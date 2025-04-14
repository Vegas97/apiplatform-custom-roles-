<?php

/**
 * Mock Microservice Client Service for API Platform.
 *
 * PHP version 8.4
 *
 * @category Service
 * @package  App\Service
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @version  GIT: <git_id>
 * @link     https://apiplatform.com
 */

declare(strict_types=1);

namespace App\Service;

use App\Config\MicroserviceEntityMapping;
use App\Dto\EntityMappingDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Mock client for microservices that respects context levels.
 *
 * @category Service
 * @package  App\Service
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class MockMicroserviceClient
{
    /**
     * Logger service.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $_logger;

    /**
     * Parameter bag for accessing configuration.
     *
     * @var ParameterBagInterface
     */
    private ParameterBagInterface $_parameterBag;

    /**
     * Constructor.
     *
     * @param LoggerInterface       $logger       Logger service
     * @param ParameterBagInterface $parameterBag Parameter bag for configuration
     */
    public function __construct(
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag
    ) {
        $this->_logger = $logger;
        $this->_parameterBag = $parameterBag;
    }

    /**
     * Fetch data from a microservice based on entity mapping.
     *
     * @param EntityMappingDto $entityMapping   Entity mapping information
     * @param array            $queryParameters Query parameters for the request
     * @param string|null      $id              Optional ID for single item requests
     *
     * @return array The fetched data
     */
    public function fetchEntityData(
        EntityMappingDto $entityMapping,
        array $queryParameters = [],
        ?string $id = null
    ): array {
        $microservice = $entityMapping->microservice;
        $entity = $entityMapping->entity;
        $context = $entityMapping->context;
        
        $this->_logger->info(
            'Fetching mock data',
            [
                'microservice' => $microservice,
                'entity' => $entity,
                'context' => $context,
                'id' => $id
            ]
        );
        
        // Define mock data structure based on context levels
        // This is a simplified example - expand with more entities/fields as needed
        $mockData = [
            'guest-service' => [
                'Guest' => [
                    'context_ids' => [
                        ['id' => 'G001'],
                        ['id' => 'G002'],
                        ['id' => 'G003'],
                    ],
                    'context_mini' => [
                        [
                            'id' => 'G001', 
                            'fullName' => 'John Doe', 
                            'email' => 'john@example.com',
                            'countryCode' => 'US',
                            'reservationId' => 'R101'
                        ],
                        [
                            'id' => 'G002', 
                            'fullName' => 'Jane Smith', 
                            'email' => 'jane@example.com',
                            'countryCode' => 'UK',
                            'reservationId' => 'R102'
                        ],
                        [
                            'id' => 'G003', 
                            'fullName' => 'Bob Johnson', 
                            'email' => 'bob@example.com',
                            'countryCode' => 'CA',
                            'reservationId' => 'R103'
                        ],
                    ],
                    'context_normal' => [
                        [
                            'id' => 'G001', 
                            'fullName' => 'John Doe', 
                            'email' => 'john@example.com',
                            'countryCode' => 'US',
                            'dateOfBirth' => '1985-06-15',
                            'documentId' => 'US123456789',
                            'phoneNumber' => '123-456-7890',
                            'reservationId' => 'R101'
                        ],
                        [
                            'id' => 'G002', 
                            'fullName' => 'Jane Smith', 
                            'email' => 'jane@example.com',
                            'countryCode' => 'UK',
                            'dateOfBirth' => '1990-03-22',
                            'documentId' => 'UK987654321',
                            'phoneNumber' => '987-654-3210',
                            'reservationId' => 'R102'
                        ],
                        [
                            'id' => 'G003', 
                            'fullName' => 'Bob Johnson', 
                            'email' => 'bob@example.com',
                            'countryCode' => 'CA',
                            'dateOfBirth' => '1978-11-30',
                            'documentId' => 'CA555555555',
                            'phoneNumber' => '555-555-5555',
                            'reservationId' => 'R103'
                        ],
                    ],
                    'context_full' => [
                        [
                            'id' => 'G001', 
                            'fullName' => 'John Doe', 
                            'email' => 'john@example.com',
                            'countryCode' => 'US',
                            'dateOfBirth' => '1985-06-15',
                            'documentId' => 'US123456789',
                            'phoneNumber' => '123-456-7890',
                            'address' => '123 Main St, New York, NY 10001',
                            'reservationId' => 'R101',
                            'createdAt' => '2023-01-15T10:00:00Z',
                            'updatedAt' => '2023-04-20T14:30:00Z'
                        ],
                        [
                            'id' => 'G002', 
                            'fullName' => 'Jane Smith', 
                            'email' => 'jane@example.com',
                            'countryCode' => 'UK',
                            'dateOfBirth' => '1990-03-22',
                            'documentId' => 'UK987654321',
                            'phoneNumber' => '987-654-3210',
                            'address' => '456 Oak Ave, London, UK SW1A 1AA',
                            'reservationId' => 'R102',
                            'createdAt' => '2023-02-10T09:15:00Z',
                            'updatedAt' => '2023-05-05T11:45:00Z'
                        ],
                        [
                            'id' => 'G003', 
                            'fullName' => 'Bob Johnson', 
                            'email' => 'bob@example.com',
                            'countryCode' => 'CA',
                            'dateOfBirth' => '1978-11-30',
                            'documentId' => 'CA555555555',
                            'phoneNumber' => '555-555-5555',
                            'address' => '789 Pine Rd, Toronto, ON M5V 2H1',
                            'reservationId' => 'R103',
                            'createdAt' => '2023-03-22T16:20:00Z',
                            'updatedAt' => '2023-06-18T08:10:00Z'
                        ],
                    ]
                ]
            ],
            'reservation-service' => [
                'Reservation' => [
                    'context_ids' => [
                        ['id' => 'R101'],
                        ['id' => 'R102'],
                        ['id' => 'R103'],
                        ['id' => 'R104'],
                    ],
                    'context_mini' => [
                        [
                            'id' => 'R101',
                            'guestDocumentId' => 'US123456789',
                            'arrivalDate' => '2025-05-01',
                            'departureDate' => '2025-05-05',
                            'status' => 'confirmed'
                        ],
                        [
                            'id' => 'R102',
                            'guestDocumentId' => 'UK987654321',
                            'arrivalDate' => '2025-05-10',
                            'departureDate' => '2025-05-15',
                            'status' => 'confirmed'
                        ],
                        [
                            'id' => 'R103',
                            'guestDocumentId' => 'CA555555555',
                            'arrivalDate' => '2025-06-01',
                            'departureDate' => '2025-06-07',
                            'status' => 'pending'
                        ],
                        [
                            'id' => 'R104',
                            'guestDocumentId' => 'US123456789',
                            'arrivalDate' => '2025-07-15',
                            'departureDate' => '2025-07-20',
                            'status' => 'pending'
                        ]
                    ],
                    'context_normal' => [
                        [
                            'id' => 'R101',
                            'guestDocumentId' => 'US123456789',
                            'arrivalDate' => '2025-05-01',
                            'departureDate' => '2025-05-05',
                            'roomNumber' => '101',
                            'status' => 'confirmed',
                            'paymentStatus' => 'paid',
                            'totalAmount' => 750.00
                        ],
                        [
                            'id' => 'R102',
                            'guestDocumentId' => 'UK987654321',
                            'arrivalDate' => '2025-05-10',
                            'departureDate' => '2025-05-15',
                            'roomNumber' => '205',
                            'status' => 'confirmed',
                            'paymentStatus' => 'paid',
                            'totalAmount' => 1200.00
                        ],
                        [
                            'id' => 'R103',
                            'guestDocumentId' => 'CA555555555',
                            'arrivalDate' => '2025-06-01',
                            'departureDate' => '2025-06-07',
                            'roomNumber' => '310',
                            'status' => 'pending',
                            'paymentStatus' => 'awaiting',
                            'totalAmount' => 1500.00
                        ],
                        [
                            'id' => 'R104',
                            'guestDocumentId' => 'US123456789',
                            'arrivalDate' => '2025-07-15',
                            'departureDate' => '2025-07-20',
                            'roomNumber' => '402',
                            'status' => 'pending',
                            'paymentStatus' => 'awaiting',
                            'totalAmount' => 1250.00
                        ]
                    ],
                    'context_full' => [
                        [
                            'id' => 'R101',
                            'guestDocumentId' => 'US123456789',
                            'arrivalDate' => '2025-05-01',
                            'departureDate' => '2025-05-05',
                            'roomNumber' => '101',
                            'status' => 'confirmed',
                            'paymentStatus' => 'paid',
                            'totalAmount' => 750.00,
                            'specialRequests' => 'Early check-in requested',
                            'createdAt' => '2025-01-15T10:00:00Z',
                            'updatedAt' => '2025-01-20T14:30:00Z'
                        ],
                        [
                            'id' => 'R102',
                            'guestDocumentId' => 'UK987654321',
                            'arrivalDate' => '2025-05-10',
                            'departureDate' => '2025-05-15',
                            'roomNumber' => '205',
                            'status' => 'confirmed',
                            'paymentStatus' => 'paid',
                            'totalAmount' => 1200.00,
                            'specialRequests' => 'Non-smoking room, high floor',
                            'createdAt' => '2025-02-05T09:15:00Z',
                            'updatedAt' => '2025-02-10T11:45:00Z'
                        ],
                        [
                            'id' => 'R103',
                            'guestDocumentId' => 'CA555555555',
                            'arrivalDate' => '2025-06-01',
                            'departureDate' => '2025-06-07',
                            'roomNumber' => '310',
                            'status' => 'pending',
                            'paymentStatus' => 'awaiting',
                            'totalAmount' => 1500.00,
                            'specialRequests' => 'Extra pillows, late check-out',
                            'createdAt' => '2025-03-10T16:20:00Z',
                            'updatedAt' => '2025-03-15T08:10:00Z'
                        ],
                        [
                            'id' => 'R104',
                            'guestDocumentId' => 'US123456789',
                            'arrivalDate' => '2025-07-15',
                            'departureDate' => '2025-07-20',
                            'roomNumber' => '402',
                            'status' => 'pending',
                            'paymentStatus' => 'awaiting',
                            'totalAmount' => 1250.00,
                            'specialRequests' => 'Airport shuttle service',
                            'createdAt' => '2025-04-01T11:30:00Z',
                            'updatedAt' => '2025-04-05T13:45:00Z'
                        ]
                    ]
                ]
            ]
        ];
        
        // Check if we have mock data for this entity and context
        if (!isset($mockData[$microservice][$entity][$context])) {
            $this->_logger->warning(
                'No mock data available',
                [
                    'microservice' => $microservice,
                    'entity' => $entity,
                    'context' => $context
                ]
            );
            return [];
        }
        
        $data = $mockData[$microservice][$entity][$context];
        
        // Filter by ID if provided
        if ($id !== null) {
            $this->_logger->info('Filtering by specific ID', [
                'id' => $id
            ]);
            
            $data = array_filter(
                $data, 
                function ($item) use ($id) {
                    return isset($item['id']) && $item['id'] == $id;
                }
            );
            
            // If we're filtering by ID, we don't need to apply the 'ids' filter
            // Remove 'ids' from query parameters if it exists
            if (isset($queryParameters['ids'])) {
                $this->_logger->info('ID parameter takes precedence over ids query parameter');
                unset($queryParameters['ids']);
            }
        }
        
        // Apply any additional query parameters filtering
        foreach ($queryParameters as $key => $value) {
            if ($key !== 'context') {
                // Special handling for 'ids' parameter
                if ($key === 'ids') {
                    // Handle both single ID and comma-separated list of IDs
                    $idList = strpos($value, ',') !== false 
                        ? array_map('trim', explode(',', $value)) 
                        : [$value]; // Single ID case
                    
                    $this->_logger->info('Processing ids parameter', [
                        'idCount' => count($idList),
                        'ids' => $idList,
                        'isMultiple' => strpos($value, ',') !== false
                    ]);
                    
                    $data = array_filter(
                        $data,
                        function ($item) use ($idList) {
                            return isset($item['id']) && in_array($item['id'], $idList);
                        }
                    );
                } else {
                    $data = array_filter(
                        $data, 
                        function ($item) use ($key, $value) {
                            return isset($item[$key]) && $item[$key] == $value;
                        }
                    );
                }
            }
        }
        
        // Reset array keys
        $data = array_values($data);
        
        // Format the response in Hydra JSON-LD format
        if ($id !== null) {
            // Single item request - return the first item if found
            return !empty($data) 
                ? $this->_formatSingleItem($data[0], $entity) 
                : [];
        } else {
            // Collection request
            return $this->_formatCollection(
                $data, 
                $entity
            );
        }
    }
    
    /**
     * Format a single item in Hydra JSON-LD format.
     *
     * @param array  $item   The item data
     * @param string $entity The entity name
     *
     * @return array The formatted item
     */
    private function _formatSingleItem(
        array $item, 
        string $entity
    ): array {
        return [
            '@context' => '/api/contexts/' . $entity,
            '@id' => '/api/' . strtolower($entity) . 's/' . $item['id'],
            '@type' => $entity,
            ...$item // Include all other fields from the item
        ];
    }
    
    /**
     * Format a collection in Hydra JSON-LD format.
     *
     * @param array  $items  The collection items
     * @param string $entity The entity name
     *
     * @return array The formatted collection
     */
    private function _formatCollection(
        array $items, 
        string $entity
    ): array {
        $formattedItems = [];
        $totalItems = count($items);
        
        foreach ($items as $item) {
            $formattedItems[] = $this->_formatSingleItem($item, $entity);
        }
        
        return [
            '@context' => '/api/contexts/' . $entity,
            '@id' => '/api/' . strtolower($entity) . 's',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => $totalItems,
            'hydra:member' => $formattedItems,
            'hydra:view' => [
                '@id' => '/api/' . strtolower($entity) . 's?page=1',
                '@type' => 'hydra:PartialCollectionView',
                'hydra:first' => '/api/' . strtolower($entity) . 's?page=1',
                'hydra:last' => '/api/' . strtolower($entity) . 's?page=1',
            ],
        ];
    }
}
