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

    const LABEL                  = 'Log files';
    const DESCRIPTION            = 'Deletes log files older than the configured retention period, including compressed archives';
    const CRON_EXPRESSION        = '0 0 * * *';
    const CONFIG_RETENTION_DAYS  = 'LOG_RETENTION';
    const DEFAULT_RETENTION_DAYS = 180;

    protected function directory(): string
    {
        /** @var CommonLogger $oLogger */
        $oLogger = Factory::service('Logger');
        return $oLogger->getDir();
    }

    /**
     * @return string|string[]
     */
    protected function pattern(): string|array
    {
        return ['*.php', '*.php.gz'];
    }

    protected function olderThanDays(): int
    {
        return (int) Config::get(static::CONFIG_RETENTION_DAYS, static::DEFAULT_RETENTION_DAYS);
    }
}
