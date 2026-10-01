<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Unit\Provider;

use PHPUnit\Framework\TestCase;
use SvenPetersen\UX\Driver\Model\Tour;
use SvenPetersen\UX\Driver\Provider\TourProviderInterface;
use SvenPetersen\UX\Driver\Provider\TourRegistry;
use SvenPetersen\UX\Driver\Tests\Fixtures\TestTourProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class TourRegistryTest extends TestCase
{
    public function testItFindsATourByItsId(): void
    {
        $registry = $this->registry([
            TestTourProvider::ID => new TestTourProvider(),
        ]);

        self::assertTrue($registry->has(TestTourProvider::ID));
        self::assertSame(TestTourProvider::ID, $registry->get(TestTourProvider::ID)->id);
        self::assertCount(3, $registry->get(TestTourProvider::ID)->steps);
    }

    public function testAnUnknownTourFailsLoudly(): void
    {
        $registry = $this->registry([]);

        self::assertFalse($registry->has('nope'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no tour "nope"');

        $registry->get('nope');
    }

    /**
     * The id is the key the "seen" state is stored under. A provider whose tour carries a
     * different id would store "seen" under one key and look it up under another.
     */
    public function testAProviderMustReturnTheTourItIsRegisteredFor(): void
    {
        $registry = $this->registry([
            'announced' => new class() implements TourProviderInterface {
                public static function getTourId(): string
                {
                    return 'announced';
                }

                public function create(): Tour
                {
                    return new Tour('delivered', []);
                }
            },
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('reports the id "announced" but returns a tour "delivered"');

        $registry->get('announced');
    }

    /**
     * @param array<string, TourProviderInterface> $providers
     */
    private function registry(array $providers): TourRegistry
    {
        return new TourRegistry(new ServiceLocator(array_map(
            static fn (TourProviderInterface $provider): \Closure => static fn (): TourProviderInterface => $provider,
            $providers,
        )));
    }
}
