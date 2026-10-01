<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use SvenPetersen\UX\Driver\Model\Side;
use SvenPetersen\UX\Driver\Model\Step;

final class StepTest extends TestCase
{
    public function testItSerializesToADriverJsStepWithItsPage(): void
    {
        $step = new Step('/admin/', '[data-tour="filter"]', 'Filter', 'Narrow the list.', Side::TOP);

        self::assertSame([
            'page' => '/admin/',
            'popover' => [
                'title' => 'Filter',
                'description' => 'Narrow the list.',
                'side' => 'top',
            ],
            'element' => '[data-tour="filter"]',
        ], $step->jsonSerialize());
    }

    /**
     * Without an element driver.js centers the popover; an `element: null` would make it look
     * for one.
     */
    public function testACenteredStepHasNoElementKey(): void
    {
        $step = new Step('/admin/', null, 'Welcome', 'A short tour.');

        self::assertArrayNotHasKey('element', $step->jsonSerialize());
        self::assertSame('bottom', $step->jsonSerialize()['popover']['side']);
    }

    /**
     * Escaping is the controller's job, right before driver.js writes the text via innerHTML.
     * The server sends plain text, so nothing is escaped twice.
     */
    public function testTextsAreSentAsPlainText(): void
    {
        $step = new Step('/admin/', null, '<b>Bold</b>', 'Fish & chips');

        self::assertSame('<b>Bold</b>', $step->jsonSerialize()['popover']['title']);
        self::assertSame('Fish & chips', $step->jsonSerialize()['popover']['description']);
    }
}
