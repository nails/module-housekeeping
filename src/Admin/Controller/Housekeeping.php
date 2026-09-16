<?php

namespace Nails\Housekeeping\Admin\Controller;

use Nails\Admin\Controller\Base;
use Nails\Admin\Factory\Nav;
use Nails\Admin\Helper;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Service\Input;
use Nails\Components;
use Nails\Factory;
use Nails\Housekeeping\Admin\Permission;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Interfaces\Routine;
use Nails\Housekeeping\Service\Logger;
use Nails\Housekeeping\Service\Orchestrator;
use ReflectionException;
use Symfony\Component\Console\Output\NullOutput;

class Housekeeping extends Base
{
    /**
     * @throws FactoryException
     */
    public static function announce(): Nav|array|null
    {
        if (!userHasPermission(Permission\Browse::class)) {
            return null;
        }

        /** @var Nav $oNavGroup */
        $oNavGroup = Factory::factory('Nav', \Nails\Admin\Constants::MODULE_SLUG);
        $oNavGroup
            ->setLabel('Utilities')
            ->setIcon('fa-sliders-h')
            ->addAction('Housekeeping');

        return $oNavGroup;
    }

    /**
     * @throws FactoryException
     * @throws NailsException
     * @throws ReflectionException
     */
    public function index(): void
    {
        if (!userHasPermission(Permission\Browse::class)) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Orchestrator $oOrchestrator */
        $oOrchestrator = Factory::service('Orchestrator', Constants::MODULE_SLUG);

        $aRows = [];
        foreach ($oOrchestrator->discover() as $oRoutine) {
            $oComponent = Components::detectClassComponent($oRoutine);
            $aRows[]    = [
                'routine'   => $oRoutine,
                'component' => $oComponent->name ?? 'Unknown',
                'last_run'  => $oOrchestrator->getLastRun($oRoutine->getKey()),
            ];
        }

        Helper::addHeaderButton(
            static::url('logs'),
            'View Audit Logs'
        );

        $this
            ->addBreadcrumb('Utilities')
            ->addBreadcrumb('Housekeeping')
            ->setData('aRows', $aRows)
            ->setData('bCanExecute', userHasPermission(Permission\Execute::class))
            ->loadView('index');
    }

    /**
     * @throws FactoryException
     */
    public function logs(): void
    {
        if (!userHasPermission(Permission\Browse::class)) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Logger $oLogger */
        $oLogger = Factory::service('Logger', Constants::MODULE_SLUG);

        $aFiles     = $oLogger->listLogFiles();
        $sRequested = (string) $oInput::get('file');
        $sSelected  = $sRequested !== '' ? basename($sRequested) : '';
        $sContents  = '';

        if ($sSelected === '' && !empty($aFiles)) {
            $sSelected = basename($aFiles[0]);
        }

        if ($sSelected !== '') {
            $sContents = $oLogger->readLogTail($sSelected);
        }

        $this
            ->addBreadcrumb('Utilities')
            ->addBreadcrumb('Housekeeping', static::url())
            ->addBreadcrumb('Logs')
            ->setData('aFiles', $aFiles)
            ->setData('sSelected', $sSelected)
            ->setData('sContents', $sContents)
            ->loadView('logs');
    }

    /**
     * @throws FactoryException
     * @throws NailsException
     * @throws ReflectionException
     */
    public function run(): void
    {
        if (!userHasPermission(Permission\Execute::class)) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Orchestrator $oOrchestrator */
        $oOrchestrator = Factory::service('Orchestrator', Constants::MODULE_SLUG);

        $sRoutine = (string) $oInput::get('routine');
        $bDryRun  = (bool) $oInput::get('dry_run');

        $oMatched = $this->findRoutine($oOrchestrator, $sRoutine);
        if (!$oMatched instanceof Routine) {
            $this->oUserFeedback->error('Unknown housekeeping routine.');
            redirect(static::url());
            return;
        }

        $oResult = $oOrchestrator->runRoutine(
            $oMatched->getKey(),
            $bDryRun,
            true,
            new NullOutput()
        );

        $sSummary = sprintf(
            '%s: %s item(s) %s.',
            $oMatched->getLabel(),
            number_format($oResult->getProcessed()),
            $bDryRun ? 'would be processed' : 'processed'
        );

        if ($oResult->isSuccess()) {
            $this->oUserFeedback->success($sSummary);
        } else {
            $this->oUserFeedback->error($sSummary . ' ' . $oResult->getMessage());
        }

        redirect(static::url());
    }

    /**
     * @throws FactoryException
     */
    private function findRoutine(Orchestrator $oOrchestrator, string $sRoutine): ?Routine
    {
        foreach ($oOrchestrator->discover() as $oCandidate) {
            if ($oCandidate->getKey() === $sRoutine) {
                return $oCandidate;
            }
        }

        return null;
    }
}
