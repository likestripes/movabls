<?php

define('DATA_RESULT', 0);
define('DATA_OBJECT', 1);
define('DATA_ARRAY',2);

/**
 * Data API
 * @author Travis Donia
 */
class Movabls_Data {
/* function __construct() {

	include ('config.inc.php');
        $mvsdb = new mysqli($db_server,$db_user,$db_password,$db_name, $db_port);
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
            exit();
        }
        $this->db_link = $mvsdb;
  

}
*/
    /**
     * Gets a single movabl by type and GUID
     * @param string $movabl_type
     * @param array
     */
    public static function data_query($query, $return_type=DATA_RESULT, $cacheable=TRUE) {
	global $mvsdb;
 

	if (strtoupper(substr($query, 0, 6)) != "SELECT")  $cacheable = FALSE;
try{

	if(!$cacheable || $return_type === DATA_RESULT ):
	//	echo "no cache";
	       if (!is_object($mvsdb))  $mvsdb = self::db_link();
    	   	$result = $mvsdb->query($query);
          return  $result;
	else:
	//	echo "cache";
  $query_md5 = md5($query);
  $cache_return = self::memcache_get($query_md5);
  if (!isset($cache_return) || empty($cache_return)):
		if (!is_object($mvsdb)) $mvsdb = self::db_link();
		$result = $mvsdb->query($query);

if ($return_type === DATA_OBJECT)
		$return_value =  $result->fetch_object();
	elseif ($return_type === DATA_ARRAY)
		$return_value = $result->fetch_array();

    self::memcache_set($query_md5, $return_value);
  
    return $return_value;

  endif;
	endif;
	
	

    } catch(Exception $e){
    
    print_r($e);
    }
    
    }
    
public static function memcache_get($key) {
global $mvsmemcache;
return $mvsmemcache->get($key);
}
    
public static function memcache_set($key, $value) {
global $mvsmemcache;
$mvsmemcache->set($key, $value);
return $mvsmemcache->get($key);
}

public static function memcache_link($server, $bin) {
global $mvsmemcache;
    if (!is_object($mvsmemcache)):
    $mvsmemcache = new Memcached();
    $mvsmemcache->addServer($server,$bin);
  endif;
return $mvsmemcache;
}

    /**
     * Gets the handle to access the database
     * @return mysqli handle 
     */
    public static function db_link() {
    global $mvsdb;
    if (!is_object($mvsdb)):
	include ('config.inc.php');
        $mvsdb = new mysqli($db_server,$db_user,$db_password,$db_name, $db_port);
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
            exit();
        }

    
    endif;
        return $mvsdb;
        }
}
