<?php
/**
 * This file is part of cBackup, network equipment configuration backup tool
 * Copyright (C) 2017, Oļegs Čapligins, Imants Černovs, Dmitrijs Galočkins
 *
 * cBackup is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\helpers\Inflector;
use yii\helpers\Json;
use yii\widgets\Pjax;
use app\models\Setting;
use app\helpers\StringHelper;
use app\helpers\GridHelper;
use app\models\Exclusion;

/**
 * @var $this           yii\web\View
 * @var $data           app\models\Node
 * @var $credential     app\models\Credential
 * @var $task_info      app\models\Task
 * @var $int_provider   yii\data\ArrayDataProvider
 * @var $commit_log     array
 * @var $templates      array
 * @var $plugins        object
 * @var $exclusion      boolean
 * @var $networks       array
 * @var $credentials    array
 */
app\assets\NodeAsset::register($this);
app\assets\DataTablesBootstrapAsset::register($this);
app\assets\ScrollingTabsAsset::register($this);

$title       = empty($data->hostname) ? $data->ip : $data->hostname;
$empty_task  = [false, false]; // empty array for defining empty task tab
$this->title = Yii::t('backup', 'Node') . ' ' . $title;
$this->params['breadcrumbs'][] = ['label' => Yii::t('backup', 'Nodes'), 'url' => ['/backup']];
$this->params['breadcrumbs'][] = $this->title;

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

$compareUrlTemplate = Url::to(['compare-two-nodes-files', 'file1' => 'file1_placeholder', 'file2' => 'file2_placeholder', 'ip1' => 'ip1_placeholder', 'ip2' => 'ip2_placeholder']);
$ip1 = $data->hostname;
?>


