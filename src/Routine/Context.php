<?php

namespace Nails\Housekeeping\Routine;

use Nails\Config;
use Nails\Housekeeping\Service\Logger;
use Symfony\Component\Console\Output\OutputInterface;

class Context
{
    const CONFIG_BUDGET_SECONDS  = 'HOUSEKEEPING_ROUTINE_BUDGET';
    const DEFAULT_BUDGET_SECONDS = 1800;

    private readonly int $iBudgetSeconds;
    private readonly float $fStartedAt;

    public function __construct(
        private readonly bool $bDryRun,
        private readonly Logger $oLogger,
        private readonly string $sRoutineClass,
        private readonly ?OutputInterface $oOutput = null,
        ?int $iBudgetSeconds = null,
        ?float $fStartedAt = null,
    ) {
        $this->iBudgetSeconds = $iBudgetSeconds ?? (int) Config::get(
            static::CONFIG_BUDGET_SECONDS,
            static::DEFAULT_BUDGET_SECONDS
        );
        $this->fStartedAt = $fStartedAt ?? microtime(true);
    }

    public function isDryRun(): bool
    {
        return $this->bDryRun;
    }

    public function logger(): Logger
    {
        return $this->oLogger;
    }

    public function routineClass(): string
    {
        return $this->sRoutineClass;
    }

    public function output(): ?OutputInterface
    {
        return $this->oOutput;
    }

    public function budgetSeconds(): int
    {
        return $this->iBudgetSeconds;
    }

    /**
     * True when this routine has used its time budget. A budget of 0 disables the check.
     * Cooperative: the loop must call this; it does not kill the process.
     */
    public function shouldStop(): bool
    {
        if ($this->iBudgetSeconds < 1) {
            return false;
        }

        return (microtime(true) - $this->fStartedAt) >= $this->iBudgetSeconds;
    }

    public function abort(int $iProcessed = 0, int $iFailed = 0): Result
    {
        $this
            ->log('ABORTED')
            ->writeln('<error>Aborted: routine exceeded time budget</error>');

        return Result::fail('Routine exceeded time budget', $iProcessed, $iFailed);
    }

    public function writeln(string $sLine = ''): self
    {
        $this->oOutput?->writeln($sLine);
        return $this;
    }

    public function log(string $sMessage): self
    {
        $this->oLogger->routine($this->sRoutineClass, $sMessage);
        return $this;
    }
}
