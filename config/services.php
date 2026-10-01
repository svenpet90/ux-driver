<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use SvenPetersen\UX\Driver\Action\MarkTourSeenAction;
use SvenPetersen\UX\Driver\Persistence\AsTourViewManager;
use SvenPetersen\UX\Driver\Persistence\TourViewStore;
use SvenPetersen\UX\Driver\Provider\TourProviderInterface;
use SvenPetersen\UX\Driver\Provider\TourRegistry;
use SvenPetersen\UX\Driver\Twig\UXDriverExtension;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(TourRegistry::class)
            ->args([
                tagged_locator(TourProviderInterface::TAG, defaultIndexMethod: 'getTourId'),
            ])

        ->set(TourViewStore::class)
            ->args([
                tagged_iterator(AsTourViewManager::TAG, indexAttribute: 'user_class'),
            ])

        ->set(MarkTourSeenAction::class)
            ->public()
            ->args([
                service(TourRegistry::class),
                service(TourViewStore::class),
                service('security.csrf.token_manager'),
            ])
            ->tag('controller.service_arguments')

        ->set(UXDriverExtension::class)
            ->args([
                service(TourRegistry::class),
                service(TourViewStore::class),
                service('stimulus.helper'),
                service('router'),
                service('security.csrf.token_manager'),
                service('security.helper'),
            ])
            ->tag('twig.extension');
};
