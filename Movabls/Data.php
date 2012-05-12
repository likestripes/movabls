<?php
/**
 * Data API
 * @author Travis Donia
 */
class Data {

    /**
     * Gets a single movabl by type and GUID
     * @param string $movabl_type
     * @param array
     */
    public static function generic_example() {
        $mvsdb = self::db_link();
        $result = $mvsdb->query("SELECT x.* FROM `mvs_$table` AS x WHERE x.{$movabl_type}_GUID = '$movabl_guid'");
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
