<?php

namespace Nails\Housekeeping\Routine;

class Result
{
    public function __construct(
        public readonly bool $bSuccess,
        public readonly int $iProcessed = 0,
        public readonly int $iFailed = 0,
        public readonly string $sMessage = '',
    ) {
    }

    public static function ok(int $iProcessed = 0, string $sMessage = ''): self
    {
        return new self(true, $iProcessed, 0, $sMessage);
    }

    public static function fail(string $sMessage, int $iProcessed = 0, int $iFailed = 0): self
    {
        return new self(false, $iProcessed, $iFailed, $sMessage);
    }

    public function isSuccess(): bool
    {
        return $this->bSuccess;
    }

    public function getProcessed(): int
    {
        return $this->iProcessed;
    }

    public function getFailed(): int
    {
        return $this->iFailed;
    }

    public function getMessage(): string
    {
        return $this->sMessage;
    }
}
