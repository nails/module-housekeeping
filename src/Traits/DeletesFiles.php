<?php

namespace Nails\Housekeeping\Traits;

use Nails\Factory;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use Nails\Housekeeping\Service\Deleter;

trait DeletesFiles
{
    /**
     * Directory to scan
     */
    abstract protected function directory(): string;

    /**
     * fnmatch() pattern applied to filenames
     */
    protected function pattern(): string
    {
        return '*.php';
    }

    /**
     * Only files older than this many days are removed. 0 = all matching files.
     */
    protected function olderThanDays(): int
    {
        return 180;
    }

    public function execute(Context $oContext): Result
    {
        /** @var Deleter $oDeleter */
        $oDeleter = Factory::service('Deleter', Constants::MODULE_SLUG);

        return $oDeleter->deleteFiles(
            $oContext,
            $this->directory(),
            $this->pattern(),
            $this->olderThanDays()
        );
    }
}
