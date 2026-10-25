<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Functional;

use SvenPetersen\UX\Driver\Persistence\TourViewStore;
use SvenPetersen\UX\Driver\Provider\TourRegistry;
use SvenPetersen\UX\Driver\Tests\Fixtures\TestKernel;
use SvenPetersen\UX\Driver\Tests\Fixtures\TestTourProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * What an application gets by only implementing the interfaces: no service definitions, no tags.
 */
final class BundleWiringTest extends KernelTestCase
{
    use ContainerServiceTrait;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * An #[AutoconfigureTag] on the interface would not reach implementations outside the
     * application's resource directories; the bundle registers the autoconfiguration itself.
     */
    public function testATourProviderIsFoundWithoutAnyConfiguration(): void
    {
        $registry = self::service(TourRegistry::class);

        self::assertTrue($registry->has(TestTourProvider::ID));
    }

    /**
     * Since symfony/dependency-injection 8.1, indexing a locator by a method of its services is
     * deprecated. The bundle reads the tour id at compile time instead — building the container
     * must not leave a deprecation behind, neither for the locator nor for any provider.
     */
    public function testBuildingTheContainerTriggersNoDeprecation(): void
    {
        $kernel = new TestKernel('deprecations', true);
        $filesystem = new Filesystem();
        $filesystem->remove($kernel->getCacheDir());

        // Under PHPUnit the kernel does not collect deprecations while building its container
        // (no Deprecations.log); catch them here instead. They are triggered silenced, so only an
        // error handler sees them.
        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED | \E_DEPRECATED);

        try {
            $kernel->boot();
        } finally {
            restore_error_handler();
        }

        try {
            self::assertSame([], array_values(array_filter(
                $deprecations,
                static fn (string $message): bool => str_contains($message, 'tour_provider') || str_contains($message, 'getTourId') || str_contains($message, '$defaultIndexMethod'),
            )));
        } finally {
            $kernel->shutdown();
            $filesystem->remove($kernel->getCacheDir());
        }
    }

    public function testATourViewManagerIsRegisteredThroughItsAttribute(): void
    {
        $store = self::service(TourViewStore::class);
        $alice = new InMemoryUser('alice', null);

        $store->markSeen($alice, TestTourProvider::ID);

        self::assertTrue($store->hasSeen($alice, TestTourProvider::ID));
    }

    public function testTheControllerIsExposedToAssetMapper(): void
    {
        $asset = self::service(AssetMapperInterface::class)
            ->getAsset('@svenpetersen/ux-driver/controller.js');

        self::assertNotNull($asset, 'AssetMapper does not know the bundle\'s controller.');
        self::assertSame(realpath(\dirname(__DIR__, 2) . '/assets/dist/controller.js'), realpath($asset->sourcePath));
    }
}
