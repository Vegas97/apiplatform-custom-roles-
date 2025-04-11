<?php

/**
 * Guest Reservation DTO for API Platform.
 *
 * PHP version 8.4
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
use App\State\GuestReservationDtoProvider;
use Symfony\Component\Validator\Constraints as Assert;
use DateTimeInterface;
use App\Attribute\AllowedRoles;

/**
 * Guest Reservation DTO for API responses.
 *
 * Combines guest information with reservation details.
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
    provider: GuestReservationDtoProvider::class
)]
class GuestReservationDto
{
    /**
     * Constructor for GuestReservationDto.
     *
     * @example // prefix of all roles: ROLE_SYSTEMBFF-GUESTRESERVATIONDTO_
     * 
     * @param string            $id             Guest identifier
     * @param string            $reservationId  Reservation identifier
     * @param string            $name           Full name
     * @param string            $email          Email address
     * @param string            $nationality    Guest nationality
     * @param DateTimeInterface $birthDate      Guest birth date
     * @param DateTimeInterface $checkInDate    Check-in date
     * @param DateTimeInterface $checkOutDate   Check-out date
     * @param string            $roomNumber     Room number
     */
    public function __construct(
        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS']
            ]
        )]
        #[Assert\NotBlank]
        public string $id = '',

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS']
            ]
        )]
        #[Assert\NotBlank]
        public string $reservationId = '',

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS']
            ]
        )]
        #[Assert\NotBlank]
        #[Assert\Length(min: 3)]
        public string $name = '',

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['']
            ]
        )]
        #[Assert\NotNull]
        #[Assert\Email]
        public ?string $email = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS']
            ]
        )]
        #[Assert\NotBlank]
        public string $nationality = '',

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS']
            ]
        )]
        #[Assert\NotNull]
        public ?DateTimeInterface $birthDate = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS']
            ]
        )]
        #[Assert\NotNull]
        public ?DateTimeInterface $checkInDate = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS']
            ]
        )]
        #[Assert\NotNull]
        public ?DateTimeInterface $checkOutDate = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS']
            ]
        )]
        #[Assert\NotBlank]
        public string $roomNumber = ''
    ) {}
}
