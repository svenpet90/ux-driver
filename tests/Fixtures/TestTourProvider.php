<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Fixtures;

use SvenPetersen\UX\Driver\Model\Side;
use SvenPetersen\UX\Driver\Model\Step;
use SvenPetersen\UX\Driver\Model\Tour;
use SvenPetersen\UX\Driver\Provider\TourProviderInterface;

final class TestTourProvider implements TourProviderInterface
{
    public const string ID = 'test-tour-1.0';

    public static function getTourId(): string
    {
        return self::ID;
    }

    public function create(): Tour
    {
        return new Tour(self::ID, [
            new Step('/tour', null, 'Welcome', 'A short tour.'),
            new Step('/tour', '[data-tour="button"]', 'Restart', 'Click here to see it again.', Side::LEFT),
            new Step('/elsewhere', '#chart', 'Elsewhere', 'This step lives on another page.'),
        ]);
    }
}
