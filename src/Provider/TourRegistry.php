<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Provider;

use Psr\Container\ContainerInterface;
use SvenPetersen\UX\Driver\Model\Tour;

/**
 * All tours of the application, by id. The providers sit in a service locator and are only
 * instantiated once their tour is needed.
 */
final readonly class TourRegistry
{
    public function __construct(
        private ContainerInterface $providers,
    ) {
    }

    public function has(string $tourId): bool
    {
        return $this->providers->has($tourId);
    }

    /**
     * @throws \InvalidArgumentException when there is no tour with this id
     * @throws \LogicException           when a provider returns a tour with a different id
     */
    public function get(string $tourId): Tour
    {
        if (!$this->has($tourId)) {
            throw new \InvalidArgumentException(\sprintf('There is no tour "%s". A tour needs a service implementing %s.', $tourId, TourProviderInterface::class));
        }

        $provider = $this->providers->get($tourId);
        \assert($provider instanceof TourProviderInterface);

        $tour = $provider->create();

        if ($tour->id !== $tourId) {
            throw new \LogicException(\sprintf('%s reports the id "%s" but returns a tour "%s".', $provider::class, $tourId, $tour->id));
        }

        return $tour;
    }
}
