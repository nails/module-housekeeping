<?php

namespace Nails\Housekeeping\Traits;

use Nails\Common\Model\Base as ModelBase;
use Nails\Factory;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use Nails\Housekeeping\Service\Deleter;

trait DeletesModelRows
{
    /**
     * The model whose rows should be deleted
     */
    abstract protected function model(): ModelBase;

    /**
     * Conditions identifying rows to delete. Must not be empty.
     *
     * @return array<int, mixed>
     */
    abstract protected function where(): array;

    /**
     * Columns to include in the audit log (id is always included)
     *
     * @return string[]
     */
    protected function auditColumns(): array
    {
        return ['id'];
    }

    protected function batchSize(): int
    {
        return 200;
    }

    protected function optimizeAfter(): bool
    {
        return false;
    }

    public function execute(Context $oContext): Result
    {
        /** @var Deleter $oDeleter */
        $oDeleter = Factory::service('Deleter', Constants::MODULE_SLUG);

        return $oDeleter->deleteRows(
            $oContext,
            $this->model(),
            $this->where(),
            $this->auditColumns(),
            $this->batchSize(),
            $this->optimizeAfter()
        );
    }
}
