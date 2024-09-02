<?php

use yii\db\Migration;

/**
 * Handles the creation of table `backups`.
 */
class m240802_115240_create_backups_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('backups', [
            'id' => $this->primaryKey(),
            'date' => $this->timestamp(),
            'ip' => $this->string(),
            'hostname' => $this->string(),
            'node_id' => $this->integer()
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('backups');
    }
}
