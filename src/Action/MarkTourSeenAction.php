<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Action;

use SvenPetersen\UX\Driver\Persistence\TourViewStore;
use SvenPetersen\UX\Driver\Provider\TourRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Records that the logged-in user has seen a tour.
 *
 * The bundle only defines *what* the endpoint does; *where* it lives is up to the application
 * when it imports the routes — once per firewall, so the user is authenticated there.
 *
 * Only existing tours are stored; otherwise the table could be filled with arbitrary ids. The
 * CSRF token travels in a header sent by the Stimulus controller.
 */
final readonly class MarkTourSeenAction
{
    public const string CSRF_TOKEN_ID = 'ux_driver_tour';
    public const string CSRF_HEADER = 'X-CSRF-Token';

    public function __construct(
        private TourRegistry $tours,
        private TourViewStore $tourViews,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request, string $tourId, #[CurrentUser] ?UserInterface $user = null): Response
    {
        if ($user === null) {
            throw new AccessDeniedHttpException('Not authenticated.');
        }

        $token = new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->headers->get(self::CSRF_HEADER));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        if (!$this->tours->has($tourId)) {
            throw new NotFoundHttpException(\sprintf('There is no tour "%s".', $tourId));
        }

        $this->tourViews->markSeen($user, $tourId);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
