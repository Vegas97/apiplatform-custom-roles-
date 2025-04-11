<?php

/**
 * Microservice Field Attribute for mapping DTO fields to microservice fields.
 *
 * PHP version 8.4
 *
 * @version  GIT: <git_id>
 * @category Attribute
 * @package  App\Attribute
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

/**
 * Maps a DTO field to a microservice entity field.
 *
 * @category Attribute
 * @package  App\Attribute
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MicroserviceField
{
    /**
     * Constructor.
     *
     * @param string $microservice Microservice name
     * @param string $entity       Entity name
     * @param string $field        Field name in the entity
     */
    public function __construct(
        private readonly string $microservice,
        private readonly string $entity,
        private readonly string $field
    ) {
    }

    /**
     * Get microservice name.
     *
     * @return string
     */
    public function getMicroservice(): string
    {
        return $this->microservice;
    }

    /**
     * Get entity name.
     *
     * @return string
     */
    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * Get field name.
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }
    

}
