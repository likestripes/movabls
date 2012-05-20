<?php
/*
 * Movabls by LikeStripes LLC
*/
//print_r($_POST);
//print_r($_SERVER);
//phpinfo();
error_reporting(E_ALL);
ini_set('display_errors', '1');
//setcookie('httpsession', '4347dc968bfae2292bce89ed856f7d965c64d8424f9f57bc9b3267.74335679');
function __autoload($name) {

    if ($name == "Movabls")
        $name = "Movabls_Movabls";
    $fname = str_replace('_','/',$name);
    if (file_exists($fname.'.php'))
        require_once($fname.'.php');
    else
        echo $name."class not found";//throw new Exception ("Class $name not found",500);
    
}

//Override all superglobals with read-only variants
try{

	global $mvs_db, $mvs_memcache;
    
    $db_server="mvs-lco-db.cp9ioybwsihy.us-east-1.rds.amazonaws.com";
    $db_name="mvs_lco";
    $db_user="mvs_lco_db";
    $db_password="braceletramen";
    $db_port = 3306;
  
	$mvs_db = Movabls_Data:: db_link($db_server,$db_user,$db_password,$db_name, $db_port);	
	$memcache_link_ar = Array(
        Array('server'=>'localhost', 'bin'=>11211),	
        Array('server'=>'mvscache.veiptl.0001.use1.cache.amazonaws.com', 'bin'=>11211)
    );	
	
    $mvs_memcache = Movabls_Data::memcache_link($memcache_link_ar);	


	Movabls_Session::get_session();
	$GLOBALS = new Movabls_Globals();

	unset($_SERVER,$_GET,$_POST,$_FILES,$_COOKIE,$_SESSION,$_REQUEST,$_ENV,$_USER);

	new Movabls_Run;
}catch (Exception $e){
echo "main one";
print_r($e);
}

//print_r($GLOBALS);
/*
//TODO: Delete this once you have a way to log in via the IDE
if (!isset($GLOBALS->_USER['session_id'])) {
	
	if (!isset($GLOBALS->_POST['email']) || !isset($GLOBALS->_POST['password'])) {
   
   ?>
   <form method="POST">
	<input type="text" name="email" /> <br/>
	<input type="password" name="password" />
	<input type="submit" value="Sign In!" />
</form>
   <?
   
   }else{
   
   Movabls_Users::login('email',$GLOBALS->_POST['email'],$GLOBALS->_POST['password']);
   header('Location: http://'.$GLOBALS->_SERVER['HTTP_HOST'].$GLOBALS->_SERVER['REQUEST_URI']);
   die();
   }
   
   }
*/
//Run it!
//new Movabls_Run;

/*
$iterations = 1000;
ob_start();
$times = array();
$squares = array();
for ($i=1;$i<=$iterations;$i++) {
	$start = microtime(true);
	new Movabls_Run;
	$time = microtime(true) - $start;
	$times[] = $time;
	$squares[] = $time*$time;
}
ob_end_clean();
$variance = (array_sum($squares) - array_sum($times)*array_sum($times)/count($times)) / count($times);
echo "<br /><br />\n\n";
echo "mean run: ".(array_sum($times)/count($times))."<br />\n";
echo "max run: ".max($times)."<br />\n";
echo "std dev: ".sqrt($variance)."<br />\n";
// */
