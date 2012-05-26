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


    public static function set_place_permission($user_GUID=NULL, $group_id=NULL, $place_GUID=NULL, $access="1") {
        if (!in_array(1,$GLOBALS->_USER['groups'])) return;
        
        if(isset($user_GUID) && !empty($user_GUID) && $user_GUID!= NULL) Movabls_Data::data_query("REPLACE INTO mvs_permissions (user_GUID, place_GUID, access) VALUES ('$user_GUID', '$place_GUID', '$access' ); ");
        if(isset($group_id) && !empty($group_id) && $group_id!= NULL) Movabls_Data::data_query("REPLACE INTO mvs_permissions (group_id, place_GUID, access) VALUES ('$group_id', '$place_GUID',  '$access' ); ");
    }
    
    public static function set_url_permission($user_GUID=NULL, $group_id=NULL, $place_GUID=NULL, $url=NULL,$access="1") {
        if(isset($user_GUID) && !empty($user_GUID) && $user_GUID!= NULL)  Movabls_Data::data_query("REPLACE INTO mvs_permissions (user_GUID, url, access) VALUES ('$user_GUID', '$url',  '$access' ); ");
        if(isset($group_id) && !empty($group_id) && $group_id!= NULL)  Movabls_Data::data_query("REPLACE INTO mvs_permissions (group_id, url, access) VALUES ('$group_id', '$url',  '$access' ); ");
    }

        

}