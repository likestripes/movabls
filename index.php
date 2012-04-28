<?php
require('config.inc.php');
$con = mysql_connect($db_server,$db_user,$db_password);
if (!$con)
  {
  die('Could not connect: ' . mysql_error());
  }

mysql_select_db($db_name, $con);

$result = mysql_query("SELECT * FROM mvs_functions");

while($row = mysql_fetch_array($result))
  {
print_r($row);
  }

mysql_close($con);


?>
/*
 * Movabls by LikeStripes LLC
*/
