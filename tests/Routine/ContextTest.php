<?php

namespace Tests\Housekeeping\Routine;

use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Service\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nails\Housekeeping\Routine\Context
 */
class ContextTest extends TestCase
{
    public function test_should_stop_is_false_at_the_start_of_a_run(): void
    {
        $oContext = $this->context();

        self::assertFalse($oContext->shouldStop());
        self::assertSame(Context::DEFAULT_BUDGET_SECONDS, $oContext->budgetSeconds());
    }

    public function test_should_stop_is_true_once_the_budget_has_elapsed(): void
    {
        $oContext = $this->context(iBudgetSeconds: 1, fStartedAt: microtime(true) - 2);

        self::assertTrue($oContext->shouldStop());
    }

    public function test_budget_of_zero_disables_should_stop(): void
    {
        $oContext = $this->context(iBudgetSeconds: 0, fStartedAt: microtime(true) - 3600);

        self::assertFalse($oContext->shouldStop());
        self::assertSame(0, $oContext->budgetSeconds());
    }

    public function test_abort_logs_and_returns_a_failed_result(): void
    {
        $aLogs   = [];
        $oLogger = $this->createStub(Logger::class);
        $oLogger->method('routine')->willReturnCallback(
            function (string $sClass, string $sMessage) use (&$aLogs, $oLogger): Logger {
                $aLogs[] = $sMessage;
                return $oLogger;
            }
        );

        $oContext = new Context(false, $oLogger, 'Tests\\Housekeeping\\Routine\\ContextTest');
        $oResult  = $oContext->abort(4, 1);

        self::assertFalse($oResult->isSuccess());
        self::assertSame(4, $oResult->getProcessed());
        self::assertSame(1, $oResult->getFailed());
        self::assertSame('Routine exceeded time budget', $oResult->getMessage());
        self::assertSame(['ABORTED'], $aLogs);
    }

    private function context(?int $iBudgetSeconds = null, ?float $fStartedAt = null): Context
    {
        $oLogger = $this->createStub(Logger::class);
        $oLogger->method('routine')->willReturnSelf();

        return new Context(
            false,
            $oLogger,
            'Tests\\Housekeeping\\Routine\\ContextTest',
            null,
            $iBudgetSeconds,
            $fStartedAt
        );
    }
}
