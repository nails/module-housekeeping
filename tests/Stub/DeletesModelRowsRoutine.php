<?php

namespace Tests\Housekeeping\Stub;

use Nails\Common\Model\Base as ModelBase;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Traits\DeletesModelRows;

class DeletesModelRowsRoutine extends Base
{
    use DeletesModelRows;

    const CRON_EXPRESSION = '@daily';

    protected function model(): ModelBase
    {
        throw new \RuntimeException('Stub');
    }

    /**
     * @return array<int, mixed>
     */
    protected function where(): array
    {
        return [
            ['id <', 0],
        ];
    }
}
