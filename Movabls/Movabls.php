<?php
/**
 * Movabls API
 * @author Travis Hardman, Travis Donia
 */
class Movabls {

    public static function get_movabls($type="all") {

        if (is_array($type)) $type_str = "AND mvs_meta.movabls_type IN ('".implode("','",$type)."')";
        elseif ($type != "all") $type_str = "AND mvs_meta.movabls_type = '$type'";
        else $type_str="";
        
        $result = Movabls_Data::data_query("SELECT mvs_meta.movabls_type, mvs_meta.movabls_GUID, mvs_meta.value FROM  mvs_meta WHERE mvs_meta.key='label' $type_str;");

        $count = 0;
        $movabls = Array();
        while($row = $result->fetch_assoc()):
            $movabls[$row['movabls_type']][$row['movabls_GUID']]=$row['value'];
            $count++;
        endwhile;
        return $movabls;
    }
    /**
     * Adds a movabl to the given package
     * @param string $package_guid
     * @param string $movabl_type
     * @param string $movabl_guid
     * @return bool
     */
    public static function add_to_package($package_guid, $movabl_type, $movabl_guid) {

        if (!in_array(1,$GLOBALS->_USER['groups']))
            throw new Exception('Only administrators can add Movabls to a package',500);

        Movabls_Data::data_query("REPLACE INTO  mvs_packages (package_GUID, movabls_GUID, movabls_type) VALUES ('$package_guid',  '$movabl_guid',  '$movabl_type');");

        return true;
    }

    /**
     * Removes a movabl from the given package
     * @param string $package_guid
     * @param string $movabl_type
     * @param string $movabl_guid
     * @return bool
     */
    public static function remove_from_package($package_guid, $movabl_type, $movabl_guid) {

        if (!in_array(1,$GLOBALS->_USER['groups']))
            throw new Exception('Only administrators can add Movabls to a package',500);

        Movabls_Data::data_query("DELETE FROM mvs_packages WHERE mvs_packages.package_GUID = '$package_guid' AND mvs_packages.movabls_type = '$movabl_type' AND mvs_packages.movabls_GUID = '$movabl_guid';");
        return true;
    }


    /**
     * Gets a single movabl by type and GUID
     * @param string $movabl_type
     * @param array
     */
    public static function get_movabl($movabl_type, $movabl_guid) {

        global $mvs_db;

        if ($movabl_type === 'package'):

            $result = Movabls_Data::data_query("SELECT movabls_GUID, movabls_type FROM  mvs_packages WHERE package_GUID = '$movabl_guid'");

            if (empty($result))
                throw new Exception ("Movabl ($movabl_type: $movabl_guid) not found",500);
            $count = 0;
            $movabl = Array();
            while($row = $result->fetch_assoc()):
                $movabl[$count]["movabl_type"]=$row['movabls_type'];
                $movabl[$count]["movabl_GUID"]=$row['movabls_GUID'];
                $count++;
           endwhile;
        
            $meta = self::get_meta($movabl_type,$movabl_guid);
            $movabl['meta'] = isset($meta[$movabl_guid]) ? $meta[$movabl_guid] : array();
            return $movabl;
        endif;
        
        $movabl_type = $mvs_db->real_escape_string($movabl_type);
        $movabl_guid = $mvs_db->real_escape_string($movabl_guid);

        $table = self::table_name($movabl_type);
            
        $movabl = Movabls_Data::data_query("SELECT x.* FROM `mvs_$table` AS x WHERE x.{$movabl_type}_GUID = '$movabl_guid'", DATA_ARRAY);

        $meta = self::get_meta($movabl_type,$movabl_guid);
        $movabl['meta'] = isset($meta[$movabl_guid]) ? $meta[$movabl_guid] : array();

        switch ($movabl_type) {
            case 'interface':
                $movabl['content'] = json_decode($movabl['content'],true);
                break;
            case 'media':
            case 'function':
                $inputs = json_decode($movabl['inputs'],true);
                if(is_array($inputs)) $movabl['inputs'] = $inputs;
                else $movabl['inputs'] = array();
                break;
        }
        return $movabl;
	
    }

