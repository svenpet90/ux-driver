<?php

declare(strict_types=1);

use SvenPetersen\UX\Driver\Action\MarkTourSeenAction;
use SvenPetersen\UX\Driver\Twig\UXDriverExtension;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import this once per firewall, with that area's prefix, so the user is authenticated when
 * the endpoint is called — see the README.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(UXDriverExtension::SEEN_ROUTE, '/ux-driver/tours/{tourId}/seen')
        ->controller(MarkTourSeenAction::class)
        ->methods(['POST'])
        ->requirements([
            'tourId' => '[A-Za-z0-9._-]+',
        ]);
};
