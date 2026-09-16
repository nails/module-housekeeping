<?php

use Nails\Admin\Housekeeping\Housekeeping;
use Nails\Housekeeping\Interfaces\Routine;

/**
 * @var array<int, array{routine: Routine, component: string, last_run: array<string, mixed>|null}> $aRows
 * @var bool                                                                                        $bCanExecute
 */

?>
<div class="group-housekeeping browse">
    <p>
        Housekeeping routines remove expired data, files, and other residue on a schedule.
    </p>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Routine</th>
                    <th class="text-center">Schedule</th>
                    <th>Enabled</th>
                    <th>Last run</th>
                    <?php

                    if ($bCanExecute) {
                        ?>
                        <th class="actions" style="width:220px;">Actions</th>
                        <?php
                    }

                    ?>
                </tr>
            </thead>
            <tbody>
                <?php

                if (empty($aRows)) {

                    ?>
                    <tr>
                        <td colspan="<?=$bCanExecute ? 5 : 4?>" class="no-data">
                            No housekeeping routines have been discovered.
                        </td>
                    </tr>
                    <?php

                } else {
                    foreach ($aRows as $aRow) {
                        $oRoutine = $aRow['routine'];
                        $aLastRun = $aRow['last_run'];
                        ?>
                        <tr>
                            <td>
                                <strong><?=htmlspecialchars($oRoutine->getLabel())?></strong>
                                <?php

                                if ($oRoutine->getDescription() !== '' && $oRoutine->getDescription() !== $oRoutine->getLabel()) {
                                    ?>
                                    <small class="text-muted"><?=htmlspecialchars($oRoutine->getDescription())?></small>
                                    <?php
                                }

                                ?>
                                <small class="text-muted">
                                    <code><?=htmlspecialchars($aRow['component'])?></code>
                                    &rsaquo;
                                    <code><?=htmlspecialchars($oRoutine->getKey())?></code>
                                </small>
                            </td>
                            <td class="text-center">
                                <code><?=htmlspecialchars($oRoutine->getCronExpression())?></code>
                            </td>
                            <?=\Nails\Admin\Helper::loadBoolCell($oRoutine->isEnabled())?>
                            <td>
                                <?php

                                if (empty($aLastRun['at'])) {
                                    echo '<span class="text-muted">&mdash;</span>';

                                } else {
                                    echo htmlspecialchars((string) $aLastRun['at']);
                                    echo '<br><small>';
                                    echo $aLastRun['success'] ? 'OK' : 'Failed';
                                    echo ' &middot; ' . number_format((int) ($aLastRun['processed'] ?? 0)) . ' items';
                                    if (!empty($aLastRun['duration_ms'])) {
                                        echo ' &middot; ' . number_format((int) $aLastRun['duration_ms']) . ' ms';
                                    }
                                    echo '</small>';
                                }

                                ?>
                            </td>
                            <?php

                            if ($bCanExecute) {

                                ?>
                                <td class="actions">
                                    <?php

                                    echo anchor(
                                        Housekeeping::ADMIN_URL . '/run?run=1&dry_run=1&routine=' . urlencode($oRoutine->getKey()),
                                        'Dry Run',
                                        'class="btn btn-xs btn-primary"'
                                    );

                                    echo anchor(
                                        Housekeeping::ADMIN_URL . '/run?run=1&routine=' . urlencode($oRoutine->getKey()),
                                        'Run now',
                                        sprintf(
                                            'class="btn btn-xs btn-danger confirm" data-body="Run %s now? This will permanently remove matching items."',
                                            htmlspecialchars($oRoutine->getLabel(), ENT_QUOTES)
                                        )
                                    );

                                    ?>
                                </td>
                                <?php
                            }

                            ?>
                        </tr>
                        <?php
                    }
                }

                ?>
            </tbody>
        </table>
    </div>
</div>
