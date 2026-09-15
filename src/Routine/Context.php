<?php

namespace Nails\Housekeeping\Routine;

use Nails\Housekeeping\Service\Logger;
use Symfony\Component\Console\Output\OutputInterface;

class Context
{
    public function __construct(
        private readonly bool $bDryRun,
        private readonly Logger $oLogger,
        private readonly string $sRoutineClass,
        private readonly ?OutputInterface $oOutput = null,
    ) {
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
