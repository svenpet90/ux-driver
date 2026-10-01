<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Persistence;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Loads and stores {@see TourViewInterface} records for one user class.
 *
 * Register the implementation for its user class with {@see AsTourViewManager}. There may be
 * several, one per user class.
 */
interface TourViewManagerInterface
{
    public function find(UserInterface $user, string $tourId): ?TourViewInterface;

    /**
     * Creates a new record that has not been stored yet.
     */
    public function factory(UserInterface $user, string $tourId): TourViewInterface;

    public function save(TourViewInterface $tourView): void;
}
