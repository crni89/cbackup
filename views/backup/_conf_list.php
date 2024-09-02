<?php
use yii\helpers\Html;

/* @var $nodes array */

function formatFilename($filename) {
    // Ukloni prvi deo sve do _
    $filename = preg_replace('/^\d+.+_/', '', $filename);
    // Ukloni ekstenziju
    $filename = str_replace('.txt', '', $filename);
    // Konvertuj u timestamp
    $timestamp = strtotime($filename);
    // Formatiraj datum
    return date('d.m.Y H:i:s', $timestamp);
}

?>

<i id="back-to-node-selection" class="fa fa-chevron-circle-left" style="cursor: pointer; margin-bottom: 10px;">
    <?= Yii::t('app', 'Back') ?>
</i>

<table class="table table-bordered" style="margin-bottom: 0;">
    <tr>
        <th><?= Yii::t('node', 'Select') ?></th>
        <th><?= Yii::t('node', 'Date') ?></th>
        <th><?= Yii::t('node', 'Configuration') ?></th>
    </tr>
    <?php foreach($backups as $outBackup): ?>
        <tr>
            <td>
                <?= Html::checkbox('selected_backups[]', false, ['value' => $outBackup, 'class' => 'file-checkbox']);
                ?>
            </td>
            <td>
                <?= $date = formatFilename($outBackup); ?>
            </td>
            <td class="hidden-sm hidden-xs">
                <?php
                    $com_status  = 'disabled';
                    $com_hash    = '';
                    $conf_exists = '';
                    $disabled    = '';
                    $path        = Yii::t('network', 'Database');
                    $file_path   = DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $data->hostname . DIRECTORY_SEPARATOR . $outBackup;

                    if ($task_info->put == 'file') {
                        $path = Html::tag('span', '<i class="fa fa-folder-open-o"></i>', [
                            'class'          => 'text-info cursor-question',
                            'title'          => Y::param('dataPath'),
                            'data-toggle'    => 'tooltip',
                            'data-placement' => 'left',
                        ]) . $file_path;
                    }
                    
                    if (($task_info->put == 'file' && !file_exists(Y::param('dataPath') . $file_path))) {
                        $conf_exists = Html::tag('i', '', [
                            'class'               => 'fa fa-warning text-danger cursor-question',
                            'title'               => Yii::t('node', 'Configuration not found'),
                            'data-toggle'         => 'tooltip',
                            'data-placement'      => 'right',
                        ]);

                        $disabled = 'disabled';
                    }

                    echo Html::tag('span', $path, ['class' => 'margin-r-5']) . $conf_exists;
                ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<button type="button" id="select-backup-button" class="btn btn-primary" style="margin-top: 5px; width: 100%;">Select</button>

<script>

    var ipAddress = '<?= $data->hostname ?>';
    var checkboxes = document.querySelectorAll('input[name="selected_backups[]"]');
    var selectButton = document.querySelectorAll('[id^="select-backup-button"]');

    function updateButtonStates() {
        var selectedCount = Array.from(checkboxes).filter(checkbox => checkbox.checked).length;

        selectButton.forEach(btn => {
            btn.classList.toggle('disabled', selectedCount > 2);
            btn.setAttribute('aria-disabled', selectedCount > 2);
        });
    }
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateButtonStates);
    });

    updateButtonStates();

    $('#select-backup-button').click(function() {
        var selectedFiles = [];
        $('input.file-checkbox:checked').each(function() {
            selectedFiles.push($(this).val());
        });

        if (selectedFiles.length > 0 && selectedFiles.length < 2 ) {
            window.parent.postMessage({
                action: 'selectFiles',
                files: selectedFiles,
                ip: ipAddress
            }, '*');
            window.parent.$('#backupModal').modal('hide');
        } else {
            window.alert('Izaberite max 1 fajl!');
        }
    });
</script>