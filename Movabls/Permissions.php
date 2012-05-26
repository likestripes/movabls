<?php
/**
 * Movabls Permissions API
 * @author Travis Hardman
 */
class Movabls_Permissions {

    /**
     * Checks whether the current user has permission to access the given movabl with the given permission type
     * @param string $movabl_type
     * @param string $movabl_guid
     * @param array $user
     * @param string $permission_type
     * @param mysqli handle $mvs_db
     */
   public static function check_permission($place_GUID,$access="1") {
        if (isset($GLOBALS->_USER["user_GUID"])):
            $group_str = implode(",", $GLOBALS->_USER["groups"]);
            $result = Movabls_Data::data_query("SELECT access FROM mvs_permissions WHERE (user_GUID='{$GLOBALS->_USER["user_GUID"]}' OR group_id IN ($group_str)) AND (place_GUID='$place_GUID' OR url='{$GLOBALS->_SERVER["REQUEST_URI"]}') ORDER BY access DESC LIMIT 1;", DATA_ARRAY);
        else:
            $result = Movabls_Data::data_query("SELECT access FROM mvs_permissions WHERE group_id=11 AND (place_GUID='$place_GUID' OR url='{$GLOBALS->_SERVER["REQUEST_URI"]}') ORDER BY access DESC LIMIT 1;", DATA_ARRAY);
        endif;
    return ($result["access"]===$access) ? TRUE : FALSE;
    }


    public static function set_permission($user_GUID=NULL, $group_id=NULL, $place_GUID=NULL, $url=NULL,$access="1") {
        $result = Movabls_Data::data_query("REPLACE INTO mvs_permissions (user_GUID, group_id, place_GUID, url, access) VALUES ('$user_GUID',  '', '$place_GUID', '',  '$access' ); ");
    }

        

}