<?php
/*
 * Movabls by LikeStripes LLC
*/
//print_r($_POST);
//print_r($_SERVER);
//phpinfo();
error_reporting(E_ALL);
ini_set('display_errors', '1');
function __autoload($name) {

    if ($name == "Movabls")
        $name = "Movabls_Movabls";
    $fname = str_replace('_','/',$name);
    if (file_exists($fname.'.php'))
        require_once($fname.'.php');
    else
        throw new Exception ("Class $name not found",500);
    
}

//Override all superglobals with read-only variants
Movabls_Session::get_session();
$GLOBALS = new Movabls_Globals();
unset($_SERVER,$_GET,$_POST,$_FILES,$_COOKIE,$_SESSION,$_REQUEST,$_ENV,$_USER);

//TODO: Delete this once you have a way to log in via the IDE
if (!$GLOBALS->_USER['session_id']) {
	
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

//Run it!
new Movabls_Run;

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