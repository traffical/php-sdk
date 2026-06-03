<?php

declare(strict_types=1);

namespace Traffical\Types;

/**
 * Discriminator for which call produced an assignment row. A subset of the
 * canonical BaseEvent.type discriminator.
 */
enum AssignmentType: string
{
    case Decision = 'decision';
    case Exposure = 'exposure';
}
