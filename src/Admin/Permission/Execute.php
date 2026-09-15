<?php

namespace Nails\Housekeeping\Admin\Permission;

use Nails\Admin\Interfaces\Permission;

class Execute implements Permission
{
    public function label(): string
    {
        return 'Can run housekeeping routines';
    }

    public function group(): string
    {
        return 'Housekeeping';
    }
}
