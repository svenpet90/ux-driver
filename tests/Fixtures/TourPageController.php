<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Twig\Environment;

/**
 * The page a test application would render: one button carrying the tour.
 */
#[AsController]
final readonly class TourPageController
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function __invoke(): Response
    {
        $template = $this->twig->createTemplate(
            '<button type="button" data-tour="button" {{ ux_driver_tour(id, {next: "Weiter"}) }}>Tour</button>',
        );

        return new Response($template->render([
            'id' => TestTourProvider::ID,
        ]));
    }
}
