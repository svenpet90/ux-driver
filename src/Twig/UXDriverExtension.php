<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Twig;

use SvenPetersen\UX\Driver\Action\MarkTourSeenAction;
use SvenPetersen\UX\Driver\Persistence\TourViewStore;
use SvenPetersen\UX\Driver\Provider\TourRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\UX\StimulusBundle\Helper\StimulusHelper;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * `ux_driver_tour('<id>')` renders the attributes for the element that carries the tour —
 * usually the button that restarts it. The button's markup stays with the application.
 *
 * Whether the tour starts by itself is decided on the server: only for a logged-in user who
 * has not seen it yet.
 */
final class UXDriverExtension extends AbstractExtension
{
    public const string CONTROLLER = '@svenpetersen/ux-driver/tour';
    public const string SEEN_ROUTE = 'ux_driver_tour_seen';

    /** Button and progress texts the controller accepts; the defaults live in the controller. */
    private const array LABELS = ['next', 'previous', 'done', 'progress'];

    public function __construct(
        private readonly TourRegistry $tours,
        private readonly TourViewStore $tourViews,
        private readonly StimulusHelper $stimulus,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ux_driver_tour', $this->renderTour(...), [
                'is_safe' => ['html'],
            ]),
        ];
    }

    /**
     * @param array<string, string> $labels    any of `next`, `previous`, `done` and `progress`
     *                                         (`{{current}}` and `{{total}}` are replaced)
     * @param string                $seenRoute name of the route to {@see MarkTourSeenAction} in the
     *                                         firewall of the current page — set it when the routes
     *                                         are imported a second time, e.g. for another area
     *
     * @throws \InvalidArgumentException when the tour does not exist or a label is unknown
     */
    public function renderTour(string $tourId, array $labels = [], string $seenRoute = self::SEEN_ROUTE): Markup
    {
        $unknown = array_diff(array_keys($labels), self::LABELS);

        if ($unknown !== []) {
            throw new \InvalidArgumentException(\sprintf('Unknown tour label(s) "%s". Allowed are: %s.', implode('", "', $unknown), implode(', ', self::LABELS)));
        }

        $tour = $this->tours->get($tourId);
        $user = $this->security->getUser();

        $values = [
            'id' => $tour->id,
            'steps' => $tour->steps,
            'autostart' => $user !== null && !$this->tourViews->hasSeen($user, $tour->id),
            'seenUrl' => $this->urlGenerator->generate($seenRoute, [
                'tourId' => $tour->id,
            ]),
            'csrfToken' => $this->csrfTokenManager->getToken(MarkTourSeenAction::CSRF_TOKEN_ID)->getValue(),
        ];

        foreach ($labels as $name => $text) {
            $values[$name . 'Label'] = $text;
        }

        $attributes = $this->stimulus->createStimulusAttributes();
        $attributes->addController(self::CONTROLLER, $values);
        $attributes->addAction(self::CONTROLLER, 'start', 'click');

        return new Markup((string) $attributes, 'UTF-8');
    }
}
