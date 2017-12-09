<?php

/*
 *  Copyright (c) Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2009-2011. All Rights Reserved.
 *
 *
 *  This file is part of OBBLM.
 *
 *  OBBLM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  OBBLM is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

class Award implements ModuleInterface
{

/***************
 * Properties
 ***************/

// MySQL stored information
public $award_id = 0;
public $team_id  = 0;
public $player_id  = 0;
public $tour_id  = 0;
public $type     = 0; // Is equal to a PRIZE_* constant.
public $date     = '';
public $title    = '';
public $txt      = '';

/***************
 * Methods
 ***************/

function __construct($award_id)
{
    $result = mysql_query("SELECT * FROM awards WHERE award_id = $award_id");
    if ($result && mysql_num_rows($result) > 0) {
        while ($row = mysql_fetch_assoc($result)) {
            foreach ($row as $key => $val) {
                $this->$key = $val;
            }
        }
    }

    return true;
}

public function delete()
{
    return (mysql_query("DELETE FROM awards WHERE award_id = $this->award_id"));
}

public function edit($type, $tid, $pid, $trid, $title, $txt)
{
    if (mysql_query("UPDATE awards SET
                    title = '".mysql_real_escape_string($title)."',
                    txt = '".mysql_real_escape_string($txt)."',
                    team_id = $tid,
                    player_id = $pid,
                    tour_id = $trid,
                    type = $type
                    WHERE award_id = $this->award_id")) {
        $this->txt   = $txt;
        $this->title = $title;
        $this->team_id  = $tid;
        $this->player_id  = $pid;
        $this->tour_id = $trid;
        $this->type  = $type;
        return true;
    }
    else
        return false;
}

/***************
 * Statics
 ***************/

public static function getTypes()
{
    return array(
        PRIZE_MVP => 'MVP',
        PRIZE_ALLSTAR => 'All-Star',
        PRIZE_SCORER => 'Top Scorer',
        PRIZE_VIOLENT => 'Most Violent',
        PRIZE_ROOKIE => 'Rookie of the Year',
        PRIZE_BOUNTY => 'Bounty Hunter'
    );
}

public static function getAwards($type, $id, $N = false)
{
    $awards = array();
    if (!$type && !$id) { # Special case
        $type = 'ALL';
    }
    $_IS_OBJ = in_array($type, array(T_OBJ_COACH, T_OBJ_TEAM, T_OBJ_PLAYER));
    $_LIMIT = ($N && is_numeric($N)) ? " LIMIT $N" : '';
    $_ORDER_BY__NODE = ' ORDER BY tours.date_created DESC, awards.type ASC';
    $_ORDER_BY__OBJ = ' ORDER BY awards.date DESC, awards.type ASC';
    switch ($type) {
        case T_NODE_LEAGUE:
            if (!isset($_FROMWHERE)) {
                $_FROMWHERE = "FROM awards,tours,divisions WHERE awards.tour_id = tours.tour_id AND tours.f_did = divisions.did AND divisions.f_lid = $id";
            }
            # Fall through
        case T_NODE_DIVISION:
            if (!isset($_FROMWHERE)) {
                $_FROMWHERE = "FROM awards,tours WHERE awards.tour_id = tours.tour_id AND tours.f_did = $id";
            }
            # Fall through
        case T_NODE_TOURNAMENT:
            if (!isset($_FROMWHERE)) {
                $_FROMWHERE = "FROM awards,tours WHERE awards.tour_id = tours.tour_id AND tours.tour_id = $id";
            }
            # Fall through
        case 'ALL':
            if (!isset($_FROMWHERE)) {
                $_FROMWHERE = "FROM awards,tours WHERE awards.tour_id = tours.tour_id";
            }
            $query = "SELECT awards.award_id AS 'award_id', tours.tour_id AS 'tour_id' ".$_FROMWHERE.$_ORDER_BY__NODE.$_LIMIT;
            break;

        case T_OBJ_COACH:
            $query = "SELECT award_id FROM awards, teams WHERE awards.team_id = teams.team_id AND owned_by_coach_id = $id".$_ORDER_BY__OBJ.$_LIMIT;
            break;
        case T_OBJ_TEAM:
            $query = "SELECT award_id FROM awards WHERE team_id = $id".$_ORDER_BY__OBJ.$_LIMIT;
            break;
        case T_OBJ_PLAYER:
            $query = "SELECT award_id FROM awards WHERE player_id = $id".$_ORDER_BY__OBJ.$_LIMIT;
            break;

        default:
            return array();
    }

    $result = mysql_query($query);
    if ($result && mysql_num_rows($result) > 0) {
        while ($row = mysql_fetch_assoc($result)) {
            $award = new Award($row['award_id']);
            if ($_IS_OBJ) {
                $awards[] = $award;
            }
            else {
                $awards[$row['tour_id']][] = $award;
            }
        }
    }
    return $awards;
}

public static function getAwardsString($obj, $id)
{
    global $lng;
    // ONLY FOR T_OBJ_*
    $awards = self::getAwards($obj, $id);
    $str = array();
    $award_types = self::getTypes();
    foreach ($award_types as $idx => $type) {
        $cnt = count(array_filter($awards, create_function('$a', 'return ($a->type == '.$idx.');')));
        if ($cnt > 0)
            $str[] = $cnt.'x'.$award_types[$idx];
    }
    return empty($str) ? $lng->getTrn('common/none') : implode(', ', $str);
}

public static function create($type, $tid, $pid, $trid, $title, $txt)
{
    if (!in_array($type, array_keys(self::getTypes())))
        return false;

    // Create new.
    $query = "
            INSERT INTO awards
            (date, type, team_id, player_id, tour_id, title, txt)
            VALUES
            (NOW(), $type, $tid, $pid, $trid, '".mysql_real_escape_string($title)."', '".mysql_real_escape_string($txt)."')
            ";
    $result = mysql_query($query);
    $query = "SELECT MAX(award_id) AS 'award_id' FROM awards;";
    $result = mysql_query($query);
    $row = mysql_fetch_assoc($result);

    return true;
}

/***************
 * Interface
 ***************/

public static function getModuleAttributes()
{
    return array(
        'author'     => 'Cody Clerke',
        'moduleName' => 'Awards',
        'date'       => '2017',
        'setCanvas'  => false,
    );
}

public static function getModuleTables()
{
    return array(
        # Table 1 name => column definitions
        'awards' => array(
            # Column name => definition
            'award_id' => 'MEDIUMINT UNSIGNED  NOT NULL PRIMARY KEY AUTO_INCREMENT',
            'team_id'  => 'MEDIUMINT UNSIGNED  NOT NULL DEFAULT 0',
            'player_id'  => 'MEDIUMINT UNSIGNED  NOT NULL DEFAULT 0',
            'tour_id'  => 'MEDIUMINT UNSIGNED  NOT NULL DEFAULT 0',
            'type'     => 'TINYINT UNSIGNED    NOT NULL DEFAULT 0',
            'date'     => 'DATETIME',
            'title'    => 'VARCHAR(100)',
            'txt'      => 'TEXT',
        ),
    );
}

public static function getModuleUpgradeSQL()
{
    return array();
}

public static function triggerHandler($type, $argv){}

public static function main($argv)
{
    /*
        First argument is func name in old Award class, the rest are arguments for that func.
    */
    $func = array_shift($argv);
    return call_user_func_array(array(__CLASS__, $func), $argv);
}

/***************
 * main() related.
 ***************/

// Main awards page.
public static function makeList()
{

    global $lng, $coach, $settings;
    HTMLOUT::frame_begin(); # Make page frame, banner and menu.

    title($lng->getTrn('name', __CLASS__));
    echo $lng->getTrn('desc', __CLASS__)."<br><br>\n";
    list($sel_node, $sel_node_id) = HTMLOUT::nodeSelector(array());

    $ALLOW_EDIT = (is_object($coach) && $coach->isNodeCommish($sel_node, $sel_node_id));

    /* A new entry was sent. Add it to system */
    if ($ALLOW_EDIT && isset($_POST['pid']) && isset($_POST['tid']) && isset($_POST['trid'])) {
        if (get_magic_quotes_gpc()) {
            $_POST['title'] = stripslashes($_POST['title']);
            $_POST['txt'] = stripslashes($_POST['txt']);
        }
        switch ($_GET['action'])
        {
            case 'new':
                status(self::create($_POST['atype'], $_POST['tid'], $_POST['pid'], $_POST['trid'], $_POST['title'], $_POST['txt']));
                break;
        }
    }

    /* Was a request for a new entry made? */
    if (isset($_GET['action']) && $ALLOW_EDIT) {
        switch ($_GET['action'])
        {
            case 'delete':
                if (isset($_GET['award_id']) && is_numeric($_GET['award_id'])) {
                    $award = new Award($_GET['award_id']);
                    status($award->delete());
                    unset($award);
                }
                else {
                    fatal('Sorry. You did not specify which award ID you wish to delete.');
                }
                break;

            case 'new':
                echo "<a href='handler.php?type=award'><-- ".$lng->getTrn('common/back')."</a><br><br>";
                $_DISABLED_TEAMS = !isset($_POST['trid']) ? 'DISABLED' : '';
                $_DISABLED_FORM = !isset($_POST['tid']) ? 'DISABLED' : '';
                ?>
                <form name="STS" method="POST" enctype="multipart/form-data">
                <b><?php echo $lng->getTrn('common/tournament');?></b><br>
                <?php
                echo HTMLOUT::nodeList(T_NODE_TOURNAMENT, 'trid', array(), array(), array('sel_id' => $_POST['trid']));
                ?>
                <input type='submit' value='<?php echo $lng->getTrn('common/select');?>'>
                </form>
                <br>
                <form method="POST" enctype="multipart/form-data">
                <b><?php echo $lng->getTrn('team', __CLASS__);?></b><br>
                <select name="tid" <?php echo $_DISABLED_TEAMS;?>>
                    <?php
                    $teams = isset($_POST['trid']) ? Team::getTeams(false,array(get_parent_id(T_NODE_TOURNAMENT, (int) $_POST['trid'], T_NODE_LEAGUE)),true) : array();
                    foreach ($teams as $tid => $name) {
                        echo "<option value='$tid' ". ($_POST['tid'] == $tid ? 'SELECTED' : '').">$name</option>\n";
                    }
                    ?>
                </select>
                <input type='hidden' name='trid' value='<?php echo $_DISABLED_TEAMS ? 0 : $_POST['trid'];?>'>
                <input type='submit' value='<?php echo $lng->getTrn('common/select');?>'>
                </form>
                <br>
                <form method="POST" enctype="multipart/form-data">
                <b><?php echo $lng->getTrn('player', __CLASS__);?></b><br>
                <select name="pid" <?php echo $_DISABLED_FORM;?>>
                    <?php
                    $players = isset($_POST['tid']) ? Team::getTeamPlayers($_POST['tid']) : array();
                    foreach ($players as $pid => $name) {
                        echo "<option value='$pid'>$name</option>\n";
                    }
                    ?>
                </select>
                <br><br>
                <b><?php echo $lng->getTrn('kind', __CLASS__);?></b><br>
                <select name="atype" <?php echo $_DISABLED_FORM;?>>
                    <?php
                    foreach (self::getTypes() as $atype => $desc) {
                        echo "<option value='$atype'>$desc</option>\n";
                    }
                    ?>
                </select>
                <br><br>
                <?php echo '<b>'.$lng->getTrn('g_title', __CLASS__).'</b> &mdash; '.$lng->getTrn('title', __CLASS__);?><br>
                <input type="text" name="title" size="60" maxlength="100" value="" <?php echo $_DISABLED_FORM;?>>
                <br><br>
                <?php echo '<b>'.$lng->getTrn('g_about', __CLASS__).'</b> &mdash; '.$lng->getTrn('about', __CLASS__);?><br>
                <textarea name="txt" rows="15" cols="100" <?php echo $_DISABLED_FORM;?>></textarea>
                <br><br><br>
                <input type='hidden' name='trid' value='<?php echo $_DISABLED_TEAMS ? 0 : $_POST['trid'];?>'>
                <input type='hidden' name='tid' value='<?php echo $_DISABLED_FORM ? 0 : $_POST['tid'];?>'>
                <input type="submit" value="<?php echo $lng->getTrn('submit', __CLASS__);?>" name="Submit" <?php echo $_DISABLED_FORM;?>>
                </form>
                <br>
                <?php

                return;
                break;

        }
    }

    if ($ALLOW_EDIT) {
        echo "<br><a href='handler.php?type=award&amp;action=new'>".$lng->getTrn('new', __CLASS__)."</a><br>\n";
    }

    /* Print the awards */
    self::printList($sel_node, $sel_node_id, $ALLOW_EDIT);
    HTMLOUT::frame_end();
}

// Prints awards list for a given tour_id or all tours.
public static function printList($node, $node_id, $ALLOW_EDIT)
{
    global $lng;
    $awards = self::getAwards($node, $node_id);
    $FOLD_UP = false; # (count($awards) > 20);
    foreach ($awards as $trid => $tourawards) {
        $tname = get_alt_col('tours', 'tour_id', $trid, 'name');
        ?>
        <div class="boxWide" style="width: 70%; margin: 20px auto 20px auto;">
            <div class="boxTitle<?php echo T_HTMLBOX_INFO;?>"><?php echo "$tname awards";?> <a href='javascript:void(0);' onClick="slideToggleFast('<?php echo 'trpr'.$trid;?>');">[+/-]</a></div>
            <div id="trpr<?php echo $trid;?>">
            <div class="boxBody">
                <table class="common" style='border-spacing: 10px;'>
                    <tr>
                        <td style='width:25%;'><b>Award&nbsp;type</b></td>
                        <td><b>Team</b></td>
                        <td><b>Player</b></td>
                        <td><b>About</b></td>
                    </tr>
                    <?php
                    $atypes = self::getTypes();
                    foreach ($tourawards as $award) {
                        echo "<tr><td colspan='4'><hr></td></td>";
                        echo "<tr>\n";
                        $delete = ($ALLOW_EDIT) ? '&nbsp;<a href="handler.php?type=award&amp;action=delete&amp;award_id='.$award->award_id.'">'.$lng->getTrn('common/delete').'</a>' : '';
                        echo "<td valign='top'>".preg_replace('/\s/', '&nbsp;', $atypes[$award->type])."&nbsp;$delete</td>\n";
                        echo "<td valign='top'><b>".preg_replace('/\s/', '&nbsp;', get_alt_col('teams', 'team_id', $award->team_id, 'name'))."</b></td>\n";
                        echo "<td valign='top'><b>".preg_replace('/\s/', '&nbsp;', get_alt_col('players', 'player_id', $award->player_id, 'name'))."</b></td>\n";
                        echo "<td valign='top'>".$award->title."<br><br><i>".$award->txt."</i></td>\n";
                        echo "</tr>\n";
                    }
                    ?>
                </table>
            </div>
            </div>
        </div>
        <?php
        if ($FOLD_UP) {
            ?>
            <script language="JavaScript" type="text/javascript">
                document.getElementById('trpr<?php echo $t->tour_id;?>').style.display = 'none';
            </script>
            <?php
        }
    }
}
}

?>
