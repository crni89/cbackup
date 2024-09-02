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

namespace app\models;

use Yii;
use \yii\db\ActiveRecord;
use \yii\behaviors\TimestampBehavior;
use \yii\db\Expression;


/**
 * This is the model class for table "{{%backups}}".
 *
 * @property integer $id
 * @property string $time
 * @property integer $node_id
 * @property string $hash
 * @property string $config
 *
 * @property Node $node
 *
 * @package app\models
 */
class Backup extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%backups}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['date'], 'safe'],
            [['node_id'], 'integer'],
            [['ip'], 'string'],
            [['hostname'], 'string'],
            // [['node_id'], 'unique'],
            [['node_id'], 'exist', 'skipOnError' => true, 'targetClass' => Node::class, 'targetAttribute' => ['node_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'      => Yii::t('app', 'ID'),
            'node_id' => Yii::t('app', 'Node ID'),
            'date'    => Yii::t('app', 'Date'),
            'ip'    => Yii::t('app', 'IP'),
            'hostname'  => Yii::t('app', 'Hostname'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNode()
    {
        return $this->hasOne(Node::class, ['id' => 'node_id']);
    }

    /**
     * Behaviors
     *
     * @return array
     */
    // public function behaviors()
    // {
    //     return [
    //         [
    //             'class' => TimestampBehavior::class,
    //             'createdAtAttribute' => 'time',
    //             'updatedAtAttribute' => 'time',
    //             'value' => new Expression('NOW()'),
    //         ],
    //     ];
    // }
}
