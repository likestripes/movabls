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
     * @param mysqli handle $mvsdb
     */
    public static function check_permission($movabl_type,$movabl_guid,$permission_type,$mvsdb = null) {

        if ($GLOBALS->_USER['is_owner'])
            return true;

        if (empty($mvsdb))
            $mvsdb = Movabls_Permissions::db_link();

        $movabl_type = $mvsdb->real_escape_string($movabl_type);
        $movabl_guid = $mvsdb->real_escape_string($movabl_guid);
        foreach ($GLOBALS->_USER['groups'] as $k => $group)
            $groups[$k] = $mvsdb->real_escape_string($group);
        $groups = "'".implode("','",$groups)."'";
        $permission_type = $mvsdb->real_escape_string($permission_type);

        $results = $mvsdb->query("SELECT permission_id FROM mvs_permissions
                                WHERE movabl_type = '$movabl_type'
                                AND movabl_guid = '$movabl_guid'
                                AND permission_type = '$permission_type'
                                AND group_guid IN ($groups)");

        if ($results->num_rows == 0)
            return false;
        else
            return true;

    }

    /**
     * Takes information on new permissions, constructs an array of new permissions,
     * diffs that array with the existing permissions in the database, and makes the
     * necessary changes to the db
     * @param string $movabl_type (or 'site')
     * @param string $movabl_guid
     * @param array $groups = array('guid'=>'fooguid','r'=>bool,'w'=>bool,'x'=>bool)
     * @param string $inheritance_type
     * @param string $inheritance_GUID
     * @param mysqli handle $mvsdb
     * @return true
     */
    public static function set_permission($movabl_type,$movabl_guid,$groups,$inheritance_type = null,$inheritance_GUID = null,$mvsdb = null) {

        if (empty($mvsdb))
            $mvsdb = Movabls_Permissions::db_link();
        if (!Movabls_Permissions::permissions_editor($GLOBALS->_USER,$mvsdb))
            throw new Exception('You do not have permission to edit permissions.');

        $escaped_data = Movabls_Permissions::escape_data($movabl_type,$movabl_guid,$groups,$inheritance_type,$inheritance_GUID,$mvsdb);

        //Set the permissions of all the children of this movabl
        if (empty($inheritance_type))
            Movabls_Permissions::set_children($escaped_data,true,$mvsdb);
        else
            Movabls_Permissions::set_children($escaped_data,false,$mvsdb);
        
        //Prepare the new data array to set this movabl
        foreach ($escaped_data['groups'] as $group) {
            $template = array(
                'group_GUID' => $group['guid'],
                'movabl_type' => $escaped_data['movabl_type'],
                'movabl_GUID' => $escaped_data['movabl_GUID'],
                'permission_type' => null,
                'inheritance_type' => $escaped_data['inheritance_type'],
                'inheritance_GUID' => $escaped_data['inheritance_GUID']
            );
            if ($group['r']) {
                $template['permission_type'] = 'read';
                $new_data[] = $template;
            }
            if ($group['w']) {
                $template['permission_type'] = 'write';
                $new_data[] = $template;
            }
            if ($group['x']) {
                $template['permission_type'] = 'execute';
                $new_data[] = $template;
            }
            $groupstring[] = $group['guid'];
        }
        $groupstring = "'".implode("','",$groupstring)."'";

        //Get the relevant existing permissions and put them into the old data array
        $results = $mvsdb->query("SELECT * FROM mvs_permissions
                                WHERE movabl_type = '{$escaped_data['movabl_type']}'
                                AND movabl_GUID = '{$escaped_data['movabl_GUID']}'
                                AND group_GUID IN ($groupstring)
                                AND inheritance_type ".(empty($escaped_data['inheritance_type']) ? 'IS NULL' : "= '{$escaped_data['inheritance_type']}'")."
                                AND inheritance_GUID ".(empty($escaped_data['inheritance_GUID']) ? 'IS NULL' : "= '{$escaped_data['inheritance_GUID']}'"));

        $old_data_index = array();
        while ($row = $results->fetch_assoc()) {
            $row['permission_id'] = (int)$row['permission_id'];
            $row['inheritance_type'] = empty($row['inheritance_type']) ? null : $row['inheritance_type'];
            $row['inheritance_GUID'] = empty($row['inheritance_GUID']) ? null : $row['inheritance_GUID'];
            $old_data[] = $row;
            $old_data_index[] = array(
                'group_GUID' => $row['group_GUID'],
                'movabl_type' => $escaped_data['movabl_type'],
                'movabl_GUID' => $row['movabl_GUID'],
                'permission_type' => $row['permission_type'],
                'inheritance_type' => $row['inheritance_type'],
                'inheritance_GUID' => $row['inheritance_GUID']
            );
        }
        $results->free();

        //Add new data if the rows don't already exist
        foreach ($new_data as $data) {
            $key = array_search($data,$old_data_index);
            if ($key === false) {
                if ($data['inheritance_type'] !== null) {
                    $data['inheritance_type'] = "'".$data['inheritance_type']."'";
                    $data['inheritance_GUID'] = "'".$data['inheritance_GUID']."'";
                }
                else {
                    $data['inheritance_type'] = "NULL";
                    $data['inheritance_GUID'] = "NULL";
                }
                $mvsdb->query("INSERT INTO mvs_permissions
                               (group_GUID,movabl_type,movabl_GUID,permission_type,inheritance_type,inheritance_GUID)
                               VALUES ('{$data['group_GUID']}','{$data['movabl_type']}','{$data['movabl_GUID']}','{$data['permission_type']}',{$data['inheritance_type']},{$data['inheritance_GUID']})");
            }
            else
                unset($old_data[$key],$old_data_index[$key]);            
        }

        //old_data that were not included in new_data should be removed
        if (!empty($old_data)) {
            foreach ($old_data as $data)
                $mvsdb->query("DELETE FROM mvs_permissions WHERE permission_id = {$data['permission_id']}");
        }
    }

    /**
     * Gets all the children of the movabl being set and sets them
     * @param array $escaped_data
     * @param bool $toplevel
     * @param mysqli handle $mvsdb
     */
    private static function set_children($escaped_data,$toplevel,$mvsdb = null) {
        
        if (empty($mvsdb))
            $mvsdb = Movabls_Permissions::db_link();

        switch ($escaped_data['movabl_type']) {
            case 'site':
                $results = $mvsdb->query("SELECT media_GUID FROM mvs_media");
                while ($row = $results->fetch_assoc())
                    $extras[] = array('movabl_type'=>'media','movabl_GUID'=>$row['media_GUID']);
                $results->free();
                $results = $mvsdb->query("SELECT function_GUID FROM mvs_functions");
                while ($row = $results->fetch_assoc())
                    $extras[] = array('movabl_type'=>'function','movabl_GUID'=>$row['function_GUID']);
                $results->free();
                $results = $mvsdb->query("SELECT interface_GUID FROM mvs_interfaces");
                while ($row = $results->fetch_assoc())
                    $extras[] = array('movabl_type'=>'interface','movabl_GUID'=>$row['interface_GUID']);
                $results->free();
                $results = $mvsdb->query("SELECT place_GUID FROM mvs_places");
                while ($row = $results->fetch_assoc())
                    $extras[] = array('movabl_type'=>'place','movabl_GUID'=>$row['place_GUID']);
                $results->free();
                $results = $mvsdb->query("SELECT package_GUID FROM mvs_packages");
                while ($row = $results->fetch_assoc())
                    $extras[] = array('movabl_type'=>'packages','movabl_GUID'=>$row['package_GUID']);
                $results->free();
                break;
            case 'place':
                $results = $mvsdb->query("SELECT media_GUID,interface_GUID FROM mvs_places WHERE place_GUID = '{$escaped_data['movabl_GUID']}'");
                $row = $results->fetch_assoc();
                $extras = array(
                    array('movabl_type'=>'media','movabl_GUID'=>$row['media_GUID']),
                    array('movabl_type'=>'interface','movabl_GUID'=>$row['interface_GUID'])
                );
                $results->free();
                break;
            case 'interface':
                $results = $mvsdb->query("SELECT content FROM mvs_interfaces WHERE interface_GUID = '{$escaped_data['movabl_GUID']}'");
                $row = $results->fetch_assoc();
                $tags = json_decode($row['content'],true);
                $extras = Movabls_Permissions::get_tags($tags);
                $results->free();
                break;
            case 'package':
                $results = $mvsdb->query("SELECT contents FROM mvs_packages WHERE package_GUID = '{$escaped_data['movabl_GUID']}'");
                $row = $results->fetch_assoc();
                $extras = json_decode($row['contents'],true);
                $results->free();
                break;
        }
        if (!empty($extras)) {
            if ($toplevel) {
                foreach ($extras as $extra)
                    Movabls_Permissions::set_permission($extra['movabl_type'], $extra['movabl_GUID'], $escaped_data['groups'], $escaped_data['movabl_type'], $escaped_data['movabl_GUID'], $mvsdb);
            }
            else {
                foreach ($extras as $extra)
                    Movabls_Permissions::set_permission($extra['movabl_type'], $extra['movabl_GUID'], $escaped_data['groups'], $escaped_data['inheritance_type'], $escaped_data['inheritance_GUID'], $mvsdb);
            }
        }
        
    }

    /**
     * Extracts sub-movabls from an interface
     * @param tags $tags
     * @param extras array so far $extras
     * @return extras array after this round
     */
    private static function get_tags($tags,$extras = array()) {

        if (!empty($tags)) {
            foreach ($tags as $value) {
                if (isset($value['movabl_type']))
                    $extras[] = array('movabl_type'=>$value['movabl_type'],'movabl_GUID'=>$value['movabl_GUID']);
                if (isset($value['tags']))
                    $extras = Movabls_Permissions::get_tags($value['tags'],$extras);
                elseif (isset($value['interface_GUID']))
                    $extras[] = array('movabl_type'=>'interface','movabl_GUID'=>$value['interface_GUID']);
            }
        }

        return $extras;

    }

    //TODO: If you create a movabl, any site permission has to be added for that movabl
    //If you add a movabl to a package, place or interface, the permissions have to be added with the
    //correct inheritances.  Also, if you remove an item from a package, place, or int, the permission
    //inheritance has to be removed, and any inheritances that passed through that parent also have to
    //be removed.

    /**
     * Escapes data passed to a set function for use in a SQL query
     * @param string $movabl_type
     * @param string $movabl_guid
     * @param array $groups
     * @param string $inheritance_type
     * @param string $inheritance_GUID
     * @param mysqli handle $mvsdb
     * @return array 
     */
    private static function escape_data($movabl_type,$movabl_guid,$groups,$inheritance_type,$inheritance_GUID,$mvsdb = null) {

        if (empty($mvsdb))
            $mvsdb = Movabls_Permissions::db_link();

        $data['movabl_type'] = $mvsdb->real_escape_string($movabl_type);
        $data['movabl_GUID'] = $mvsdb->real_escape_string($movabl_guid);
        if (empty($inheritance_type)) {
            $data['inheritance_type'] = null;
            $data['inheritance_GUID'] = null;
        }
        else {
            $data['inheritance_type'] = $mvsdb->real_escape_string($inheritance_type);
            $data['inheritance_GUID'] = $mvsdb->real_escape_string($inheritance_GUID);
        }
        foreach ($groups as $k => $group) {
            $data['groups'][$k]['guid'] = $mvsdb->real_escape_string($group['guid']);
            $data['groups'][$k]['r'] = $group['r'] ? true : false;
            $data['groups'][$k]['w'] = $group['w'] ? true : false;
            $data['groups'][$k]['x'] = $group['x'] ? true : false;
        }

        return $data;

    }

    /**
     * Determines whether the current user has permission to edit permissions
     * @param array $user
     * @param mysqli handle $mvsdb
     * @return bool
     */
    public static function permissions_editor($mvsdb = null) {

        if ($GLOBALS->_USER['is_owner'])
            return true;

        if (empty($mvsdb))
            $mvsdb = Movabls_Permissions::db_link();
        
        $groups = "'".implode("','",$GLOBALS->_USER['groups'])."'";
        $results = $mvsdb->query("SELECT group_id FROM mvs_groups
                                    WHERE group_GUID IN ($groups)
                                    AND permissions_editor = 1");
        if ($results->num_rows == 0)
            return false;
        else
            return true;

    }

    /**
     * Gets the handle to access the database
     * @return mysqli handle
     */
    private static function db_link() {

        $mvsdb = new mysqli('localhost','root','h4ppyf4rmers','db_filet');
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
            exit();
        }
        return $mvsdb;

    }

}
?>
