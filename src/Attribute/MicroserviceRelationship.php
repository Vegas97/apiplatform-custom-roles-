<?php

/**
 * Microservice Relationship Attribute.
 *
 * PHP version 8.4
 *
 * @category Attribute
 * @package  App\Attribute
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 * @version  GIT: <git_id>
 */

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

/**
 * Attribute to define relationships between fields in different microservices.
 *
 * @category Attribute
 * @package  App\Attribute
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MicroserviceRelationship
{
    /**
     * Array of MicroserviceField instances.
     *
     * @var array<MicroserviceField>
     */
    private array $_fields = [];

    /**
     * Constructor.
     *
     * @param MicroserviceField ...$fields The microservice fields that form this relationship
     */
    public function __construct(MicroserviceField ...$fields)
    {
        $this->_fields = $fields;
    }

    /**
     * Get the microservice fields.
     *
     * @return array<MicroserviceField> Array of MicroserviceField instances
     */
    public function getFields(): array
    {
        return $this->_fields;
    }
}
