<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Model;

/**
 * A single tour step, serialized in the shape driver.js expects for a step, plus `page`.
 *
 * A tour may span several pages: `page` is the path of the page the step lives on. The
 * Stimulus controller only shows the steps of the current page and navigates once the next
 * step lives elsewhere.
 *
 * Title and description are plain text. driver.js writes them into the popover via innerHTML,
 * so the controller escapes them first — any HTML in the text is shown as text.
 */
final readonly class Step implements \JsonSerializable
{
    /**
     * @param string      $page    path of the page the step lives on, without query string
     * @param string|null $element CSS selector; null centers the step on screen without a highlight
     */
    public function __construct(
        public string $page,
        public ?string $element,
        public string $title,
        public string $description,
        public Side $side = Side::BOTTOM,
    ) {
    }

    /**
     * @return array{page: string, element?: string, popover: array{title: string, description: string, side: string}}
     */
    public function jsonSerialize(): array
    {
        $step = [
            'page' => $this->page,
            'popover' => [
                'title' => $this->title,
                'description' => $this->description,
                'side' => $this->side->value,
            ],
        ];

        if ($this->element !== null) {
            $step['element'] = $this->element;
        }

        return $step;
    }
}
