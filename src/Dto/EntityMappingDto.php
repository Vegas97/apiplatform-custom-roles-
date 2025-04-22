<?php

/**
 * Entity Mapping DTO for microservice integration.
 *
 * PHP version 8.4
 *
 * @category  Dto
 * @package   App\Dto
 * @author    API Platform Team <contact@api-platform.com>
 * @copyright 2023 API Platform
 * @license   MIT License
 * @version   GIT: <git_id>
 * @link      https://apiplatform.com
 */

declare(strict_types=1);

namespace App\Dto;

/**
 * DTO for mapping between BFF DTOs and microservice entities.
 *
 * @category  Dto
 * @package   App\Dto
 * @author    API Platform Team <contact@api-platform.com>
 * @license   MIT License
 * @link      https://apiplatform.com
 */
class EntityMappingDto
{
    /**
     * Constructor.
     *
     * @param string      $microservice            Name of the microservice
     * @param string      $entity                  Name of the entity in the microservice
     * @param string      $endpoint                API endpoint to call
     * @param array       $accessibleFields        Fields accessible to the user
     * @param array       $fieldMap                Mapping of BFF fields to entity fields
     * @param string      $context                 Context level for the API call
     * @param bool        $isPrimaryEntity         Whether this is the primary entity for the DTO
     * @param string|null $primaryIdentifierField  Name of the field that serves as the primary identifier
     */
    public function __construct(
        public readonly string $microservice,
        public readonly string $entity,
        public readonly string $endpoint,
        public readonly array $accessibleFields = [],
        public readonly array $fieldMap = [],
        public readonly string $context = 'context_ids',
        public readonly bool $isPrimaryEntity = false,
        public readonly ?string $primaryIdentifierField = null
    ) {
    }

    /**
     * Get the microservice name.
     *
     * @return string The microservice name
     */
    public function getMicroservice(): string
    {
        return $this->microservice;
    }

    /**
     * Get the entity name.
     *
     * @return string The entity name
     */
    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * Get the endpoint.
     *
     * @return string The endpoint
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get the field map.
     *
     * @return array The field map
     */
    public function getFieldMap(): array
    {
        return $this->fieldMap;
    }

    /**
     * Get the accessible fields.
     *
     * @return array The accessible fields
     */
    public function getFields(): array
    {
        return $this->accessibleFields;
    }
    
    /**
     * Check if this is the primary entity for the DTO.
     *
     * @return bool
     */
    public function isPrimaryEntity(): bool
    {
        return $this->isPrimaryEntity;
    }
    
    /**
     * Get the primary identifier field name.
     *
     * @return string|null The primary identifier field name or null if not set
     */
    public function getPrimaryIdentifierField(): ?string
    {
        return $this->primaryIdentifierField;
    }
}
