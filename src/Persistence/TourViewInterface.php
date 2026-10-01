<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Persistence;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user has seen a tour.
 *
 * The bundle ships no entity: the application maps this interface onto its own user entity
 * (mapping, table and migration are the application's) and provides a matching
 * {@see TourViewManagerInterface}.
 */
interface TourViewInterface
{
    public function getUser(): UserInterface;

    public function getTourId(): string;

    public function getSeenAt(): \DateTimeImmutable;
}
