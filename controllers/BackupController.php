<?php

namespace app\controllers;

use app\models\Device;
use app\models\Network;
use app\models\Credential;
use yii\helpers\ArrayHelper;
use app\models\search\NodeSearch;
use app\models\DeviceAuthTemplate;
use yii\web\Controller;
use app\models\Exclusion;
use app\models\Node;
use app\models\Plugin;
use app\models\Config;
use app\models\Task;
use yii\data\ArrayDataProvider;
use yii\data\Sort;
use app\models\OutBackup;
use yii\helpers\Html;
use yii\web\Response;
use yii\helpers\Url;
use ZipArchive;
use Yii;

/**
 * @package app\controllers
 */
class BackupController extends Controller {

    public $defaultAction = 'list';


    public function actionList() {
        $searchModel = new NodeSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $devices = ArrayHelper::map(Device::find()->all(), 'id', function ($data) {
            return "{$data->vendor} {$data->model}";
        }, 'vendor');

        return $this->render('list', [
            'searchModel'  => $searchModel,
            'dataProvider' => $dataProvider,
            'networks'     => Network::find()->select('network')->indexBy('id')->asArray()->column(),
            'credentials'  => Credential::find()->select('name')->indexBy('id')->asArray()->column(),
            'auth_list'    => DeviceAuthTemplate::find()->select('name')->indexBy('name')->asArray()->column(),
            'devices'      => $devices
        ]);
    }


    public function actionView($id)
    {

        $id   = intval($id);
        $data = Node::findOne(['id' => $id]);
        $cid  = Node::getCredentialsId($id);
        $ex   = Exclusion::exists($data->ip);

        // Folder path based on nodeId
        $folderPath = Yii::getAlias('@app/data/backup/') . $data->hostname;
        
        // Check if the folder exists
        if (!is_dir($folderPath)) {
            Yii::$app->session->setFlash('error', "Backup configs for Node {$data->ip} does not exist.");
            return $this->redirect(['list']);
        }
        
        // Get files from the directory
        $files = scandir($folderPath);
        $files = array_diff($files, ['.', '..']); // Remove '.' and '..' from the list

        rsort($files);

        /** Create alternative interfaces dataprovider */
        $interfaces   = ArrayHelper::toArray($data->altInterfaces);
        $int_provider = new ArrayDataProvider([
            'allModels' => $interfaces,
            'sort'  => new Sort(['attributes' => ['ip'], 'defaultOrder' => ['ip' => SORT_ASC]]),
            'pagination' => [
                'pageSize' => 9,
            ],
        ]);

        /** Create networks array for dropdownlist */
        $networks = Network::find()->select(['id', 'network', 'description'])->asArray()->all();
        $networks = ArrayHelper::map($networks, 'id', function ($data) {
            $description = (!empty($data['description'])) ? "- {$data['description']}" : "";
            return "{$data['network']} {$description}";
        });

        return $this->render('view', [
            'data'         => $data,
            'exclusion'    => $ex,
            'credential'   => Credential::findOne(['id' => $cid]),
            'task_info'    => Task::findOne('backup'),
            'commit_log'   => (Config::isGitRepo()) ? Node::getBackupCommitLog($id) : null,
            'int_provider' => $int_provider,
            'templates'    => DeviceAuthTemplate::find()->select('name')->indexBy('name')->asArray()->column(),
            'networks'     => $networks,
            'plugins'      => Plugin::find()->where(['enabled' => '1', 'widget' => 'node'])->all(),
            'credentials'  => Credential::find()->select('name')->indexBy('id')->asArray()->column(),
            'backups'      => $files
        ]);
    }

    /**
     * Load config via Ajax
     *
     * @return bool|mixed|string
     */
    public function actionAjaxLoadConfig()
    {

        $response = Yii::t('backup', 'File not found');
        if (isset($_POST)) {
            $_post = Yii::$app->request->post();
            
            /** Load config from DB */
            if ($_post['put'] == 'db') {
                $db_backup = OutBackup::find()->select('config')->where(['node_id' => $_post['node_id']]);
                if ($db_backup->exists()) {
                    $config   = $db_backup->column();
                    $response = array_shift($config);
                }
            }

            $node = Node::getNode($_post['node_id']);
            /** Load config from file */
            if ($_post['put'] == 'file') {
                $path_to_file = \Y::param('dataPath') . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $node['hostname'] . DIRECTORY_SEPARATOR . $_post['backup'];
                if (file_exists($path_to_file)) {
                    $response = file_get_contents($path_to_file);
                }
            }
        }

        return Html::tag('pre', Html::encode($response));

    }

    public function actionAjaxLoadForNodeConfig(){

        if (Yii::$app->request->isPost) {
            $_post = Yii::$app->request->post();
            
            $data = Node::findOne(['id' => $_post['node_id']]);

            // Folder path based on nodeId
            $folderPath = Yii::getAlias('@app/data/backup/') . $data->hostname;
            
            // Check if the folder exists
            if (!is_dir($folderPath)) {
                Yii::$app->session->setFlash('error', "Backup configs for Node {$data->ip} does not exist.");
                return $this->redirect(['list']);
            }
            
            // Get files from the directory
            $files = scandir($folderPath);
            $files = array_diff($files, ['.', '..']); // Remove '.' and '..' from the list

            rsort($files);
        }

        return $this->renderPartial('_conf_list', [
            'backups' => $files,
            'data' => $data,
            'task_info'    => Task::findOne('backup'),
        ]);
    }

