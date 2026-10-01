<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Persistence;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Per-user "seen" state: picks the manager registered for the user's class.
 *
 * The match is made with `instanceof` rather than by class name, so a Doctrine proxy or a
 * subclass still finds its manager.
 */
final readonly class TourViewStore
{
    /**
     * @param iterable<class-string<UserInterface>, TourViewManagerInterface> $managers user class => manager
     */
    public function __construct(
        private iterable $managers,
    ) {
    }

    public function hasSeen(UserInterface $user, string $tourId): bool
    {
        return $this->managerFor($user)->find($user, $tourId) !== null;
    }

    /**
     * Idempotent: finishing a tour a second time keeps the first record.
     */
    public function markSeen(UserInterface $user, string $tourId): void
    {
        $manager = $this->managerFor($user);

        if ($manager->find($user, $tourId) !== null) {
            return;
        }

        $manager->save($manager->factory($user, $tourId));
    }

    /**
     * @throws \LogicException when no manager is registered for the user's class
     */
    private function managerFor(UserInterface $user): TourViewManagerInterface
    {
        foreach ($this->managers as $userClass => $manager) {
            if ($user instanceof $userClass) {
                return $manager;
            }
        }

        throw new \LogicException(\sprintf('No %s is registered for %s. A service with #[%s(userClass: ...)] is missing.', TourViewManagerInterface::class, $user::class, AsTourViewManager::class));
    }
}
