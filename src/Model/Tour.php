<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Model;

/**
 * A tour, played by the bundle's Stimulus controller.
 *
 * The id doubles as the key under which a user's "seen" state is stored. A tour for a new
 * release therefore gets a new id (e.g. with the version in it) and shows up for everyone again.
 */
final readonly class Tour
{
    /**
     * @param list<Step> $steps
     */
    public function __construct(
        public string $id,
        public array $steps,
    ) {
    }
}
