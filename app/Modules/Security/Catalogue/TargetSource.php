<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

/** WHAT WAS ACTED ON. */
enum TargetSource: string
{
    case EntityTypeAndId = 'entity';

    case None = 'none';
}
