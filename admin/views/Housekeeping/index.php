<?php

use Nails\Admin\Housekeeping\Housekeeping;
use Nails\Housekeeping\Interfaces\Routine;

/**
 * @var array<int, array{routine: Routine, component: string, last_run: array<string, mixed>|null}> $aRows
 * @var bool $bCanExecute
 */

?>
<div class="group-housekeeping browse">
    <p>
        Housekeeping routines remove expired data, files, and other residue on a schedule.
        Every removal is written to a dedicated log file.
        <a href="<?=siteUrl(Housekeeping::ADMIN_URL . '/logs')?>">View audit logs</a>
    </p>
    <div class="table-responsive">
        <table class="table table-striped table-hover table-bordered">
            <thead>
                <tr>
                    <th>Routine</th>
                    <th>Component</th>
                    <th>Schedule</th>
                    <th>Enabled</th>
                    <th>Last run</th>
                    <?php if ($bCanExecute) { ?>
                        <th class="actions" style="width:220px;">Actions</th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($aRows)) {
                    ?>
                    <tr>
                        <td colspan="<?=$bCanExecute ? 6 : 5?>" class="no-data">
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
                                <?php if ($oRoutine->getDescription() !== '' && $oRoutine->getDescription() !== $oRoutine->getLabel()) { ?>
                                    <br><small class="text-muted"><?=htmlspecialchars($oRoutine->getDescription())?></small>
                                <?php } ?>
                                <br><small><code><?=htmlspecialchars($oRoutine->getKey())?></code></small>
                            </td>
                            <td><?=htmlspecialchars($aRow['component'])?></td>
                            <td><code><?=htmlspecialchars($oRoutine->getCronExpression())?></code></td>
                            <td class="text-center">
                                <?php if ($oRoutine->isEnabled()) { ?>
                                    <span class="label label-success">Yes</span>
                                <?php } else { ?>
                                    <span class="label label-default">No</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php
                                if (empty($aLastRun['at'])) {
                                    echo '<span class="text-muted">Never</span>';
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
                            <?php if ($bCanExecute) { ?>
                                <td class="actions">
                                    <?=form_open(Housekeeping::ADMIN_URL, ['style' => 'display:inline-block'])?>
                                        <input type="hidden" name="run" value="1">
                                        <input type="hidden" name="routine" value="<?=htmlspecialchars($oRoutine->getKey())?>">
                                        <input type="hidden" name="dry_run" value="1">
                                        <button type="submit" class="btn btn-xs btn-default">Dry run</button>
                                    <?=form_close()?>
                                    <?=form_open(Housekeeping::ADMIN_URL, ['style' => 'display:inline-block'])?>
                                        <input type="hidden" name="run" value="1">
                                        <input type="hidden" name="routine" value="<?=htmlspecialchars($oRoutine->getKey())?>">
                                        <button
                                            type="submit"
                                            class="btn btn-xs btn-danger"
                                            onclick="return confirm('Run <?=htmlspecialchars($oRoutine->getLabel(), ENT_QUOTES)?> now? This will permanently remove matching items.');"
                                        >Run now</button>
                                    <?=form_close()?>
                                </td>
                            <?php } ?>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
