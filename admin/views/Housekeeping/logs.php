<?php

use Nails\Housekeeping\Admin\Controller\Housekeeping;

/**
 * @var string[] $aFiles
 * @var string   $sSelected
 * @var string   $sContents
 */

?>
<div class="group-housekeeping logs">
    <p>
        <a href="<?=Housekeeping::url()?>" class="btn btn-primary btn-sm">&lsaquo; Back to routines</a>
    </p>
    <hr>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th style="width:300px;">File</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody class="align-middle">
                <?php

                if (empty($aFiles)) {

                    ?>
                    <tr>
                        <td colspan="2" class="no-data">
                            No items found
                        </td>
                    </tr>
                    <?php

                } else {
                    foreach ($aFiles as $sPath) {
                        $sName = basename($sPath);
                        ?>
                        <tr>
                            <td>
                                <?=htmlspecialchars($sName)?>
                            </td>
                            <td class="actions">
                                <?php

                                if ($sName !== $sSelected) {
                                    echo anchor(
                                        Housekeeping::url('logs?file=' . urlencode($sName)),
                                        'View Tail',
                                        'class="btn btn-default btn-xs"'
                                    );
                                } else {

                                    echo '<pre style="max-height: 70vh; overflow: auto; background: #111; color: #eee; padding: 1em; margin: 0; text-align: left;">';
                                    echo htmlspecialchars($sContents !== '' ? $sContents : 'Nothing to display.');
                                    echo '</pre>';
                                }

                                ?>
                            </td>
                        </tr>
                        <?php
                    }
                }

                ?>
            </tbody>
        </table>
    </div>
</div>
