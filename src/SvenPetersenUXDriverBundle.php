<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver;

use SvenPetersen\UX\Driver\Persistence\AsTourViewManager;
use SvenPetersen\UX\Driver\Provider\TourProviderInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Product tours with driver.js; the "seen" state is stored per user.
 *
 * Built like bentools/webpush-bundle: the bundle defines the interfaces
 * ({@see Persistence\TourViewInterface}, {@see Persistence\TourViewManagerInterface}), the
 * application maps them onto its user entity and registers its manager with
 * {@see AsTourViewManager}.
 *
 * Works with Webpack Encore (via @symfony/stimulus-bridge) and with AssetMapper: when AssetMapper
 * is available, the bundle exposes `assets/dist` as `@svenpetersen/ux-driver`.
 */
final class SvenPetersenUXDriverBundle extends AbstractBundle
{
    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        // An #[AutoconfigureTag] on the interface would only apply to interfaces inside the
        // application's own resource directories, not to one shipped in vendor/.
        $builder->registerForAutoconfiguration(TourProviderInterface::class)
            ->addTag(TourProviderInterface::TAG);

        $builder->registerAttributeForAutoconfiguration(
            AsTourViewManager::class,
            static function (ChildDefinition $definition, AsTourViewManager $attribute): void {
                $definition->addTag(AsTourViewManager::TAG, [
                    'user_class' => $attribute->userClass,
                ]);
            },
        );
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->isAssetMapperAvailable($builder)) {
            return;
        }

        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    $this->getPath() . '/assets/dist' => '@svenpetersen/ux-driver',
                ],
            ],
        ]);
    }

    /**
     * Same check as the Symfony UX bundles: prepending `asset_mapper` config without AssetMapper
     * being set up would break (or silently enable) it in an Encore application.
     */
    private function isAssetMapperAvailable(ContainerBuilder $builder): bool
    {
        if (!interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        // Before Symfony 8.2, FrameworkBundle provided the AssetMapper configuration.
        $bundlesMetadata = $builder->getParameter('kernel.bundles_metadata');

        if (!\is_array($bundlesMetadata) || !isset($bundlesMetadata['FrameworkBundle']['path'])) {
            return false;
        }

        return isset($bundlesMetadata['AssetMapperBundle'])
            || is_file($bundlesMetadata['FrameworkBundle']['path'] . '/Resources/config/asset_mapper.php');
    }
}
