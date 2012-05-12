<?php

define('DATA_OBJECT', 1);
define('DATA_ARRAY',2);

/**
 * Data API
 * @author Travis Donia
 */
class Movabls_Data {

    /**
     * Gets a single movabl by type and GUID
     * @param string $movabl_type
     * @param array
     */
    public static function data_query($query, $return_type, $cacheable=TRUE) {
	



	if (strtoupper(substr($query, 0, 6)) != "SELECT")  $cacheable = FALSE;


	if(!$cacheable):
		echo "no cache";
	        $mvsdb = self::db_link();
    	   	$result = $mvsdb->query($query);
	else:
		echo "cache";
		$mvsdb = self::db_link();
		$result = $mvsdb->query($query);
	endif;
	if ($return_type === DATA_OBJECT)
		return  $result->fetch_object();
	elseif ($return_type === DATA_ARRAY)
		return $result->fetch_array();

    }

    /**
     * Gets the handle to access the database
     * @return mysqli handle 
     */
    private static function db_link() {
	include ('config.inc.php');
        $mvsdb = new mysqli($db_server,$db_user,$db_password,$db_name, $db_port);
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
            exit();
        }
        return $mvsdb;
    }

}
