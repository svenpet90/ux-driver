<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Unit\Persistence;

use PHPUnit\Framework\TestCase;
use SvenPetersen\UX\Driver\Persistence\TourViewStore;
use SvenPetersen\UX\Driver\Tests\Fixtures\InMemoryTourViewManager;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class TourViewStoreTest extends TestCase
{
    public function testATourIsUnseenUntilItIsMarkedAsSeen(): void
    {
        $manager = new InMemoryTourViewManager();
        $store = new TourViewStore([
            InMemoryUser::class => $manager,
        ]);
        $alice = new InMemoryUser('alice', null);

        self::assertFalse($store->hasSeen($alice, 'tour'));

        $store->markSeen($alice, 'tour');

        self::assertTrue($store->hasSeen($alice, 'tour'));
        self::assertFalse($store->hasSeen($alice, 'another-tour'));
        self::assertFalse($store->hasSeen(new InMemoryUser('bob', null), 'tour'), 'Seen is per user.');
    }

    public function testMarkingATourAsSeenTwiceKeepsTheFirstRecord(): void
    {
        $manager = new InMemoryTourViewManager();
        $store = new TourViewStore([
            InMemoryUser::class => $manager,
        ]);
        $alice = new InMemoryUser('alice', null);

        $store->markSeen($alice, 'tour');
        $first = $manager->find($alice, 'tour');

        $store->markSeen($alice, 'tour');

        self::assertCount(1, $manager->all());
        self::assertSame($first, $manager->find($alice, 'tour'));
    }

    /**
     * The manager is chosen with `instanceof`, so one registered for a parent class or an
     * interface also serves a Doctrine proxy or a subclass of it.
     */
    public function testAManagerRegisteredForAParentTypeServesItsSubtypes(): void
    {
        $store = new TourViewStore([
            UserInterface::class => new InMemoryTourViewManager(),
        ]);

        $store->markSeen(new InMemoryUser('alice', null), 'tour');

        self::assertTrue($store->hasSeen(new InMemoryUser('alice', null), 'tour'));
    }

    public function testAUserClassWithoutAManagerFailsLoudly(): void
    {
        $store = new TourViewStore([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No SvenPetersen\UX\Driver\Persistence\TourViewManagerInterface is registered for Symfony\Component\Security\Core\User\InMemoryUser');

        $store->hasSeen(new InMemoryUser('alice', null), 'tour');
    }
}
