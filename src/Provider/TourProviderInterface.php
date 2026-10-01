<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Provider;

use SvenPetersen\UX\Driver\Model\Tour;

/**
 * Provides one tour. Implementations are tagged automatically (the bundle registers the
 * interface for autoconfiguration) and looked up by `getTourId()` — a template then only needs
 * `ux_driver_tour('<id>')`.
 */
interface TourProviderInterface
{
    public const string TAG = 'svenpetersen_ux_driver.tour_provider';

    public static function getTourId(): string;

    public function create(): Tour;
}
