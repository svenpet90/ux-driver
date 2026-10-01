<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Functional;

use SvenPetersen\UX\Driver\Persistence\TourViewStore;
use SvenPetersen\UX\Driver\Provider\TourRegistry;
use SvenPetersen\UX\Driver\Tests\Fixtures\TestKernel;
use SvenPetersen\UX\Driver\Tests\Fixtures\TestTourProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
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