<div class="row">
    <div class="col-xs-12">
        <div id="nav_tabs" class="nav-tabs-custom">
            <ul class="nav nav-tabs tabs-scroll disable-multirow">
                <li class="active"><a href="#backup_tab" data-toggle="tab"><?= Yii::t('backup', 'Configuration backup') ?></a></li>
                <?php foreach ($plugins as $plugin): ?>
                    <?php if ($plugin->plugin_params['widget_enabled'] == '1'): ?>
                        <li>
                            <?php
                                echo Html::a($plugin->plugin::t('general', Inflector::humanize($plugin->name)), "#tab_{$plugin->name}", [
                                    'class'            => 'load-widget',
                                    'data-toggle'      => 'tab',
                                    'data-widget-url'  => Url::to(['ajax-load-widget', 'node_id' => $data->id, 'plugin' => $plugin->name]),
                                    'data-plugin-name' => $plugin->name
                                ]);
                            ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="pull-right">
                    <a href="javascript:void(0);" id="tab_expand_btn" class="text-muted"><i class="fa fa-expand"></i></a>
                </li>
            </ul>
            <!-- Tab with all information about selected device config backup -->
            <div class="tab-content no-padding">
                <div class="tab-pane active table-responsive" id="backup_tab">
                    <?php if (!empty($backups)): ?>
                        <form id="backup_form">
                            <?= Html::a('<i class="fa fa-download"></i> ' . Yii::t('app', 'Download selected configs'), 'javascript:void(0);', [
                                'id'    => 'download_selected_configs',
                                'class' => 'btn btn-primary pull-right',
                                'style' => 'margin-right: 10px; width: 12%;',
                            ]) ?>
                            <?= Html::a('<i class="fa fa-exchange"></i> ' . Yii::t('app', 'Difference'), 'javascript:void(0);', [
                                'id'    => 'show_diff_button',
                                'data-url-template' => Url::to(['compare-files', 'file1' => 'file1_placeholder', 'file2' => 'file2_placeholder', 'ip' => $data->hostname]),
                                'class' => 'btn btn-default pull-right',
                                'style' => 'margin-right: 10px; width: 12%;'
                            ]) ?>
                            <table class="table table-bordered" style="margin-bottom: 0;">
                                <tr>
                                    <th><?= Yii::t('node', 'Select') ?></th>
                                    <th><?= Yii::t('node', 'Created at') ?></th>
                                    <th class="hidden-sm hidden-xs"><?= Yii::t('node', 'Path') ?></th>
                                    <th><?= Yii::t('app', 'Size') ?></th>
                                    <th><?= Yii::t('app', 'Actions') ?></th>
                                </tr>
                                <?php foreach($backups as $outBackup): ?>
                                    <tr>
                                        <td>
                                            <?= Html::checkbox('selected_backups[]', false, ['value' => $outBackup, 'class' => 'backup-checkbox']);
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

                                                if (($task_info->put == 'file' && !file_exists(Y::param('dataPath') . $file_path)) ||
                                                    ($task_info->put == 'db'   && is_null($outBackup->config))) {
                                                    $conf_exists = Html::tag('i', '', [
                                                        'class'               => 'fa fa-warning text-danger cursor-question',
                                                        'title'               => Yii::t('node', 'Configuration not found'),
                                                        'data-toggle'         => 'tooltip',
                                                        'data-placement'      => 'right',
                                                    ]);

                                                    $disabled = 'disabled';
                                                }

                                                if ($task_info->put == 'file' && is_array($commit_log) && array_key_exists('0', $commit_log) ) {
                                                    $com_hash   = $commit_log[0][0];
                                                    $com_status = '';
                                                }

                                                echo Html::tag('span', $path, ['class' => 'margin-r-5']) . $conf_exists;
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                                $file_size = '&mdash;';
                                                if ($task_info->put == 'file' && file_exists(Y::param('dataPath') . $file_path)) {
                                                    $file_size = StringHelper::beautifySize(filesize(Y::param('dataPath') . $file_path));
                                                }
                                                echo $file_size;
                                            ?>
                                        </td>
                                        <td class="narrow">
                                            <?= Html::a('<i class="fa fa-eye"></i> ' . Yii::t('app', 'View'), '#config_content', [
                                                'id'          => 'show_config',
                                                'class'       => 'btn btn-xs btn-default margin-r-5 ' . $disabled,
                                                'data-toggle' => "collapse",
                                                'data-parent' => '#accordion',
                                                'data-url'    => Url::to(['ajax-load-config']),
                                                'data-params' => json_encode([
                                                    'node_id' => $data->id,
                                                    'backup'  => $outBackup,
                                                    'put'     => $task_info->put,
                                                ])
                                            ]) ?>
                                            <?= Html::a('<i class="fa fa-download"></i> ' . Yii::t('app', 'Download'), Url::to(['ajax-download', 'hostname' => $data->hostname, 'outBackup' => $outBackup , 'id' => $data->id, 'put' => $task_info->put, 'hash' => null]), [
                                                'class'         => 'btn btn-xs btn-default margin-r-5' . $disabled,
                                                'id'            => 'single_download',
                                                'title'         => Yii::t('app', 'Download')
                                            ]) ?>
                                            <?= Html::a('<i class="fa fa-exchange"></i> ' . Yii::t('app', 'Compare with other node conf'), 'javascript:void(0);', [
                                                'class'         => 'btn btn-xs btn-default',
                                                'id'            => 'compare_button',
                                                'title'         => Yii::t('app', 'Compare with other node conf'),
                                                'data-dismiss'  => 'modal',
                                                'data-toggle'   => 'modal',
                                                'data-target'   => '#backupModal',
                                            ]) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </form>
                    <?php else: ?>
                        <div class="callout callout-info" style="margin: 10px;">
                            <p><?= Yii::t('backup', 'Configuration not found') ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="panel-group" id="accordion" style="margin-bottom: 0">
                        <div class="panel panel-no-border panel-default">
                            <div id="config_content" class="panel-collapse collapse">
                                <div class="panel-body">
                                    <?= Html::tag('iframe', '', [
                                        'id'    => 'config_iframe',
                                        'style' => 'width: 100%; font-family: monospace; border: 1px solid silver; background: #efefef;',
                                        'src'   => '#'
                                    ]) ?>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-no-border panel-default" style="margin-top: 0">
                            <div id="diff_content" class="panel-collapse collapse">
                                <div class="panel-body">
                                    <span class="loader" style="margin-left: 35%;">
                                        <?= Html::img('@web/img/modal_loading.gif', ['alt' => Yii::t('app', 'Loading...')]) ?>
                                    </span>
                                    <div id="file_diff"></div>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-no-border panel-default">
                            <div id="diff_result" class="panel-collapse collapse">
                                <div class="panel-body">
                                    <pre id="diff_contents" style="width: 100%; font-family: monospace; border: 1px solid silver; background: #efefef;"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php foreach ($plugins as $plugin): ?>
                    <?php if ($plugin->plugin_params['widget_enabled'] == '1'): ?>
                        <div class="tab-pane" id="tab_<?= $plugin->name ?>">
                            <div id="widget_loading_<?= $plugin->name ?>" style="padding: 30px 0 30px 0;">
                                <span class="loader" style="margin-left: 32%;">
                                    <?= Html::img('@web/img/modal_loading.gif', ['alt' => Yii::t('app', 'Loading...')]) ?>
                                </span>
                            </div>
                            <div id="widget_content_<?= $plugin->name ?>" style="padding: 10px;"></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        var checkboxes = document.querySelectorAll('input[name="selected_backups[]"]');
        var viewButtons = document.querySelectorAll('[id^="show_config"]');
        var downloadButton = document.getElementById('download_selected_configs');
        var singleDwonload =document.querySelectorAll('[id^="single_download"]');
        var downloadUrl = '';
        var diffButton = document.getElementById('show_diff_button');
        var diffResult = document.getElementById('diff_result');
        var diffContent = document.getElementById('diff_contents');
        var hostname = '<?= $data->hostname ?>'
        var compare = document.querySelectorAll('[id^="compare_button"]');
        var compareButton = document.querySelectorAll('[id^="compare_button"]');
        var selectedFiles = [];
        var selectedModalFile = null;
        var modalIp = '';
        var compareUrl = '<?= $compareUrlTemplate ?>';
        var ip1 = '<?= $ip1 ?>';

        function updateButtonStates() {
            var selectedCount = Array.from(checkboxes).filter(checkbox => checkbox.checked).length;
            

            // view buttons
            viewButtons.forEach(btn => {
                btn.classList.toggle('disabled', selectedCount > 1);
                btn.setAttribute('aria-disabled', selectedCount > 1);
            });
            
            singleDwonload.forEach(btn => {
                btn.classList.toggle('disabled', selectedCount > 1);
                btn.setAttribute('aria-disabled', selectedCount > 1);
            })

            compare.forEach(btn => {
                btn.classList.toggle('disabled', selectedCount > 1 || selectedCount == 0);
                btn.setAttribute('aria--disabled', selectedCount > 1);
            })



            if (selectedCount == 2) {
                diffButton.style.display = 'block';
            } else {
                diffButton.style.display = 'none'; 
                $(diffResult).collapse('hide');
            }

            // download button
            if (selectedCount > 1) {
                downloadButton.style.display = 'block';
            } else {
                downloadButton.style.display = 'none';
            }
        }

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateButtonStates);
        });

        updateButtonStates();

        diffButton.addEventListener('click', function() {
            const selectedCheckboxes = Array.from(checkboxes).filter(checkbox => checkbox.checked);
            if (selectedCheckboxes.length === 2) {
                compareConfigs(selectedCheckboxes[0].value, selectedCheckboxes[1].value);
            } else {
                alert('Please select two files for comparison.');
            }
        });

        window.addEventListener('message', function(event) {
            if (event.data.action === 'selectFiles') {
                selectedModalFile = event.data.files[0];
                modalIp = event.data.ip;
                var selectedFile = Array.from(checkboxes).find(checkbox => checkbox.checked)?.value;
                compareTwoNodesConfigs(selectedFile, selectedModalFile, ip1, modalIp);
            }
        });

        function compareTwoNodesConfigs(file1, file2, ip1, ip2) {
            const url = compareUrl
                .replace('file1_placeholder', file1)
                .replace('file2_placeholder', file2)
                .replace('ip1_placeholder', ip1)
                .replace('ip2_placeholder', ip2);

            fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                if (data.identical) {
                    diffContent.innerHTML = '<p>' + data.message + '</p>';
                } else {
                    const base = difflib.stringAsLines(data.file1);
                    const newtxt = difflib.stringAsLines(data.file2);
                    const sm = new difflib.SequenceMatcher(base, newtxt);
                    const opcodes = sm.get_opcodes();

                    diffContent.innerHTML = "";
                    diffContent.appendChild(diffview.buildView({
                        baseTextLines: base,
                        newTextLines: newtxt,
                        opcodes: opcodes,
                        baseTextName: file1,
                        newTextName: file2,
                        contextSize: 3,
                        viewType: 0
                    }));
                }
                $(diffResult).collapse('show');
            })
            .catch(error => console.error('Error fetching diff:', error));
        }

        function compareConfigs(file1, file2) {
            const url = diffButton.getAttribute('data-url-template')
                .replace('file1_placeholder', file1)
                .replace('file2_placeholder', file2);

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }

                    if (data.identical) {
                        diffContent.innerHTML = '<p>' + data.message + '</p>';
                    } else {
                        const base = difflib.stringAsLines(data.file1);
                        const newtxt = difflib.stringAsLines(data.file2);
                        const sm = new difflib.SequenceMatcher(base, newtxt);
                        const opcodes = sm.get_opcodes();

                        diffContent.innerHTML = "";
                        diffContent.appendChild(diffview.buildView({
                            baseTextLines: base,
                            newTextLines: newtxt,
                            opcodes: opcodes,
                            baseTextName: file1,
                            newTextName: file2,
                            contextSize: 3,
                            viewType: 0
                        }));
                    }

                    $(diffResult).collapse('show');
                })
                .catch(error => console.error('Error fetching diff:', error));
        }

        downloadButton.addEventListener('click', function() {
            var selectedBackups = Array.from(checkboxes)
                .filter(checkbox => checkbox.checked)
                .map(checkbox => checkbox.value);

            if (selectedBackups.length > 0) {
                var url = '<?= Url::to(['ajax-download-multiple']) ?>';
                var params = { backups: selectedBackups, hostname: hostname };

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= Yii::$app->request->csrfToken ?>'
                    },
                    body: JSON.stringify(params)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.downloadUrl;
                        if (data.filename) {
                            fetch('<?= Url::to(['ajax-delete-zip-file']) ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': '<?= Yii::$app->request->csrfToken ?>'
                                },
                                body: JSON.stringify({ filename: data.filename })
                            })
                            .then(response => response.json())
                            .then(deleteData => {
                                if (deleteData.success) {
                                    console.log(deleteData.message);
                                } else {
                                    console.error(deleteData.message);
                                }
                            })
                            .catch(error => console.error('Error deleting file:', error));
                        }
                        
                    } else {
                        alert('<?= Yii::t('app', 'Download failed. Please try again.') ?>');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });

        $('#backupModal').on('show.bs.modal', function (e) {
            $.ajax({
                url: '<?= \yii\helpers\Url::to(['ajax-load-nodes']) ?>',
                method: 'GET',
                success: function(data) {
                    $('#node-list').html(data);

                    $('#search-nodes').on('input', function() {
                        var searchText = $(this).val().toLowerCase();
                        $('#node-list-items .node-item').each(function() {
                            var nodeText = $(this).text().toLowerCase();
                            $(this).toggle(nodeText.includes(searchText));
                        });
                    });
                }
            });
        });

        $(document).on('click', '.node-item', function() {
            var nodeId = $(this).data('id');
            
            $.ajax({
                url: '<?= Url::to(['ajax-load-for-node-config']) ?>',
                method: 'POST',
                data: { 
                    node_id: nodeId,
                },
                success: function(data) {
                    $('#conf-list').html(data).show();
                    $('#node-list').hide();
                    $('#save-changes').show();
                    $('.modal-title').text('Select Configuration');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Error loading configurations:', textStatus, errorThrown);
                    alert('Failed to load configurations. Please try again.');
                }
            });
        });

        $(document).on('click', '#back-to-node-selection', function() {
            $('#conf-list').hide();
            $('#node-list').show();
            $('#save-changes').hide();
            $('.modal-title').text('Select Node');
        });

        $('#save-changes').click(function() {
            $('#conf-list').hide();
            $('#node-list').show();
            $('#save-changes').hide();
            $('.modal-title').text('Select Node');
        });
    });
