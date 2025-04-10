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
                'admin' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS'],
                'workspace' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS'],
                'distributor' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS']
            ]
        )]
        #[Assert\NotBlank]
        public string $id = '',

        #[AllowedRoles(
            [
                'admin' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS'],
                'workspace' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS'],
                'distributor' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS']
            ]
        )]
        #[Assert\NotBlank]
        #[Assert\Length(min: 3)]
        public string $username = '',

        #[AllowedRoles(
            [
                'admin' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS'],
                'workspace' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS'],
                'distributor' => ['']
            ]
        )]
        #[Assert\NotNull]
        #[Assert\Email]
        public ?string $email = null,

        #[AllowedRoles(
            [
                'admin' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS'],
                'workspace' => ['ROLE_SYSTEMBFF-USERDTO_ACCESS'],
            ]
        )]
        #[Assert\NotNull]
        public ?DateTimeInterface $birthDate = null
    ) {}
}
