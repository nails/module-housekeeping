<?php

namespace Nails\Admin\Housekeeping;

use Nails\Admin\Controller\Base;
use Nails\Admin\Factory\Nav;
use Nails\Admin\Helper;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Service\Input;
use Nails\Components;
use Nails\Factory;
use Nails\Housekeeping\Constants;
use Nails\Housekeeping\Interfaces\Routine;
use Nails\Housekeeping\Service\Logger;
use Nails\Housekeeping\Service\Orchestrator;
use ReflectionException;
use Symfony\Component\Console\Output\NullOutput;

class Housekeeping extends Base
{
    const PERMISSION_BROWSE  = 'admin:housekeeping:housekeeping:browse';
    const PERMISSION_EXECUTE = 'admin:housekeeping:housekeeping:execute';
    const ADMIN_URL          = 'admin/housekeeping/housekeeping';

    /**
     * @throws FactoryException
     */
    public static function announce(): Nav|array|null
    {
        if (!userHasPermission(self::PERMISSION_BROWSE)) {
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
     * @return array<string, string>
     */
    public static function permissions(): array
    {
        $aPermissions = parent::permissions();

        $aPermissions['browse']  = 'Can browse housekeeping routines and logs';
        $aPermissions['execute'] = 'Can run housekeeping routines';

        return $aPermissions;
    }

    /**
     * @throws FactoryException
     * @throws NailsException
     * @throws ReflectionException
     */
    public function index(): void
    {
        if (!userHasPermission(self::PERMISSION_BROWSE)) {
            unauthorised();
        }

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Orchestrator $oOrchestrator */
        $oOrchestrator = Factory::service('Orchestrator', Constants::MODULE_SLUG);

        if ($oInput::post('run')) {
            $this->handleRun($oOrchestrator, (string) $oInput::post('routine'), (bool) $oInput::post('dry_run'));
            return;
        }

        $aRows = [];
        foreach ($oOrchestrator->discover() as $oRoutine) {
            $oComponent = Components::detectClassComponent($oRoutine);
            $aRows[]    = [
                'routine'   => $oRoutine,
                'component' => $oComponent->name ?? 'Unknown',
                'last_run'  => $oOrchestrator->getLastRun($oRoutine->getKey()),
            ];
        }

        $this->data['page']->title = 'Housekeeping';
        $this->data['aRows']       = $aRows;
        $this->data['bCanExecute'] = userHasPermission(self::PERMISSION_EXECUTE);

        Helper::loadView('index');
    }

    /**
     * @throws FactoryException
     */
    public function logs(): void
    {
        if (!userHasPermission(self::PERMISSION_BROWSE)) {
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

        $this->data['page']->title = 'Housekeeping: Logs';
        $this->data['aFiles']      = $aFiles;
        $this->data['sSelected']   = $sSelected;
        $this->data['sContents']   = $sContents;

        Helper::loadView('logs');
    }

    /**
     * @throws FactoryException
     * @throws NailsException
     * @throws ReflectionException
     */
    private function handleRun(Orchestrator $oOrchestrator, string $sRoutine, bool $bDryRun): void
    {
        if (!userHasPermission(self::PERMISSION_EXECUTE)) {
            unauthorised();
        }

        $oMatched = $this->findRoutine($oOrchestrator, $sRoutine);
        if (!$oMatched instanceof Routine) {
            $this->oUserFeedback->error('Unknown housekeeping routine.');
            redirect(self::ADMIN_URL);
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

        redirect(self::ADMIN_URL);
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