    /**
     * Gets the metadata for an array of Movabls or types, or an individual
     * Movabl or type
     * @param mixed $types (array or string)
     * @param mixed $guids (array or string)
     * @param mysqli handle $mvs_db
     * @return array
     */
    public static function get_meta($types,$guids = null) {
        global $mvs_db;

        $meta = array();

        $query = "SELECT m.* FROM `mvs_meta` AS m";

        if (!empty($guids)) {
            if (!is_array($guids))
                $guids = array($guids);
            foreach($guids as $k => $guid)
                $guids[$k] = $mvs_db->real_escape_string($guid);
            $in_string = "'".implode("','",$guids)."'";
            $where[] = "m.movabls_GUID IN ($in_string)";
        }

        if (!empty($types)) {
            if (!is_array($types))
                $types = array($types);
            foreach($types as $k => $type)
                $types[$k] = $mvs_db->real_escape_string($type);
            $in_string = "'".implode("','",$types)."'";
            $where[] = "m.movabls_type IN ($in_string)";
        }

        if (!empty($where))
            $where = 'WHERE '.implode(' AND ',$where);
        else
            $where = '';
        
        $query .= ' '.$where;

        $result = Movabls_Data::data_query($query);

        if (empty($result))
            return $meta;

        while($row = $result->fetch_assoc())
            $meta[$row['movabls_GUID']][$row['key']] = $row['value'];

        $result->free();

        return $meta;

    }

  
    /**
     * Runs an update or insert that sets the specified movabl with this data
     * @param string $movabl_type
     * @param array $data
     * @param string $movabl_guid
     * @param mysqli handle $mvs_db
     * @return $movabl_guid
     */
    public static function set_movabl($movabl_type,$data,$movabl_guid = null) {
        global $mvs_db;

        // if (!in_array(1,$GLOBALS->_USER['groups']) && self::movabls_added($movabl_type,$data,$movabl_guid))
        //    throw new Exception("Only administrators may add new movabls to a place, interface, or package",500);

        if (!empty($data['meta']))
            $meta = $data['meta'];

        switch($movabl_type) {
            case 'media':
            case 'function':
                $data['inputs'] = array_keys($data['inputs']);
                break;
            case 'place':
                //If url includes {{something}}, extract those and use them to replace the inputs
                if (preg_match_all('/{{.*}}/',$data['url'],$matches)) {
                    $data['url'] = preg_replace('/{{.*}}/','%',$data['url']);
                    $data['inputs'] = array();
                    foreach ($matches[0] as $match)
                        $data['inputs'][] = substr($match,2,-2);
                }
                break;
            case 'interface':
            case 'package':
                break;
        }
        $group_id = $data["group_id"];
        $data = self::sanitize_data($movabl_type,$data);
        $table = self::table_name($movabl_type);
        $sanitized_guid = $mvs_db->real_escape_string($movabl_guid);
        $sanitized_type = $mvs_db->real_escape_string($movabl_type);

        if (!empty($movabl_guid)) {
            $datastring = self::generate_datastring('update',$data);
            $result = Movabls_Data::data_query("UPDATE `mvs_$table` SET $datastring WHERE {$sanitized_type}_GUID = '$sanitized_guid'");
        }
        else {
            $data["{$movabl_type}_guid"] = self::generate_guid($movabl_type);
            $datastring = self::generate_datastring('insert',$data);
            $result = Movabls_Data::data_query("INSERT INTO `mvs_$table` $datastring");
            $movabl_guid = $data["{$movabl_type}_guid"];
        }
        if ($movabl_type === 'place' && !empty($group_id))
            Movabls_Permissions::set_place_permission($GLOBALS->_USER["user_GUID"], $group_id, $movabl_guid);
  
        if (!empty($meta))
            self::set_meta($meta,$movabl_type,$movabl_guid);


        return $movabl_guid;	
    }

    /**
     * Determines whether movabls have been added to the movabl specified in this revision
     * @param string $movabl_type
     * @param array $newdata
     * @param string $movabl_guid
     * @param mysqli handle $mvs_db
     * @return bool 
     */
    private static function movabls_added($movabl_type,$newdata,$movabl_guid = null) {


            global $mvs_db;
            
        if (!in_array($movabl_type,array('package','place','interface')))
            return false;

        $old_movabls = array();
        if (!empty($movabl_guid)) {
            $olddata = self::get_movabl($movabl_type,$movabl_guid);
            $old_movabls = self::get_submovabls($movabl_type,$olddata);
        }

        $new_movabls = self::get_submovabls($movabl_type,$newdata);

        foreach ($new_movabls as $movabl) {
            if (!in_array($movabl,$old_movabls))
                return true;
        }

        return false;

    }
    
