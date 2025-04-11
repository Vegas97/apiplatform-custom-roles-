<?php

/**
 * User DTO Provider for API Platform.
 *
 * @category State
 * @package  App\State
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 * @version  GIT: <git_id>
 */

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\UserDto;
use DateTime;

/**
 * Provider for UserDto resources with role-based access control.
 *
 * @category State
 * @package  App\State
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class UserDtoProvider extends AbstractDtoProvider implements ProviderInterface
{

    /**
     * Provides the data for the API resource.
     *
     * @param Operation $operation    The operation
     * @param array     $uriVariables The URI variables
     * @param array     $context      The context
     *
     * @return object|array|null The data
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return parent::provide($operation, $uriVariables, $context);
    }

    /**
     * Get the DTO class name.
     *
     * @return string The fully qualified class name of the DTO
     */
    protected function getDtoClass(): string
    {
        return UserDto::class;
    }

    /**
     * Get items for the provider.
     *
     * @return array Array of UserDto objects
     */
    protected function getItems(): array
    {
        // Sample data - in a real app, this would come from a database or other source
        return [
            new UserDto(
                '1',
                'john_doe',
                'john@example.com',
                new DateTime('1990-01-15')
            ),
            new UserDto(
                '2',
                'admin_user',
                'admin@example.com',
                new DateTime('1985-05-22')
            ),
            new UserDto(
                '3',
                'support_agent',
                'support@example.com',
                new DateTime('1992-11-07')
            )
        ];
    }
}
