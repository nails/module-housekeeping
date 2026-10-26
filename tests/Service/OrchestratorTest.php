<?php

namespace Tests\Housekeeping\Service;

use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Service\Orchestrator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nails\Housekeeping\Service\Orchestrator
 */
class OrchestratorTest extends TestCase
{
    public function test_last_run_key_fits_app_setting_key_column(): void
    {
        $oOrchestrator = new Orchestrator();

        $sKey = $oOrchestrator->lastRunKey('Nails\\Housekeeping\\Housekeeping\\LogFilesArchive');

        self::assertSame('last_run.' . md5('Nails\\Housekeeping\\Housekeeping\\LogFilesArchive'), $sKey);
        self::assertLessThanOrEqual(50, strlen($sKey));
    }

    public function test_last_run_key_is_unique_per_class(): void
    {
        $oOrchestrator = new Orchestrator();

        self::assertNotSame(
            $oOrchestrator->lastRunKey('Nails\\Housekeeping\\Housekeeping\\LogFiles'),
            $oOrchestrator->lastRunKey('Nails\\Housekeeping\\Housekeeping\\LogFilesArchive')
        );
        self::assertSame(Constants::MODULE_SLUG, 'nails/module-housekeeping');
    }
}