</script>


<!-- Modal -->
<div class="modal fade" id="backupModal" tabindex="-1" role="dialog" aria-labelledby="backupModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="width: 40%;" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="backupModalLabel">Select conf</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modal-content-placeholder">
                    <!-- Prvo učitaj listu nod-ova -->
                    <div id="node-list">
                        <!-- AJAX će ovde ubaciti nod-ove -->
                    </div>
                    <!-- Ovdje će se prikazivati konfiguracije nakon selekcije nod-a -->
                    <div id="conf-list" style="display:none;">
                        <!-- AJAX će ovde ubaciti conf-ove -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<!-- form modal -->
<div id="job_modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span></button>
                <h4 class="modal-title"><?= Yii::t('app', 'Wait...') ?></h4>
            </div>
            <div class="modal-body">
                <span style="margin-left: 24%;"><?= Html::img('@web/img/modal_loading.gif', ['alt' => Yii::t('app', 'Loading...')]) ?></span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= Yii::t('app', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- form modal -->
<div id="download_modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-light-blue-active">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span></button>
                <h4 class="modal-title"><?= Yii::t('app', 'Wait...') ?></h4>
            </div>
            <div class="modal-body">
                <span style="margin-left: 24%;"><?= Html::img('@web/img/modal_loading.gif', ['alt' => Yii::t('app', 'Loading...')]) ?></span>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= Yii::t('app', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>