    public function actionAjaxLoadNodes() {
        $nodes = Node::getNodes();
        
        return $this->renderPartial('_node_list', ['nodes' => $nodes]);
    }
    // public function actionAjaxDownload($id, $outBackup, $hostname, $put, $hash = null)
    // {
    //     return $this->renderPartial('_download_modal', [
    //         'outBackup' => $outBackup,
    //         'hostname'   => $hostname,
    //         'id' => $id,
    //         'put'  => $put,
    //         'hash' => $hash
    //     ]);
    // }

    public function actionAjaxCompare()
    {
        return $this->renderPartial('_compare_modal');
    }

    public function actionAjaxDownloadMultiple()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $backups = Yii::$app->request->post('backups');
        $hostname = Yii::$app->request->post('hostname');

        if (!empty($backups)) {
            $zip = new ZipArchive();
            $zipFilename = 'config_' . date('d.m.Y') . '.zip';
            $zipFilepath = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $zipFilename;

            if ($zip->open($zipFilepath, ZipArchive::CREATE) === TRUE) {
                foreach ($backups as $backup) {
                    $parts = explode('_', $backup, 2);
                    if(isset($parts[0])){
                        $id = $parts[0];
                        $backupPath = \Y::param('dataPath') . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $hostname . DIRECTORY_SEPARATOR . $backup;
                        if (file_exists($backupPath)) {
                            $zip->addFile($backupPath, basename($backupPath));
                        }
                    }
                }
                $zip->close();

                return [
                    'success' => true,
                    'downloadUrl' => Url::to('@web/' . $zipFilename, true),
                    'filename' => $zipFilename
                ];
            }
        }

        return [
            'success' => false,
        ];
    }

    public function actionAjaxDeleteZipFile()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $filename = Yii::$app->request->post('filename');

        $filepath = Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $filename;
        
        if (file_exists($filepath) && unlink($filepath)) {
            return [
                'success' => true,
                'message' => "File $filename deleted successfully."
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to delete file $filename."
            ];
        }
    }

    public function actionCompareFiles($file1, $file2, $ip){
        // Paths to the backup folder
        $folderPath = \Y::param('dataPath') . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $ip;

        $path1 = $folderPath . DIRECTORY_SEPARATOR . $file1;
        $path2 = $folderPath . DIRECTORY_SEPARATOR . $file2;

        // Check if both files exist
        if (!file_exists($path1) || !file_exists($path2)) {
            Yii::$app->session->setFlash('error', "One or both files do not exist.");
            return $this->redirect(['view']);
        }

        // Get the content of both files
        $content1 = file_get_contents($path1);
        $content2 = file_get_contents($path2);

        // Compare the files
        if ($content1 === $content2) {
            return $this->asJson(['error' => false, 'identical' => true, 'message' => 'The files are identical.']);
        } else {
            $diff = xdiff_string_diff($content1, $content2);
            return $this->asJson(['error' => false, 'identical' => false, 'file1' => $content1, 'file2' => $content2]);
        }
    }

    public function actionCompareTwoNodesFiles($file1, $file2, $ip1, $ip2){
        // Paths to the backup folder
        $folderPath1 = \Y::param('dataPath') . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $ip1;
        $folderPath2 = \Y::param('dataPath') . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $ip2;


        $path1 = $folderPath1 . DIRECTORY_SEPARATOR . $file1;
        $path2 = $folderPath2 . DIRECTORY_SEPARATOR . $file2;

        // Check if both files exist
        if (!file_exists($path1) || !file_exists($path2)) {
            Yii::$app->session->setFlash('error', "One or both files do not exist.");
            return $this->redirect(['view']);
        }

        // Get the content of both files
        $content1 = file_get_contents($path1);
        $content2 = file_get_contents($path2);

        // Compare the files
        if ($content1 === $content2) {
            return $this->asJson(['error' => false, 'identical' => true, 'message' => 'The files are identical.']);
        } else {
            $diff = xdiff_string_diff($content1, $content2);
            return $this->asJson(['error' => false, 'identical' => false, 'file1' => $content1, 'file2' => $content2]);
        }
    }


    public function actionAjaxDownload($id, $outBackup, $hostname, $put, $hash = null)
    {

        $config = '';
        $suffix = null;

        /** Get configuration backup based on put */
        if(!empty($hash)) {

            $meta   = Node::getCommitMetaData($hash);
            $config = Node::getBackupGitVersion($id, $hash);

            if( array_key_exists(3, $meta) ) {
                $suffix = preg_replace(['/:/', '/[^\d|\-]/'], ['-', '_'], $meta[3]);
                $suffix = ".".substr($suffix, 0, -7);
            }

        }
        elseif ($put == 'file') {
            $file_path = \Y::param('dataPath') . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . $hostname . DIRECTORY_SEPARATOR . $outBackup;
            $config    = file_get_contents($file_path);
        }else {
            \Y::flashAndRedirect('warning', Yii::t('node', 'Unknown backup destination passed'), 'node/view', ['id' => $id]);
            Yii::$app->end();
        }

        if( isset($crlf) && $crlf == true ) {
            $config = preg_replace('~\R~u', "\r\n", $config);
        }

        return Yii::$app->response->sendContentAsFile($config, "conf{$suffix}.$outBackup", [
            'mimeType' => 'text/plain',
            'inline'   => false,
        ]);

    }
}