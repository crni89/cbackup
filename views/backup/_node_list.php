<?php
use yii\helpers\Html;

/* @var $nodes array */

?>

<div class="form-group">
    <input type="text" id="search-nodes" class="form-control" placeholder="Search by IP or Hostname">
</div>

<ul id="node-list-items" class="list-group">
    <?php foreach ($nodes as $node): ?>
        <li class="list-group-item node-item" data-id="<?= $node['id'] ?>">
            <strong style="cursor: pointer;"><?= Html::encode($node['hostname']) ?></strong> (<?= Html::encode($node['ip']) ?>)
        </li>
    <?php endforeach; ?>
</ul>
