<?php

namespace Tests\Housekeeping\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Nails\Housekeeping\Routine\Base;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use Nails\Housekeeping\Service\Logger;
use Nails\Housekeeping\Service\Orchestrator;
use PHPUnit\Framework\TestCase;

class ScheduleRoutine extends Base
{
    public int $iExecutions = 0;

    private ?\Closure $cOnExecute = null;

    private ?Result $oResult = null;

    public function __construct(
        private readonly string $sCron,
        private readonly bool $bEnabled = true,
    ) {
    }

    public function getCronExpression(): string
    {
        return $this->sCron;
    }

    public function isEnabled(): bool
    {
        return $this->bEnabled;
    }

    public function whenExecuting(\Closure $cOnExecute): self
    {
        $this->cOnExecute = $cOnExecute;

        return $this;
    }

    public function willReturn(Result $oResult): self
    {
        $this->oResult = $oResult;

        return $this;
    }

    public function execute(Context $oContext): Result
    {
        $this->iExecutions++;
        if ($this->cOnExecute !== null) {
            ($this->cOnExecute)();
        }

        return $this->oResult ?? Result::ok();
    }
}

class OpeningRoutine extends ScheduleRoutine
{
}

class FollowingRoutine extends ScheduleRoutine
{
}

class MinuteThreeRoutine extends ScheduleRoutine
{
}

class SilentLogger extends Logger
{
    public function __construct()
    {
    }

    public function routine(string $sClass, string $sMessage): self
    {
        return $this;
    }
}

/**
 * Last-run settings are JSON-decoded objects. This double stores that shape
 * and stands in for the clock, so tests do not need a database.
 */
class ClockOrchestrator extends Orchestrator
{
    /** @var ScheduleRoutine[] */
    public array $aRoutines;

    /** @var array<string, object|null> */
    private array $aStored = [];

    /**
     * @param ScheduleRoutine[] $aRoutines
     */
    public function __construct(
        private DateTimeImmutable $oWallClock,
        array $aRoutines,
    ) {
        $this->aRoutines = $aRoutines;
    }

    /**
     * @param ScheduleRoutine[] $aRoutines
     */
    public static function at(string $sNow, array $aRoutines): self
    {
        return new self(new DateTimeImmutable($sNow), $aRoutines);
    }

    public function advanceMinutes(int $iMinutes): void
    {
        $this->oWallClock = $this->oWallClock->modify('+' . $iMinutes . ' minutes');
    }

    public function setWallClock(string $sNow): void
    {
        $this->oWallClock = new DateTimeImmutable($sNow);
    }

    public function remember(ScheduleRoutine $oRoutine, ?string $sAt, bool $bDryRun = false): void
    {
        if ($sAt === null) {
            unset($this->aStored[$oRoutine->getKey()]);

            return;
        }

        $this->aStored[$oRoutine->getKey()] = json_decode((string) json_encode([
            'at'          => $sAt,
            'processed'   => 0,
            'failed'      => 0,
            'success'     => true,
            'duration_ms' => 0,
            'dry_run'     => $bDryRun,
        ]));
    }

    public function discover(): array
    {
        return $this->aRoutines;
    }

    protected function now(): DateTimeInterface
    {
        return $this->oWallClock;
    }

    protected function trigger(string $sEvent, array $aPayload = []): void
    {
    }

    protected function logger(): Logger
    {
        return new SilentLogger();
    }

    protected function readLastRunSetting(string $sClass): mixed
    {
        return $this->aStored[$sClass] ?? null;
    }

    protected function writeLastRunSetting(string $sClass, array $aValue): void
    {
        $this->aStored[$sClass] = json_decode((string) json_encode($aValue));
    }
}

/**
 * @covers \Nails\Housekeeping\Service\Orchestrator
 */
class OrchestratorScheduleTest extends TestCase
{
    public function test_minute_specific_routine_still_runs_when_an_earlier_routine_overruns_the_minute(): void
    {
        $oSlow  = new OpeningRoutine('0 0 * * *');
        $oLater = new FollowingRoutine('@daily');
        $oOrchestrator = ClockOrchestrator::at('2026-10-12 00:00:05', [$oSlow, $oLater]);
        $oOrchestrator->remember($oSlow, '2026-10-11 00:00:20');
        $oOrchestrator->remember($oLater, '2026-10-11 00:00:20');
        $oSlow->whenExecuting(function () use ($oOrchestrator) {
            $oOrchestrator->advanceMinutes(3);
        });

        $aResults = $oOrchestrator->run();

        self::assertSame(1, $oSlow->iExecutions);
        self::assertSame(1, $oLater->iExecutions);
        self::assertArrayHasKey(OpeningRoutine::class, $aResults);
        self::assertArrayHasKey(FollowingRoutine::class, $aResults);
    }

    public function test_routine_whose_window_opens_during_the_run_waits_for_the_next_run(): void
    {
        $oSlow  = new OpeningRoutine('* * * * *');
        $oThree = new MinuteThreeRoutine('3 0 * * *');
        $oOrchestrator = ClockOrchestrator::at('2026-10-12 00:00:05', [$oSlow, $oThree]);
        $oOrchestrator->remember($oSlow, '2026-10-11 23:59:00');
        $oOrchestrator->remember($oThree, '2026-10-11 00:03:10');
        $oSlow->whenExecuting(function () use ($oOrchestrator) {
            $oOrchestrator->advanceMinutes(3);
        });

        $oOrchestrator->run();

        self::assertSame(1, $oSlow->iExecutions);
        self::assertSame(0, $oThree->iExecutions);
    }

