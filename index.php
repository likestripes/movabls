<?php
/*
 * Movabls by LikeStripes LLC
*/

function __autoload($name) {

    if ($name == "Movabls")
        $name = "Movabls_Movabls";
    $fname = str_replace('_','/',$name);
    if (file_exists($fname.'.php'))
        require_once($fname.'.php');
    else
        echo $name."class not found";//throw new Exception ("Class $name not found",500);
    
}

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
