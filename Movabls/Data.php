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
	
 

	if (strtoupper(substr($query, 0, 6)) != "SELECT")  $cacheable = FALSE;
try{

	if(!$cacheable):
	//	echo "no cache";
	        $mvsdb = self::db_link();
    	   	$result = $mvsdb->query($query);
	else:
	//	echo "cache";
		$mvsdb = self::db_link();
		$result = $mvsdb->query($query);
	endif;
	
	if ($return_type === DATA_RESULT)
		return  $result;
	elseif ($return_type === DATA_OBJECT)
		return  $result->fetch_object();
	elseif ($return_type === DATA_ARRAY)
		return $result->fetch_array();

    } catch(Exception $e){
    
    print_r($e);
    }
    
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
