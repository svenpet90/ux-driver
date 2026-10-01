<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Persistence;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Registers a {@see TourViewManagerInterface} for a user class.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsTourViewManager
{
    public const string TAG = 'svenpetersen_ux_driver.tour_view_manager';

    /**
     * @param class-string<UserInterface> $userClass
     */
    public function __construct(
        public string $userClass,
    ) {
    }
}
