<?php

namespace Tests\Housekeeping\Stub;

use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Traits\ArchivesFiles;

class ArchivesFilesRoutine extends Base
{
    use ArchivesFiles;

    const CRON_EXPRESSION = '@daily';

    protected function directory(): string
    {
        throw new \RuntimeException('Stub');
    }
}
