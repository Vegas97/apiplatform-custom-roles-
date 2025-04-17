<?php

/**
 * Guest Reservation DTO Provider for API Platform.
 *
 * PHP version 8.4
 *
 * @category State
 * @package  App\State
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 * @version  GIT: <git_id>
 */

declare(strict_types=1);

namespace App\State\GuestReservationDto;

use DateTime;
use App\Dto\EntityMappingDto;
use App\State\AbstractDtoProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\GuestReservationDto;

/**
 * Provider for GuestReservationDto resources with role-based access control.
 *
 * @category State
 * @package  App\State
 * @author   API Platform Team <contact@api-platform.com>
 * @license  MIT License
 * @link     https://apiplatform.com
 */
class GuestReservationDtoProvider extends AbstractDtoProvider implements ProviderInterface
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
        return GuestReservationDto::class;
    }

    /**
     * Get items for the provider.
     *
     * @return array Array of GuestReservationDto objects
     */
    protected function getItems(): array
    {
        // Sample data - in a real app, this would come from a database or other source
        return [
            new GuestReservationDto(
                '1',
                'RES-10001',
                'John Smith',
                'john.smith@example.com',
                'United States',
                new DateTime('1985-03-15'),
                new DateTime('2025-04-05'),
                new DateTime('2025-04-12'),
                '101'
            ),
            new GuestReservationDto(
                '2',
                'RES-10002',
                'Maria Garcia',
                'maria.garcia@example.com',
                'Spain',
                new DateTime('1992-07-22'),
                new DateTime('2025-04-08'),
                new DateTime('2025-04-15'),
                '203'
            ),
            new GuestReservationDto(
                '3',
                'RES-10003',
                'Akira Tanaka',
                'akira.tanaka@example.com',
                'Japan',
                new DateTime('1978-11-30'),
                new DateTime('2025-04-10'),
                new DateTime('2025-04-17'),
                '305'
            ),
            new GuestReservationDto(
                '4',
                'RES-10004',
                'Sophie Dubois',
                'sophie.dubois@example.com',
                'France',
                new DateTime('1990-05-18'),
                new DateTime('2025-04-09'),
                new DateTime('2025-04-14'),
                '402'
            ),
            new GuestReservationDto(
                '5',
                'RES-10005',
                'Ahmed Hassan',
                'ahmed.hassan@example.com',
                'Egypt',
                new DateTime('1982-09-25'),
                new DateTime('2025-04-07'),
                new DateTime('2025-04-16'),
                '504'
            )
        ];
    }
}
