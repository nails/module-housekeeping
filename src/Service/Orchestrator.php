<?php

namespace Nails\Housekeeping\Service;

use Cron\CronExpression;
use DateTime;
use Exception;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Factory\Component;
use Nails\Common\Interfaces\ErrorHandlerDriver;
use Nails\Common\Service\ErrorHandler;
use Nails\Common\Service\Event;
use Nails\Components;
use Nails\Environment;
use Nails\Factory;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Events;
use Nails\Housekeeping\Exception\HousekeepingException;
use Nails\Housekeeping\Exception\RoutineMisconfiguredException;
use Nails\Housekeeping\Interfaces\Routine;
use Nails\Housekeeping\Routine\Context;
use Nails\Housekeeping\Routine\Result;
use ReflectionException;
use Symfony\Component\Console\Output\OutputInterface;

class Orchestrator
{
    /**
     * @return Routine[]
     * @throws FactoryException
     */
    public function discover(): array
    {
        $aRoutines = [];

        /** @var Component $oComponent */
        foreach (Components::available() as $oComponent) {
            $aClasses = $oComponent
                ->findClasses('Housekeeping')
                ->whichImplement(Routine::class)
                ->whichCanBeInstantiated();

            foreach ($aClasses as $sClass) {
                if (!is_string($sClass) || !is_a($sClass, Routine::class, true)) {
                    continue;
                }
                $aRoutines[$sClass] = new $sClass();
            }
        }

        ksort($aRoutines);

        return array_values($aRoutines);
    }

    /**
     * Run due routines, a single named routine, or every routine when forced.
     *
     * @return Result[] keyed by routine class
     * @throws FactoryException
     * @throws NailsException
     * @throws ReflectionException
     */
    public function run(
        ?OutputInterface $oOutput = null,
        bool $bDryRun = false,
        bool $bForce = false,
        ?string $sRoutine = null,
    ): array {
        /** @var Event $oEventService */
        $oEventService = Factory::service('Event');
        $oEventService->trigger(Events::HOUSEKEEPING_START, Events::getEventNamespace());

        $aRoutines = $this->discover();
        $oEventService->trigger(Events::HOUSEKEEPING_READY, Events::getEventNamespace(), [$aRoutines]);

        $oOutput?->writeln(sprintf('Discovered <info>%d</info> routine(s)', count($aRoutines)));

        if ($sRoutine !== null) {
            $aRoutines = $this->filterByName($aRoutines, $sRoutine);
            if (empty($aRoutines)) {
                throw new HousekeepingException(sprintf('No housekeeping routine matched "%s"', $sRoutine));
            }
        }

        $aResults = [];

        foreach ($aRoutines as $oRoutine) {
            $sClass = $oRoutine->getKey();

            if (!$bForce && $sRoutine === null && !$this->isDue($oRoutine, $oOutput)) {
                continue;
            }

            if ($sRoutine !== null && !$oRoutine->isEnabled() && !$bForce) {
                $oOutput?->writeln(sprintf('↳ <comment>%s</comment> is disabled', $sClass));
                continue;
            }

            $aResults[$sClass] = $this->executeRoutine($oRoutine, $bDryRun, $oOutput);
        }

        $oEventService->trigger(Events::HOUSEKEEPING_FINISH, Events::getEventNamespace());

        return $aResults;
    }

    /**
     * @throws FactoryException
     * @throws NailsException
     * @throws ReflectionException
     */
    public function runRoutine(
        string $sRoutine,
        bool $bDryRun = false,
        bool $bForce = true,
        ?OutputInterface $oOutput = null,
    ): Result {
        $aResults = $this->run($oOutput, $bDryRun, $bForce, $sRoutine);
        $oResult  = reset($aResults);

        if (!$oResult instanceof Result) {
            throw new HousekeepingException(sprintf('Routine "%s" did not return a result', $sRoutine));
        }

        return $oResult;
    }

    /**
     * @throws FactoryException
     * @throws NailsException
     * @throws ReflectionException
     */
    public function executeRoutine(
        Routine $oRoutine,
        bool $bDryRun = false,
        ?OutputInterface $oOutput = null,
    ): Result {
        $sClass = $oRoutine->getKey();

        /** @var Event $oEventService */
        $oEventService = Factory::service('Event');
        $oEventService->trigger(Events::HOUSEKEEPING_ROUTINE_BEFORE, Events::getEventNamespace(), [$oRoutine]);

        /** @var Logger $oLogger */
        $oLogger = Factory::service('Logger', Constants::MODULE_SLUG);
        $oContext = new Context($bDryRun, $oLogger, $sClass, $oOutput);

        $oOutput?->writeln('');
        $oOutput?->writeln(sprintf('Running <info>%s</info>', $sClass));
        if ($bDryRun) {
            $oOutput?->writeln('<comment>Dry-run: no changes will be made</comment>');
        }

        $oContext->log('START');
        $fStarted = microtime(true);

        try {
            $oResult     = $oRoutine->execute($oContext);
            $iDurationMs = (int) round((microtime(true) - $fStarted) * 1000);

            $oContext->log(sprintf(
                'SUMMARY processed=%d failed=%d success=%s dry_run=%s duration_ms=%d%s',
                $oResult->getProcessed(),
                $oResult->getFailed(),
                $oResult->isSuccess() ? 'true' : 'false',
                $bDryRun ? 'true' : 'false',
                $iDurationMs,
                $oResult->getMessage() !== '' ? ' message=' . $oResult->getMessage() : ''
            ));
            $oContext->log('FINISH');

            $oOutput?->writeln(sprintf(
                '↳ %s (<comment>%s</comment> processed, %d ms)',
                $oResult->isSuccess() ? '<info>done</info>' : '<error>failed</error>',
                number_format($oResult->getProcessed()),
                $iDurationMs
            ));

            if (!$bDryRun) {
                $this->stampLastRun($sClass, $oResult, $iDurationMs, $bDryRun);
            }

            $oEventService->trigger(
                Events::HOUSEKEEPING_ROUTINE_AFTER,
                Events::getEventNamespace(),
                [$oRoutine, $oResult]
            );

            return $oResult;

        } catch (Exception $e) {
            $iDurationMs = (int) round((microtime(true) - $fStarted) * 1000);
            $oContext->log('ERROR ' . $e->getMessage());
            $oContext->log('FINISH');
            $oOutput?->writeln('<error>' . $e->getMessage() . '</error>');

            $oEventService->trigger(
                Events::HOUSEKEEPING_ROUTINE_ERROR,
                Events::getEventNamespace(),
                [$oRoutine, $e]
            );

            /** @var ErrorHandler $oErrorHandlerService */
            $oErrorHandlerService = Factory::service('ErrorHandler');
            /** @var ErrorHandlerDriver $sDriver */
            $sDriver = $oErrorHandlerService::getDriverClass();
            $sDriver::exception($e, false);

            $oResult = Result::fail($e->getMessage());

            if (!$bDryRun) {
                $this->stampLastRun($sClass, $oResult, $iDurationMs, $bDryRun);
            }

            return $oResult;
        }
    }

