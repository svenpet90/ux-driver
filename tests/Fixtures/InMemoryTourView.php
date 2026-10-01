<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Fixtures;

use SvenPetersen\UX\Driver\Persistence\TourViewInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class InMemoryTourView implements TourViewInterface
{
    private \DateTimeImmutable $seenAt;

    public function __construct(
        private UserInterface $user,
        private string $tourId,
    ) {
        $this->seenAt = new \DateTimeImmutable();
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    public function getTourId(): string
    {
        return $this->tourId;
    }

    public function getSeenAt(): \DateTimeImmutable
    {
        return $this->seenAt;
    }
}
