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
 * @version  GIT: <git_id>
 * @link     https://apiplatform.com
 */

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Attribute\AllowedRoles;
use App\Attribute\MicroserviceField;
use App\Attribute\MicroserviceRelationship;
use App\State\GuestReservationDto\GuestReservationDtoProvider;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

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
     * @param string            $id            Guest identifier
     * @param string            $reservationId Reservation identifier
     * @param string            $name          Full name
     * @param string            $email         Email address
     * @param string            $nationality   Guest nationality
     * @param DateTimeInterface $birthDate     Guest birth date
     * @param DateTimeInterface $checkInDate   Check-in date
     * @param DateTimeInterface $checkOutDate  Check-out date
     * @param string            $roomNumber    Room number
     */
    public function __construct(
        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS'],
                'selfcheckin' => ['ACCESS']
            ]
        )]
        #[MicroserviceField(
            microservice: 'guest-service',
            entity: 'Guest',
            field: 'id'
        )]
        #[Assert\NotBlank]
        public string $id = '',

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'workspace' => ['ACCESS'],
                'distributor' => ['ACCESS'],
                'selfcheckin' => ['ACCESS']
            ]
        )]
        #[MicroserviceRelationship(
            new MicroserviceField(
                microservice: 'guest-service',
                entity: 'Guest',
                field: 'reservationId'
            ),
            new MicroserviceField(
                microservice: 'reservation-service',
                entity: 'Reservation',
                field: 'id'
            )
        )]
        #[Assert\NotBlank]
        public string $reservationId = '',

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'distributor' => ['ACCESS'],
            ]
        )]
        #[MicroserviceField(
            microservice: 'guest-service',
            entity: 'Guest',
            field: 'fullName'
        )]
        #[Assert\NotBlank]
        #[Assert\Length(min: 3)]
        public ?string $name = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
            ]
        )]
        #[MicroserviceField(
            microservice: 'guest-service',
            entity: 'Guest',
            field: 'email'
        )]
        #[Assert\NotNull]
        #[Assert\Email]
        public ?string $email = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
            ]
        )]
        #[MicroserviceField(
            microservice: 'guest-service',
            entity: 'Guest',
            field: 'countryCode'
        )]
        #[Assert\NotBlank]
        public ?string $nationality = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
            ]
        )]
        #[MicroserviceField(
            microservice: 'guest-service',
            entity: 'Guest',
            field: 'dateOfBirth'
        )]
        #[Assert\NotNull]
        public ?DateTimeInterface $birthDate = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
            ]
        )]
        #[MicroserviceField(
            microservice: 'reservation-service',
            entity: 'Reservation',
            field: 'arrivalDate'
        )]
        #[Assert\NotNull]
        public ?DateTimeInterface $checkInDate = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
            ]
        )]
        #[MicroserviceField(
            microservice: 'reservation-service',
            entity: 'Reservation',
            field: 'departureDate'
        )]
        #[Assert\NotNull]
        public ?DateTimeInterface $checkOutDate = null,

        #[AllowedRoles(
            [
                'admin' => ['ACCESS'],
                'distributor' => [''],
                'selfcheckin' => ['ACCESS']
            ]
        )]
        #[MicroserviceField(
            microservice: 'reservation-service',
            entity: 'Reservation',
            field: 'roomNumber'
        )]
        #[Assert\NotBlank]
        public ?string $roomNumber = null
    ) {}
}
