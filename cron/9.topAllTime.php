<?php

use cvweiss\redistools\RedisQueue;

require_once '../init.php';

if ($redis->get("tobefetched") > 1000) exit();
if ($redis->get("zkb:reinforced") == true) exit();
if ($redis->scard("queueStatsSet") > 1000) exit();

$redisKey = "tq:topAllTime";
$queueTopAlltime = new RedisQueue('queueTopAlltime');
if ($redis->get($redisKey) != "true" && $queueTopAlltime->size() == 0) {
    $iter = $mdb->getCollection('statistics')->find(['calcAlltime' => true]);
    while ($row = $iter->next()) {
        if (@$row['reset'] == true) continue;
        $queueTopAlltime->push($row['_id']);
    }
    $redis->setex($redisKey, 28800, "true");
}

$minute = date('Hi');
while ($minute == date('Hi') && ($id = $queueTopAlltime->pop())) {
    $row = $mdb->findDoc('statistics', ['_id' => $id]);
    calcTop($row);
}

function calcTop($row)
{
    global $mdb;

    if ($row['id'] == 0 || $row['type'] == null) return;

    $currentSum = (int) @$row['shipsDestroyed'];
    Util::out("TopAllTime: " . $row['type'] . ' ' . $row['id'] . ' - ' . $currentSum);

    $parameters = [$row['type'] => $row['id']];
    $parameters['limit'] = 100;
    $parameters['kills'] = true;

    $topLists[] = array('type' => 'character', 'data' => Stats::getTop('characterID', $parameters));
    $topLists[] = array('type' => 'corporation', 'data' => Stats::getTop('corporationID', $parameters));
    $topLists[] = array('type' => 'alliance', 'data' => Stats::getTop('allianceID', $parameters));
    $topLists[] = array('type' => 'faction', 'data' => Stats::getTop('factionID', $parameters));
    $topLists[] = array('type' => 'ship', 'data' => Stats::getTop('shipTypeID', $parameters));
    $topLists[] = array('type' => 'system', 'data' => Stats::getTop('solarSystemID', $parameters));

    $p = $parameters;
    $p['limit'] = 6;
    $p['categoryID'] = 6;
    $topKills = Stats::getTopIsk($p);

    $nextTopRecalc = ceil($currentSum * 1.01);

    $mdb->set('statistics', $row, ['topAllTime' => $topLists, 'topIskKills' => $topKills, 'allTimeSum' => $currentSum, 'nextTopRecalc' => $nextTopRecalc]);
    $mdb->removeField('statistics', $row, 'calcAlltime');
}
