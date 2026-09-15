<?php

namespace Nails\Housekeeping\Admin\Permission;

use Nails\Admin\Interfaces\Permission;

class Browse implements Permission
{
    public function label(): string
    {
        return 'Can browse housekeeping routines and logs';
    }

    public function group(): string
    {
        return 'Housekeeping';
    }
}
