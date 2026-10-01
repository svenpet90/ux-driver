<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Fixtures;

use SvenPetersen\UX\Driver\Persistence\AsTourViewManager;
use SvenPetersen\UX\Driver\Persistence\TourViewInterface;
use SvenPetersen\UX\Driver\Persistence\TourViewManagerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Keeps the records in memory. Registered for InMemoryUser through the attribute, the way an
 * application registers its Doctrine-backed manager for its user entity.
 */
#[AsTourViewManager(userClass: InMemoryUser::class)]
final class InMemoryTourViewManager implements TourViewManagerInterface
{
    /** @var array<string, TourViewInterface> */
    private array $views = [];

    public function find(UserInterface $user, string $tourId): ?TourViewInterface
    {
        return $this->views[self::key($user, $tourId)] ?? null;
    }

    public function factory(UserInterface $user, string $tourId): TourViewInterface
    {
        return new InMemoryTourView($user, $tourId);
    }

    public function save(TourViewInterface $tourView): void
    {
        $this->views[self::key($tourView->getUser(), $tourView->getTourId())] = $tourView;
    }

    /**
     * @return list<TourViewInterface>
     */
    public function all(): array
    {
        return array_values($this->views);
    }

    private static function key(UserInterface $user, string $tourId): string
    {
        return $user->getUserIdentifier() . '|' . $tourId;
    }
}
