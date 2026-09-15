<?php

namespace Nails\Housekeeping\Housekeeping;

use Nails\Common\Service\Logger as CommonLogger;
use Nails\Config;
use Nails\Factory;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Traits\DeletesFiles;

class LogFiles extends Base
{
    use DeletesFiles;

    const LABEL           = 'Log files';
    const DESCRIPTION     = 'Deletes log files older than the configured retention period';
    const CRON_EXPRESSION = '0 0 * * *';

    protected function directory(): string
    {
        /** @var CommonLogger $oLogger */
        $oLogger = Factory::service('Logger');
        return $oLogger->getDir();
    }

    protected function pattern(): string
    {
        return '*.php';
    }

    protected function olderThanDays(): int
    {
        return (int) Config::get('LOG_RETENTION', 180);
    }
}