    /**
     * @return array{at: string, processed: int, failed: int, success: bool, duration_ms: int, dry_run: bool}|null
     */
    public function getLastRun(string $sClass): ?array
    {
        $mValue = appSetting($this->lastRunKey($sClass), Constants::MODULE_SLUG);
        if (!is_array($mValue) || !isset($mValue['at'], $mValue['processed'], $mValue['failed'], $mValue['success'], $mValue['duration_ms'], $mValue['dry_run'])) {
            return null;
        }

        return [
            'at'          => (string) $mValue['at'],
            'processed'   => (int) $mValue['processed'],
            'failed'      => (int) $mValue['failed'],
            'success'     => (bool) $mValue['success'],
            'duration_ms' => (int) $mValue['duration_ms'],
            'dry_run'     => (bool) $mValue['dry_run'],
        ];
    }

    public function lastRunKey(string $sClass): string
    {
        return 'last_run.' . str_replace('\\', '.', $sClass);
    }

    /**
     * @param Routine[] $aRoutines
     * @return Routine[]
     */
    private function filterByName(array $aRoutines, string $sFilter): array
    {
        $sFilter   = ltrim($sFilter, '\\');
        $aMatched  = [];
        $aExact    = [];

        foreach ($aRoutines as $oRoutine) {
            $sClass = ltrim($oRoutine->getKey(), '\\');
            if (strcasecmp($sClass, $sFilter) === 0) {
                $aExact[] = $oRoutine;
                continue;
            }

            $sShort = (new \ReflectionClass($oRoutine))->getShortName();
            if (strcasecmp($sShort, $sFilter) === 0 || str_ends_with($sClass, '\\' . $sFilter)) {
                $aMatched[] = $oRoutine;
            }
        }

        return $aExact !== [] ? $aExact : $aMatched;
    }

    /**
     * @throws RoutineMisconfiguredException
     * @throws FactoryException
     */
    public function isDue(Routine $oRoutine, ?OutputInterface $oOutput = null): bool
    {
        $sExpression = $oRoutine->getCronExpression();
        if ($sExpression === '') {
            throw new RoutineMisconfiguredException(sprintf(
                'Housekeeping routine "%s" is misconfigured; cron expression is empty',
                $oRoutine->getKey()
            ));
        }

        if (!CronExpression::isValidExpression($sExpression)) {
            throw new RoutineMisconfiguredException(sprintf(
                'Housekeeping routine "%s" is misconfigured; "%s" is not a valid cron expression',
                $oRoutine->getKey(),
                $sExpression
            ));
        }

        if (!$oRoutine->isEnabled()) {
            $oOutput?->writeln(sprintf('↳ <comment>%s</comment> is disabled', $oRoutine->getKey()));
            return false;
        }

        $aEnvironments = $oRoutine->getEnvironments();
        if (!empty($aEnvironments) && !in_array(Environment::get(), $aEnvironments, true)) {
            $oOutput?->writeln(sprintf(
                '↳ <comment>%s</comment> is not enabled for %s',
                $oRoutine->getKey(),
                Environment::get()
            ));
            return false;
        }

        /** @var DateTime $oNow */
        $oNow        = Factory::factory('DateTime');
        $oExpression = CronExpression::factory($sExpression);

        if (!$oExpression->isDue($oNow)) {
            return false;
        }

        return true;
    }

    private function stampLastRun(string $sClass, Result $oResult, int $iDurationMs, bool $bDryRun): void
    {
        /** @var DateTime $oNow */
        $oNow = Factory::factory('DateTime');

        setAppSetting(
            $this->lastRunKey($sClass),
            Constants::MODULE_SLUG,
            [
                'at'          => $oNow->format('Y-m-d H:i:s'),
                'processed'   => $oResult->getProcessed(),
                'failed'      => $oResult->getFailed(),
                'success'     => $oResult->isSuccess(),
                'duration_ms' => $iDurationMs,
                'dry_run'     => $bDryRun,
            ]
        );
    }
}
