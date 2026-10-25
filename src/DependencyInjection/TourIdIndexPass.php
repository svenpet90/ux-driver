<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\DependencyInjection;

use SvenPetersen\UX\Driver\Provider\TourProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Writes each tour provider's id into its tag, as the `tour_id` attribute the registry's locator
 * is indexed by.
 *
 * The locator used to ask the providers themselves (`defaultIndexMethod: 'getTourId'`), which
 * symfony/dependency-injection deprecates since 8.1 in favour of #[AsTaggedItem] — an attribute
 * every application would have to repeat on every provider, next to a getTourId() returning the
 * same id. getTourId() is static, so the id is known at compile time: the bundle reads it here
 * once, and providers need nothing but the interface.
 *
 * Runs before optimisation, after autoconfiguration has tagged the providers and before the
 * tagged locator is resolved.
 */
final class TourIdIndexPass implements CompilerPassInterface
{
    public const string INDEX_ATTRIBUTE = 'tour_id';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds(TourProviderInterface::TAG) as $id => $tags) {
            $definition = $container->findDefinition($id);
            $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);

            if (!\is_string($class) || !is_subclass_of($class, TourProviderInterface::class)) {
                throw new InvalidArgumentException(\sprintf('Service "%s" is tagged "%s" but does not implement "%s".', $id, TourProviderInterface::TAG, TourProviderInterface::class));
            }

            $tourId = $class::getTourId();
            $definition->clearTag(TourProviderInterface::TAG);

            foreach ($tags as $attributes) {
                $definition->addTag(TourProviderInterface::TAG, $attributes + [self::INDEX_ATTRIBUTE => $tourId]);
            }
        }
    }
}
