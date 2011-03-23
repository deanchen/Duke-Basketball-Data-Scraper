<?php
/**
 * Mysql
 */
define('DB_NAME', 'mens_basketball');
define('SCHEDULE_TABLE_NAME', 'schedule');
define('PLAYER_TABLE_NAME', 'player');
define('ACC_TABLE_NAME', 'acc');


$link = mysql_connect('server', 'db', 'pass');
if (!$link) {
    die('Could not connect: ' . mysql_error());
}

mysql_selectdb(DB_NAME);

if (!check_table(SCHEDULE_TABLE_NAME)) {
  create_table(SCHEDULE_TABLE_NAME);
} else if (!check_table(PLAYER_TABLE_NAME)) {
  create_table(PLAYER_TABLE_NAME);
} else if (!check_table(ACC_TABLE_NAME)) {
  create_table(ACC_TABLE_NAME);
}



/**
 * Define constants
 */
define('DUKE_URL', 'http://www.cbssports.com/collegebasketball/teams/%1/DUKE/');
define('IMAGE_URL', 'http://sports.cbsimg.net/images/collegebasketball/logos/90x90/matchup/');
define('ACC_URL', 'http://www.cbssports.com/collegebasketball/standings/conference/ACC');

/**
 * Simple HTML Dom
 */
require('shd.php');

/**
 * Schedule
 */
$schedule_url = str_replace('%1', 'schedule', DUKE_URL);
$schedule_html = file_get_html($schedule_url);

foreach($schedule_html->find('table.data tr[align=right]') as $row) {
  $column_cursor = 1;
  $query_params = array();
  foreach ($row->find('td') as $field) {
    $field_content = $field->first_child();
    
    switch ($column_cursor) {
      
      case 1: // date
        $url = explode('/', $field_content->href);
        $date = $url[4];
        
        $query_params["date"] = $date;
        //$date = trim($field->plaintext);
        break;
      case 2: // opponent
        $opponent = $field->plaintext;
        $away = FALSE;
        // process if away or home
        if ($opponent[0] == '@') {
          $opponent_name = substr($opponent, 1);
          $away = TRUE;
        } else {
          $opponent_name = $opponent;
        }
        
        
        //print $opponent_name;
        
        // extract opponent team string
        $url = explode('/', $field_content->href);
        $opponent_string = $url[4];
        
        $query_params["away"] = $away;
        $query_params["opponent"] = $opponent_name;
        $query_params["opponent_string"] = $opponent_string;
        //$opponent_img_src = IMAGE_URL . $opponent_string . ".png";
        //print "<img src='$opponent_img_src' />";
        break;
      
      case 3: // result/time
        $field_content = explode(' ', trim($field->plaintext));
 
        if ($field_content[1] == "PM" || $field_content[1] == "AM") {
          // time
          $time = $field->plaintext;
          
          $query_params["time"] = $time;
        } else {
          // result
          $result = $field_content[2];
          
          $query_params["result"] = $result;
        }
        break;
      case 4: // record
        $record = $field->plaintext;
        
        $query_params["record"] = $record;
        break;
    }
    $column_cursor++; 
  }
  replace_row($query_params, SCHEDULE_TABLE_NAME);
}

/**
 * Player stats
 */
 
$playerStats_url = str_replace('%1', 'stats', DUKE_URL);
$playerStats_url .= 'duke-blue-devils/regularseason/yearly/SCORING';
$playerStats_html = file_get_html($playerStats_url);

foreach($playerStats_html->find('table.data tr[align=right]') as $row) {
  $column_cursor = 1;
  $query_params = array();
  foreach ($row->find('td') as $field) {
    switch ($column_cursor) {
      case 1: //No
        $query_params['no'] = $field->plaintext;
        break; 
      case 2: //Player
        $query_params['player'] = $field->plaintext;
        break;
      case 3: //GP
        $query_params['gp'] = $field->plaintext;
        break;
      case 4: //PPG
        $query_params['ppg'] = $field->plaintext;
        break;
      case 5: //FGM
        $query_params['fgm'] = $field->plaintext;
        break;
      case 6: //FGA
        $query_params['fga'] = $field->plaintext;
        break;
      case 7: //FG%
        //$query_params['fgp'] = $field->plaintext;
        break;
      case 8: //FTM
        $query_params['ftm'] = $field->plaintext;
        break;
      case 9: //FTA
        $query_params['fta'] = $field->plaintext;
        break;
      case 10: //FT%
        //$query_params['ftp'] = $field->plaintext;
        break;
      case 11: //3PTM
        $query_params['3ptm'] = $field->plaintext;
        break;
      case 12: //3PTA
        $query_params['3pta'] = $field->plaintext;
        break;
      case 13: //3PT%
        //$query_params['3ptp'] = $field->plaintext;
        break;
    }
    $column_cursor++; 
  }
  replace_row($query_params, PLAYER_TABLE_NAME);
}