    /**
     * Gets a list of movabls that are beneath the one specified
     * @param string $movabl_type
     * @param array $data 
     */
    public static function get_submovabls($movabl_type,$data) {
        
        $sub_movabls = array();
        switch ($movabl_type) {
            case 'package':
                $sub_movabls = $data['contents'];
                break;
            case 'place':
                if (!empty($data['media_GUID']))
                    $sub_movabls[] = array(
                        'movabl_type' => 'media',
                        'movabl_GUID' => $data['media_GUID']
                    );
                if (!empty($data['media_GUID']))
                    $sub_movabls[] = array(
                        'movabl_type' => 'interface',
                        'movabl_GUID' => $data['interface_GUID']
                    );
                break;
            case 'interface':
                if (!empty($data['content']))
                    $sub_movabls = self::get_tags($data['content']);
                break;
        }
        return $sub_movabls;
        
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
                    $extras = self::get_tags($value['tags'],$extras);
                elseif (isset($value['interface_GUID']))
                    $extras[] = array('movabl_type'=>'interface','movabl_GUID'=>$value['interface_GUID']);
            }
        }

        return $extras;

    }

    /**
     * Takes an array of metadata for a particular movabl and updates the existing metadata
     * entries to the entries specified
     * @param array $new_meta
     * @param string $movabl_type
     * @param string $movabl_guid
     * @param mysqli handle $mvs_db
     * @return bool 
     */
    public static function set_meta($meta,$movabl_type,$movabl_guid) {
        global $mvs_db;

        $sanitized_meta = self::sanitize_data('meta',$meta);
        $sanitized_guid = $mvs_db->real_escape_string($movabl_guid);
        $sanitized_type = $mvs_db->real_escape_string($movabl_type);

        foreach ($sanitized_meta as $k => $v)
               Movabls_Data::data_query("REPLACE INTO `mvs_meta` (`movabls_GUID`,`movabls_type`,`tag_name`,`key`,`value`) VALUES ('$sanitized_guid','$sanitized_type',NULL,'$k','$v')");

           
        return true;

    }

    /**
     * Delete a movabl from the system
     * @param mixed $movabl_type
     * @param mixed $movabl_guid
     * @return true
     */
    public static function delete_movabl($movabl_type,$movabl_guid) {
        global $mvs_db;

        $table = self::table_name($movabl_type);
        $sanitized_guid = $mvs_db->real_escape_string($movabl_guid);
        $sanitized_type = $mvs_db->real_escape_string($movabl_type);

        $result = Movabls_Data::data_query("DELETE FROM `mvs_$table` WHERE {$sanitized_type}_GUID = '$sanitized_guid'");

        self::set_meta(array(),$movabl_type,$movabl_guid);
        self::delete_references($sanitized_type,$sanitized_guid);

        return true;

    }

    /**
     * Deletes all references to this movabl in places, interfaces and packages
     * @param string $movabl_type
     * @param string $movabl_guid
     * @param mysqli handle $mvs_db
     */
    private static function delete_references($movabl_type,$movabl_guid) {
 
        //Packages
        Movabls_Data::data_query("DELETE FROM mvs_packages WHERE movabls_type LIKE '$movabl_type' AND movabl_GUID='$movabl_guid';");

        //Places
        if ($movabl_type ==="media" ||$movabl_type ==="function"): 
            $results = Movabls_Data::data_query("SELECT * FROM mvs_places WHERE {$movabl_type}_GUID LIKE '%$movabl_guid%'");
            while ($row = $results->fetch_assoc()) {
                unset($row['place_id'],$row[$movabl_type.'_GUID']);
                self::set_movabl('place', $row, $row['place_GUID']);
            }
            $results->free();
        endif;
        
        //Interface
        $results = Movabls_Data::data_query("SELECT * FROM mvs_interfaces
                                  WHERE content LIKE '%$movabl_guid%'");
        while ($row = $results->fetch_assoc()) {
            unset($row['interface_id']);
            $row['content'] = json_decode($row['content'],true);
            $row['content'] = self::delete_from_interface($row['content'],$movabl_type,$movabl_guid);
            self::set_movabl('interface', $row, $row['interface_GUID']);
        }
        $results->free();

    }

    /**
     * Runs through the interface tree and removes the given movabl
     * @param array $tree
     * @param string $movabl_type
     * @param string $movabl_guid
     * @param mysqli handle $mvs_db
     * @return array revised tree
     */
    private static function delete_from_interface($tree, $movabl_type, $movabl_guid) {

        if (!empty($tree)) {
            foreach ($tree as $tagname => $tagvalue) {
                if (!empty($tagvalue['movabl_type']) && $tagvalue['movabl_type'] == $movabl_type && $tagvalue['movabl_GUID'] == $movabl_guid) {
                    $tree[$tagname]['movabl_type'] = null;
                    $tree[$tagname]['movabl_GUID'] = null;
                }
                elseif (!empty($tagvalue['interface_GUID']) && $movabl_type == 'interface' && $tagvalue['interface_GUID'] == $movabl_guid)
                    $tree[$tagname]['interface_GUID'] = null;
                elseif (!empty($tagvalue['tags']))
                    $tree[$tagname]['tags'] = self::delete_from_interface($tree[$tagname]['tags'],$movabl_type,$movabl_guid);
            }
            return $tree;
        }
        else
            return array();

    }

    /**
     * Takes an array of data for a specified type of Movabl and sanitizes it to
     * match the correct columns and be safe for the sql query
     * @param string $movabl_type
     * @param array $data
     * @param mysqli handle $mvs_db
     * @return array 
     */
    private static function sanitize_data($movabl_type,$data) {
        global $mvs_db;
        if (empty($data))
            return $data;
            
        switch($movabl_type) {
            case 'media':
                $data = array(
                    'mimetype'      => !empty($data['mimetype']) ? $mvs_db->real_escape_string($data['mimetype']) : '',
                    'inputs'        => !empty($data['inputs']) ? $mvs_db->real_escape_string(json_encode($data['inputs'])) : '',
                    'content'       => !empty($data['content']) ? $mvs_db->real_escape_string($data['content']) : ''
                );
                break;
            case 'function':
                $data = array(
                    'inputs'        => !empty($data['inputs']) ? $mvs_db->real_escape_string(json_encode($data['inputs'])) : '',
                    'content'       => !empty($data['content']) ? $mvs_db->real_escape_string(utf8_encode($data['content'])) : ''
                );
                break;
            case 'interface':
                $data = array(
                    'content'       => !empty($data['content']) ? $mvs_db->real_escape_string(json_encode($data['content'])) : ''
                );
                break;
            case 'place':
				if (!empty($data['interface_GUID']))
					$clean_interface_GUID =  $mvs_db->real_escape_string($data['interface_GUID']);
				$data = array(
                    'url'           => $mvs_db->real_escape_string($data['url']),
                    'inputs'        => !empty($data['inputs']) ? $mvs_db->real_escape_string(json_encode($data['inputs'])) : '',
                    'https'         => $data['https'] ? '1' : '0',
                    'media_GUID'    => $mvs_db->real_escape_string($data['media_GUID'])
                );
                if (!empty($clean_interface_GUID))
                    $data['interface_GUID'] = $clean_interface_GUID;
                break;
            case 'meta':
                $pre_data = $data;
                $data = array();
                foreach ($pre_data as $k => $v)
                    $data[$mvs_db->real_escape_string($k)] = $mvs_db->real_escape_string($v);
                break;
            case 'package':
                $data = array(
                    'movabls_GUID'    => $mvs_db->real_escape_string($data['movabls_GUID']),
                    'movabls_type'    => $mvs_db->real_escape_string($data['movabls_type'])
                );
                break;
            default:
                throw new Exception('Incorrect Movabl Type',500);
                break;
        }
        return $data;

    }

    /**
     * Generates a globally unique 32-byte string consisting of 0-9 and a-z characters
     * @param string $movabl_type
     * @return string 
     */
    private static function generate_guid($movabl_type) {

        //Movabl type - 3 characters ensures uniqueness across types
        switch ($movabl_type) {
            case 'media': $type = 'mda'; break;
            case 'function': $type = 'fnc'; break;
            case 'interface': $type = 'int'; break;
            case 'place': $type = 'plc'; break;
            case 'package': $type = 'pkg'; break;
            default:
                throw new Exception ('Invalid movabl type specified for guid generation',500);
                break;
        }

        //Site ID - 6 characters in base 36 ensures uniqueness across sites
        $site_id = $GLOBALS->_SERVER['SITE_ID'];
        $site_id = base_convert($site_id,10,36);
        $site_id = str_pad($site_id,8,'0',STR_PAD_LEFT);

        //Microtime - 9 characters in base 36 ensures uniqueness within this site
        $microtime = microtime(true)*10000;
        $microtime = base_convert($microtime,10,36);

        //Random number - 12 characters in base 36 ensures randomness
        $rand = '';
        for ($i=1;$i<=12;$i++)
            $rand .= base_convert(mt_rand(0,35),10,36);

        return $type . $site_id . $microtime . $rand;

    }

    /**
     * Takes an array of sanitized data and prepares it as a string sql update or insert
     * @param string $query_type
     * @param array $data
     * @return string
     */
    private static function generate_datastring($query_type,$data) {

        if (empty($data))
            throw new Exception ('No Data Provided for '.uc_first($query_type),500);
        if ($query_type == 'update') {
            $datastring = '';
            $i = 1;
            foreach ($data as $k => $v) {
                $datastring .= $i==1 ? '' : ',';
                $datastring .= " `$k` = '$v'";
                $i++;
            }
        }
        elseif ($query_type == 'insert') {
            $datastring = '(`'.implode("`,`",array_keys($data)).'`) VALUES ';
            $datastring .= "('".implode("','",array_values($data))."')";
        }
        else
            throw new Exception ('Datastring Generator Only Works for Updates and Inserts',500);
        return $datastring;

    }

    /**
     * Gets the name of the table associated with a type of movabl
     * @param string $movabl_type
     * @return string 
     */
    public static function table_name($movabl_type) {

        if($movabl_type == 'media')
            $table = 'media';
        elseif (in_array($movabl_type,array('place','interface','function','package')))
            $table = $movabl_type.'s';
        else
            throw new Exception ('Invalid Movabl Type Specified',500);
        return $table;
        
    }
    
}
