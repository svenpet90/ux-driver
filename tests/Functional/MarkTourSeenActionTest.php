<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Functional;

use SvenPetersen\UX\Driver\Tests\Fixtures\InMemoryTourViewManager;
use SvenPetersen\UX\Driver\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The endpoint the controller reports the end of a tour to.
 *
 * The client does not reboot the kernel between requests, so the in-memory manager keeps its
 * records from one request to the next — the way a database would.
 */
final class MarkTourSeenActionTest extends WebTestCase
{
    use ContainerServiceTrait;

    private const string ATTRIBUTE = 'data-svenpetersen--ux-driver--tour-';
    private const string SEEN_URL = '/ux-driver/tours/test-tour-1.0/seen';

    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
    }

    public function testATourMarkedAsSeenNoLongerStartsByItself(): void
    {
        $this->logIn();

        $this->markSeen(self::SEEN_URL, $this->csrfToken());

        self::assertResponseStatusCodeSame(204);
        self::assertSame('false', $this->button()->attr(self::ATTRIBUTE . 'autostart-value'));
    }

    public function testMarkingATourAsSeenTwiceIsNoError(): void
    {
        $this->logIn();
        $token = $this->csrfToken();

        $this->markSeen(self::SEEN_URL, $token);
        $this->markSeen(self::SEEN_URL, $token);

        self::assertResponseStatusCodeSame(204);
        self::assertCount(1, $this->manager()->all());
    }

    public function testAnAnonymousVisitorCannotMarkATourAsSeen(): void
    {
        $this->markSeen(self::SEEN_URL, '');

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->manager()->all());
    }

    public function testMarkingATourAsSeenNeedsTheCsrfToken(): void
    {
        $this->logIn();
        $this->csrfToken();

        $this->markSeen(self::SEEN_URL, 'forged');

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->manager()->all());
    }

    /**
     * Otherwise any logged-in user could fill the table with made-up ids.
     */
    public function testOnlyExistingToursCanBeMarkedAsSeen(): void
    {
        $this->logIn();

        $this->markSeen('/ux-driver/tours/made-up/seen', $this->csrfToken());

        self::assertResponseStatusCodeSame(404);
        self::assertCount(0, $this->manager()->all());
    }

    public function testTheEndpointOnlyAcceptsPost(): void
    {
        $this->logIn();

        $this->client->request('GET', self::SEEN_URL);

        self::assertResponseStatusCodeSame(405);
    }

    private function logIn(): void
    {
        $this->client->loginUser(new InMemoryUser('alice', 'secret', ['ROLE_USER']), 'main');
    }

    /**
     * The token the page hands the controller — it lives in the session of this client.
     */
    private function csrfToken(): string
    {
        return (string) $this->button()->attr(self::ATTRIBUTE . 'csrf-token-value');
    }

    private function button(): Crawler
    {
        $crawler = $this->client->request('GET', '/tour');
        self::assertResponseIsSuccessful();

        return $crawler->filter('button[data-tour="button"]');
    }

    private function markSeen(string $url, string $csrfToken): void
    {
        $this->client->request('POST', $url, server: [
            'HTTP_X_CSRF_TOKEN' => $csrfToken,
        ]);
    }

    private function manager(): InMemoryTourViewManager
    {
        return self::service(InMemoryTourViewManager::class);
    }
}
