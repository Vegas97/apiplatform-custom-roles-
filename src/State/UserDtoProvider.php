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
 * Provider for UserDto resources.
 *
 * @category State
 * @package  App\State
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class UserDtoProvider implements ProviderInterface
{
    /**
     * Provides UserDto objects for API Platform.
     *
     * @param Operation $operation    The operation
     * @param array     $uriVariables The URI variables
     * @param array     $context      The context
     *
     * @return object|array|null The data
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // For collection endpoint (GET /api/user_dtos)
        if (empty($uriVariables)) {
            return [
                new UserDto('1', 'john_doe', 'john@example.com', new DateTime('1990-01-15')),
                new UserDto('2', 'admin_user', 'admin@example.com', new DateTime('1985-05-22')),
                new UserDto('3', 'support_agent', 'support@example.com', new DateTime('1992-11-07'))
            ];
        }

        // For item endpoint (GET /api/user_dtos/{id})
        $id = $uriVariables['id'] ?? null;

        return match ($id) {
            '1' => new UserDto('1', 'john_doe', 'john@example.com', new DateTime('1990-01-15')),
            '2' => new UserDto('2', 'admin_user', 'admin@example.com', new DateTime('1985-05-22')),
            '3' => new UserDto('3', 'support_agent', 'support@example.com', new DateTime('1992-11-07')),
            default => null,
        };
    }
}
