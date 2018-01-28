<?php
require("header.php");

/*function log($team, $message) {
    if (Module::isRegistered('LogSubSys')) {
        Module::run('LogSubSys', array('createEntry', T_LOG_OFFSEASON, $team->owned_by_coach_id, "'$team->name' (ID=$team->team_id) " . $message));
    }
}*/

$return = array(
    'status' => 'error',
    'message' => 'An unknown error occurred'
);

$_VISSTATE['COOCKIE'] = Coach::cookieLogin(); # If not already logged in then check for login-cookie and try to log in using the stored credentials.

if (!Coach::isLoggedIn()) {
    $return['message'] = "You must be logged into OBBLM to use this webservice.";
    echo(json_encode($return));
    return;
}

$team = new Team($_POST['team_id']);

if (!$team->allowEdit()) {
    $return['message'] = "You do not have permission to edit this team.";
    echo(json_encode($return));
    return;
}

$funding = $team->calculateOffseasonFunding();
$offseason_treasury = $funding;
$query_results = array();
$free_coaches = 0;
//log($team, 'received $funding in offseason funding for season $team->current_season');

// Validation
// - treasury >= 0, players >= 11, players <= 16, goods <= max, no active players same number, players <= pos max

$players = $_POST['active_players'];
foreach ($players as $player) {
    $action = $player['action'];
    $player_id = (int) str_replace('player', '', $player['id']);
    if ($action == 'keep') {
        //log($team, 'kept $player_id at a cost of '. $player['cost']);
        $query_results[] = mysql_query("UPDATE players SET season = season + 1 WHERE player_id = $player_id");
        $offseason_treasury -= $player['cost'];
    } else {
        //log($team, 'released $player_id');
        $query_results[] = mysql_query("UPDATE players SET date_sold = NOW() WHERE player_id = $player_id");
        SQLTriggers::run(T_SQLTRIG_PLAYER_DPROPS, array('id' => $player_id, 'obj' => (object) array('player_id' => $player_id, 'owned_by_team_id' => $team->team_id)));

        if ($action == 'coach') {
            $free_coaches += 1;
        }
    }
}

$rookies = $_POST['rookies'];
foreach ($rookies as $rookie) {
    $input = array(
        'nr'                => $rookie['number'],
        'f_pos_id'          => $rookie['position'],
        'owned_by_team_id'  => $team->team_id,
        'name'              => "'".mysql_real_escape_string($rookie['name'])."'",
        'date_bought'       => 'NOW()'
    );

    foreach (array('ach_ma', 'ach_st', 'ach_ag', 'ach_av', 'extra_spp') as $f) {$input[$f] = 0;}

    $query = "INSERT INTO players (".implode(',',array_keys($input)).") VALUES (".implode(',', array_values($input)).")";
    //log($team, 'hired #'.$input['nr'].' '.$input['name'].' to play '.$input['f_pos_id']);
    $query_results[] = mysql_query($query);
    $pid = mysql_insert_id();
    SQLTriggers::run(T_SQLTRIG_PLAYER_NEW, array('id' => $pid, 'obj' => (object) array('player_id' => $pid, 'owned_by_team_id' => $team->team_id)));

    $offseason_treasury -= $rookie['cost'];
}

$goods = $team->getGoods(false);
$team_goods = $_POST['team_goods'];
$team_goods_keys = array(
    'rr'    => 'rerolls',
    'ff'    => 'ff_bought',
    'ac'    => 'ass_coaches',
    'cheer' => 'cheerleaders',
    'apo'   => 'apothecary'
);
$set = "current_season = 0";
foreach ($team_goods as $team_good) {
    $thing = $team_goods_keys[$team_good['id']];
    $qty = (int) $team_good['quantity'];

    if ($thing == 'ff_bought') {
        $set .= ", $thing = $thing + $qty - $team_good[id]";
        $qty = max($qty - $team_good['initial'], 0);
    } else {
        $set .= ", $thing = $qty";
    }

    $cost = (($thing == 'ass_coaches') ? max($qty - $free_coaches, 0) : $qty) * $goods[$thing]['cost'];
    //log($team, 'purchased $qty $thing at a cost of $cost');
    $offseason_treasury -= $cost;
}
//log($team, 'has a treasury of $offseason_treasury to start next season');
$set .= ", treasury = $offseason_treasury";

$query = "UPDATE teams SET $set WHERE team_id = $team->team_id";
$query_results[] = mysql_query($query);
SQLTriggers::run(T_SQLTRIG_TEAM_DPROPS, array('id' => $team->team_id, 'obj' => $team));

if ($query_results && array_product($query_results)) {
    $return['status'] = "success";
    $return['message'] = "Team updated successfully. Off-season has been completed.";
} else {
    $return['message'] = 'A database error occurred. Team may not have been updated. Contact a league administrator.';
}

echo json_encode($return);