    public function test_missed_window_runs_once_then_waits_for_the_next_window(): void
    {
        $oRoutine = new FollowingRoutine('0 0 * * *');
        $oOrchestrator = ClockOrchestrator::at('2026-10-12 00:07:05', [$oRoutine]);
        $oOrchestrator->remember($oRoutine, '2026-10-11 00:00:00');

        self::assertTrue($oOrchestrator->isDue($oRoutine));

        $oOrchestrator->run();

        self::assertSame(1, $oRoutine->iExecutions);
        self::assertFalse($oOrchestrator->isDue($oRoutine));

        $oOrchestrator->run();

        self::assertSame(1, $oRoutine->iExecutions);

        $oOrchestrator->setWallClock('2026-10-12 12:00:00');
        self::assertFalse($oOrchestrator->isDue($oRoutine));

        $oOrchestrator->setWallClock('2026-10-13 00:00:05');
        self::assertTrue($oOrchestrator->isDue($oRoutine));
    }

    public function test_routine_with_no_stamp_is_due_outside_its_minute(): void
    {
        $oRoutine = new FollowingRoutine('0 0 * * *');
        $oOrchestrator = ClockOrchestrator::at('2026-10-12 15:30:00', [$oRoutine]);

        self::assertNull($oOrchestrator->getLastRun($oRoutine->getKey()));
        self::assertTrue($oOrchestrator->isDue($oRoutine));

        $oOrchestrator->run();

        self::assertSame(1, $oRoutine->iExecutions);
        self::assertNotNull($oOrchestrator->getLastRun($oRoutine->getKey()));
        self::assertFalse($oOrchestrator->isDue($oRoutine));
    }

    public function test_dry_run_stamp_does_not_suppress_the_next_real_run(): void
    {
        $oRoutine = new FollowingRoutine('0 0 * * *');
        $oOrchestrator = ClockOrchestrator::at('2026-10-12 00:07:05', [$oRoutine]);
        $oOrchestrator->remember($oRoutine, '2026-10-12 00:07:00', true);

        $aStamp = $oOrchestrator->getLastRun($oRoutine->getKey());
        self::assertNotNull($aStamp);
        self::assertTrue($aStamp['dry_run']);
        self::assertTrue($oOrchestrator->isDue($oRoutine));

        $oOrchestrator->remember($oRoutine, null);
        $oOrchestrator->run(null, true);

        self::assertSame(1, $oRoutine->iExecutions);
        self::assertNull($oOrchestrator->getLastRun($oRoutine->getKey()));
        self::assertTrue($oOrchestrator->isDue($oRoutine));
    }

    public function test_failed_attempt_is_stamped_and_does_not_retry_until_the_next_window(): void
    {
        $oRoutine = (new FollowingRoutine('0 0 * * *'))->willReturn(Result::fail('disk full', 2, 1));
        $oOrchestrator = ClockOrchestrator::at('2026-10-12 00:07:05', [$oRoutine]);
        $oOrchestrator->remember($oRoutine, '2026-10-11 00:00:05');

        $aResults = $oOrchestrator->run();

        self::assertFalse($aResults[FollowingRoutine::class]->isSuccess());
        $aStamp = $oOrchestrator->getLastRun(FollowingRoutine::class);
        self::assertNotNull($aStamp);
        self::assertFalse($aStamp['success']);
        self::assertFalse($aStamp['dry_run']);
        self::assertSame(2, $aStamp['processed']);
        self::assertSame(1, $aStamp['failed']);
        self::assertFalse($oOrchestrator->isDue($oRoutine));

        $oOrchestrator->setWallClock('2026-10-13 00:00:05');
        self::assertTrue($oOrchestrator->isDue($oRoutine));
    }

    public function test_force_and_routine_skip_the_due_check(): void
    {
        $oSibling = new OpeningRoutine('* * * * *');
        $oTarget  = new FollowingRoutine('0 0 * * *');
        $oOrchestrator = ClockOrchestrator::at('2026-10-12 00:08:00', [$oSibling, $oTarget]);
        $oOrchestrator->remember($oTarget, '2026-10-12 00:00:30');

        self::assertFalse($oOrchestrator->isDue($oTarget));

        $oOrchestrator->run();

        self::assertSame(1, $oSibling->iExecutions);
        self::assertSame(0, $oTarget->iExecutions);

        $oSibling->iExecutions = 0;
        $oOrchestrator->run(null, false, false, 'FollowingRoutine');

        self::assertSame(0, $oSibling->iExecutions);
        self::assertSame(1, $oTarget->iExecutions);

        $oSibling->iExecutions = 0;
        $oTarget->iExecutions  = 0;
        $oOrchestrator->run(null, false, true);

        self::assertSame(1, $oSibling->iExecutions);
        self::assertSame(1, $oTarget->iExecutions);
    }

    public function test_named_routine_stays_disabled_unless_forced(): void
    {
        $oRoutine = new FollowingRoutine('0 0 * * *', false);
        $oOrchestrator = ClockOrchestrator::at('2026-10-12 00:00:05', [$oRoutine]);

        $oOrchestrator->run(null, false, false, FollowingRoutine::class);
        self::assertSame(0, $oRoutine->iExecutions);

        $oOrchestrator->run(null, false, true, FollowingRoutine::class);
        self::assertSame(1, $oRoutine->iExecutions);
    }
}
