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
        $mvs_db = new mysqli($db_server,$db_user,$db_password,$db_name, $db_port);
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
            exit();
        }
        $this->db_link = $mvs_db;
  

}
*/
    /**
     * Gets a single movabl by type and GUID
     * @param string $movabl_type
     * @param array
     */
public static function data_query($query, $return_type=DATA_RESULT, $cacheable=TRUE) {
    global $mvs_db;
	if (strtoupper(substr($query, 0, 6)) != "SELECT")  $cacheable = FALSE;

    if(!$cacheable || $return_type === DATA_RESULT ):
       if (!is_object($mvs_db))  $mvs_db = self::db_link();
       $result = $mvs_db->query($query);
       return  $result;
    else:
      $query_md5 = md5($query);
      $cache_return = self::memcache_get($query_md5);
    //  print_r($cache_return);
        if (!isset($cache_return) || empty($cache_return)):
            if (!is_object($mvs_db)) $mvs_db = self::db_link();
                $result = $mvs_db->query($query);

            if ($return_type === DATA_OBJECT)
                $return_value =  $result->fetch_object();
            elseif ($return_type === DATA_ARRAY)
                $return_value = $result->fetch_array();

            self::memcache_set($query_md5, $return_value);

            return $return_value;
        endif;
    endif;
}
    
public static function memcache_get($key) {
    global $mvs_memcache;
    if (is_object($mvs_memcache)) return $mvs_memcache->get($key);
}
    
public static function memcache_set($key, $value) {
    global $mvs_memcache;
    if (is_object($mvs_memcache)):
        $mvs_memcache->set($key, $value, MEMCACHE_COMPRESSED);//, 10);
        return $mvs_memcache->get($key);
    endif;
}

public static function memcache_link($link_ar) {
    global $mvs_memcache;

   if (is_object($mvs_memcache)) return $mvs_memcache;
    $mvs_memcache = new Memcached();
   
    foreach($link_ar as $link):
        if ($mvs_memcache->addServer($link["server"],$link["bin"]) === TRUE):
            return $mvs_memcache;
        endif;
    endforeach;

    $mvs_memcache = FALSE;
    return FALSE;
}

    /**
     * Gets the handle to access the database
     * @return mysqli handle 
     */
    public static function db_link() {
    global $mvs_db;
    if (!is_object($mvs_db)):
	include ('config.inc.php');
        $mvs_db = new mysqli($db_server,$db_user,$db_password,$db_name, $db_port);
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
            exit();
        }

    
    endif;
        return $mvs_db;
        }
}
