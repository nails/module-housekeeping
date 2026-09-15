<?php

namespace Nails\Housekeeping\Traits;

use Nails\Common\Model\Base as ModelBase;
use Nails\Factory;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use Nails\Housekeeping\Service\Deleter;

trait TruncatesTable
{
    /**
     * The model whose table should be truncated
     */
    abstract protected function model(): ModelBase;

    public function execute(Context $oContext): Result
    {
        /** @var Deleter $oDeleter */
        $oDeleter = Factory::service('Deleter', Constants::MODULE_SLUG);

        return $oDeleter->truncateTable($oContext, $this->model());
    }
}
