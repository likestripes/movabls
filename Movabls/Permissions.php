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
            $result = Movabls_Data::data_query("SELECT access FROM mvs_permissions WHERE (user_GUID='{$GLOBALS->_USER["user_GUID"]}' OR group_id IN ($group_str)) AND (place_GUID='$place_GUID' OR url='{$GLOBALS->_SERVER["REQUEST_URI"]}');", DATA_ARRAY);
            return ($result["access"]===$access) ? TRUE : FALSE;
        else:
            $result = Movabls_Data::data_query("SELECT access FROM mvs_permissions WHERE group_id=11 AND (place_GUID='$place_GUID' OR url='{$GLOBALS->_SERVER["REQUEST_URI"]}');", DATA_ARRAY);
            return ($result["access"]===$access) ? TRUE : FALSE;
        endif;
    }

    public static function set_permission($user_or_group_id,$object,$access="1") {
        $result = Movabls_Data::data_query("REPLACE INTO  mvs_permissions (user_or_group_id, object, access) VALUES ('$user_or_group_id',  '$object',  '$access' ); ");
    }

        

}
