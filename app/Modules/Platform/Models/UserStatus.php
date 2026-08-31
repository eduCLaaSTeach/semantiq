<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
