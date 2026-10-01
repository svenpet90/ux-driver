<?php

declare(strict_types=1);

namespace SvenPetersen\UX\Driver\Model;

/**
 * Which side of the highlighted element the popover sits on — the values driver.js accepts as
 * `popover.side`.
 */
enum Side: string
{
    case TOP = 'top';
    case RIGHT = 'right';
    case BOTTOM = 'bottom';
    case LEFT = 'left';
}
