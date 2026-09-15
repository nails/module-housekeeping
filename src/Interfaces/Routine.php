<?php

namespace Nails\Housekeeping\Interfaces;

use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;

interface Routine
{
    /**
     * A unique key for this routine
     */
    public function getKey(): string;

    /**
     * A short human label
     */
    public function getLabel(): string;

    /**
     * A longer description of what the routine does
     */
    public function getDescription(): string;

    /**
     * The cron expression describing when the routine is due
     */
    public function getCronExpression(): string;

    /**
     * Environments in which the routine should run; empty = all
     *
     * @return string[]
     */
    public function getEnvironments(): array;

    /**
     * Whether the routine is enabled
     */
    public function isEnabled(): bool;

    /**
     * Execute the routine
     */
    public function execute(Context $oContext): Result;
}
