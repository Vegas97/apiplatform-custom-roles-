<?php

/**
 * User DTO for API Platform.
 *
 * @category ApiResource
 * @package  App\ApiResource
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 * @version  GIT: <git_id>
 */

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\UserDtoProvider;
use Symfony\Component\Validator\Constraints as Assert;
use DateTimeInterface;
use App\Attribute\AllowedRoles;

/**
 * User DTO for API responses.
 *
 * @category ApiResource
 * @package  App\ApiResource
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
#[ApiResource(
    operations: [
        new Get(),
        new GetCollection()
    ],
    provider: UserDtoProvider::class
)]
class UserDto
{
    /**
     * Constructor for UserDto.
     *
     * @param string            $id        User identifier
     * @param string            $username  Username
     * @param string            $email     Email address
     * @param DateTimeInterface $birthDate User birth date
     */
    public function __construct(

        #[AllowedRoles(
            [
                'admin' => ['ROLE_USER_ACCESS'],
                'workspace' => ['ROLE_USER_ACCESS'],
                'distributor' => ['ROLE_USER_ACCESS']
            ]
        )]
        #[Assert\NotBlank]
        public readonly string $id = '',

        #[AllowedRoles(
            [
                'admin' => ['ROLE_USER_ACCESS'],
                'workspace' => ['ROLE_USER_ACCESS'],
            ]
        )]
        #[Assert\NotBlank]
        #[Assert\Length(min: 3)]
        public readonly string $username = '',

        #[AllowedRoles(
            [
                'admin' => ['ROLE_USER_ACCESS'],
                'workspace' => ['ROLE_USER_ACCESS'],
                'distributor' => []
            ]
        )]
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $email = '',

        #[AllowedRoles(
            [
                'admin' => ['ROLE_USER_ACCESS'],
                'workspace' => ['ROLE_USER_ACCESS'],
            ]
        )]
        #[Assert\NotNull]
        public readonly ?DateTimeInterface $birthDate = null
    ) {}
}
