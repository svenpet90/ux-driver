<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Functional;

use SvenPetersen\UX\Driver\Tests\Fixtures\TestKernel;
use SvenPetersen\UX\Driver\Tests\Fixtures\TestTourProvider;
use SvenPetersen\UX\Driver\Twig\UXDriverExtension;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class TourRenderingTest extends WebTestCase
{
    use ContainerServiceTrait;

    private const string ATTRIBUTE = 'data-svenpetersen--ux-driver--tour-';

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testALoggedInUserWhoHasNotSeenTheTourGetsItStartedByItself(): void
    {
        $button = $this->button($this->loggedInClient());

        self::assertSame('svenpetersen--ux-driver--tour', $button->attr('data-controller'));
        self::assertSame('click->svenpetersen--ux-driver--tour#start', $button->attr('data-action'));
        self::assertSame(TestTourProvider::ID, $button->attr(self::ATTRIBUTE . 'id-value'));
        self::assertSame('true', $button->attr(self::ATTRIBUTE . 'autostart-value'));
        self::assertSame('/ux-driver/tours/test-tour-1.0/seen', $button->attr(self::ATTRIBUTE . 'seen-url-value'));
        self::assertNotEmpty($button->attr(self::ATTRIBUTE . 'csrf-token-value'));
    }

    public function testTheStepsAreRenderedForTheController(): void
    {
        $steps = json_decode((string) $this->button($this->loggedInClient())->attr(self::ATTRIBUTE . 'steps-value'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(json_decode(json_encode(new TestTourProvider()->create()->steps, \JSON_THROW_ON_ERROR), true), $steps);
        self::assertSame(['/tour', '/tour', '/elsewhere'], array_column($steps, 'page'));
    }

    public function testOnlyTheLabelsThatAreGivenAreRendered(): void
    {
        $button = $this->button($this->loggedInClient());

        self::assertSame('Weiter', $button->attr(self::ATTRIBUTE . 'next-label-value'));
        self::assertNull($button->attr(self::ATTRIBUTE . 'done-label-value'), 'Unset labels keep the controller\'s defaults.');
    }

    /**
     * Without a user there is nobody to remember "seen" for; the tour would start on every visit.
     */
    public function testAnAnonymousVisitorDoesNotGetTheTourStartedByItself(): void
    {
        $button = $this->button(self::createClient());

        self::assertSame('false', $button->attr(self::ATTRIBUTE . 'autostart-value'));
    }

    public function testAnUnknownLabelIsRejected(): void
    {
        self::bootKernel();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown tour label(s) "nxet"');

        self::service(UXDriverExtension::class)->renderTour(TestTourProvider::ID, [
            'nxet' => 'Weiter',
        ]);
    }

    public function testAnUnknownTourIsRejected(): void
    {
        self::bootKernel();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no tour "nope"');

        self::service(UXDriverExtension::class)->renderTour('nope');
    }

    private function loggedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(new InMemoryUser('alice', 'secret', ['ROLE_USER']), 'main');

        return $client;
    }

    private function button(KernelBrowser $client): Crawler
    {
        $crawler = $client->request('GET', '/tour');
        self::assertResponseIsSuccessful();

        return $crawler->filter('button[data-tour="button"]');
    }
}
