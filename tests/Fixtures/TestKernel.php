<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Fixtures;

use Psr\Log\NullLogger;
use SvenPetersen\UX\Driver\SvenPetersenUXDriverBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\StimulusBundle\StimulusBundle;

/**
 * A minimal application around the bundle: one in-memory user, one tour, one page rendering it.
 *
 * The fixture services are autowired and autoconfigured like application services, so the tests
 * exercise the bundle's autoconfiguration (TourProviderInterface, #[AsTourViewManager]) too.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new SecurityBundle();
        yield new StimulusBundle();
        yield new SvenPetersenUXDriverBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/svenpetersen-ux-driver/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/svenpetersen-ux-driver/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => [
                'log' => true,
            ],
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
            'csrf_protection' => true,
            'router' => [
                'utf8' => true,
            ],
        ]);

        $container->extension('twig', [
            'strict_variables' => true,
        ]);

        $container->extension('security', [
            'providers' => [
                'memory' => [
                    'memory' => [
                        'users' => [
                            'alice' => [
                                'password' => 'secret',
                                'roles' => ['ROLE_USER'],
                            ],
                        ],
                    ],
                ],
            ],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'memory',
                ],
            ],
        ]);

        // The endpoint tests provoke 403, 404 and 405 on purpose; keep them off stderr.
        $container->services()->set('logger', NullLogger::class);

        $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure()
            ->load(__NAMESPACE__ . '\\', __DIR__)
                ->exclude([__DIR__ . '/TestKernel.php', __DIR__ . '/InMemoryTourView.php']);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@SvenPetersenUXDriverBundle/config/routes.php');
        $routes->add('tour_page', '/tour')->controller(TourPageController::class);
    }
}