/**
 * ACC Stats
 */

$acc_html = file_get_html(ACC_URL);

foreach($acc_html->find('table.data tr[align=right]') as $row) {
  $column_cursor = 1;
  $query_params = array();
  foreach ($row->find('td') as $field) {
    switch ($column_cursor) {
      case 1: //Team
        $query_params['team'] = $field->plaintext;
        break;
      case 2: //Wins
        $query_params['wins'] = $field->plaintext;
        break;
      case 3: //Losses
        $query_params['losses'] = $field->plaintext;
        break;
    }
    $column_cursor++;  
  }  
  replace_row($query_params, ACC_TABLE_NAME);
}

mysql_close($link);

/**
 * Convinience function for compiling and executing SQL
 */
function replace_row($query_params, $table_name) {
  $columns = "";
  $values = "";
  // convert $query_params in to components of the sql query 
  foreach ($query_params as $key=>$value) {
    $columns .= "$key,";
    
    // do not put quotes around ints
    if ($value === '-') {
      $values .= "NULL,";
    } else {
      $values .= "'" . mysql_real_escape_string($value) . "',";
    }

  }
  
  // remove trailing comma
  $columns = substr($columns, 0, -1);
  $values = substr($values, 0 ,-1);
  
  $sql = "REPLACE INTO `mens_basketball`.`$table_name` ($columns) VALUES ($values)";
  mysql_query($sql) or die(mysql_error());
  
  print_r($query_params);
  print "<br />";
}

/**
 * Check if table exists
 */
function check_table($table_name) {
  $query = 'SELECT COUNT(*)
          FROM information_schema.tables 
          WHERE table_schema = "' . DB_NAME . '" 
          AND table_name = "' . $table_name . '"';
  $result = mysql_query($query);
  
  $row = mysql_fetch_row($result) or die (mysql_error());

  return $row[0];
}

/**
 * Create a given table, sql queries stored as string
 */
function create_table($table_name) {
  if ($table_name == SCHEDULE_TABLE_NAME) {
    $sql = "CREATE TABLE `mens_basketball`.`schedule` (`date` INT(8) NOT NULL, `away` BOOLEAN NOT NULL, `opponent` VARCHAR(63) NOT NULL, `opponent_string` VARCHAR(15) NOT NULL, `time` VARCHAR(15) NULL, `result` VARCHAR(15) NULL, `record` VARCHAR(15) NOT NULL, PRIMARY KEY (`date`, `opponent`)) ENGINE = MyISAM COMMENT = 'Stores schedule information pulled from CBS Sports';";
  } else if ($table_name == PLAYER_TABLE_NAME) {
    $sql = "CREATE TABLE `mens_basketball`.`player` (`no` INT(3) NOT NULL, `player` VARCHAR(63) NOT NULL, `gp` INT NULL, `ppg` FLOAT NULL, `fgm` INT NULL, `fga` INT NULL, `ftm` INT NULL, `fta` INT NULL, `3ptm` INT NULL, `3pta` INT NULL, PRIMARY KEY (`no`)) ENGINE = MyISAM COMMENT = 'Player stats from CBS sports';";
  } else if ($table_name == ACC_TABLE_NAME) {
    $sql = "CREATE TABLE `mens_basketball`.`acc` (`team` VARCHAR(63) NOT NULL, `wins` INT(3) NOT NULL, `losses` INT(3) NOT NULL, PRIMARY KEY (`team`)) ENGINE = MyISAM COMMENT = 'ACC Rankings';";
  }
  mysql_query($sql) or die(mysql_error());
  return TRUE;
}
