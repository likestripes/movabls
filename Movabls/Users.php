<?php
/**
 * Users class manages user data, and controls session creation
 * @author Travis Hardman
 */
class Movabls_Users {

    /**
     * Creates a user in the system and user databases
     * @param int $user_id
     * @param string $password
     * @param array $userfields = array(fieldname => value)
     * @param mysqli handle $mvsdb
     * @return user_id
     */
    public static function create($password,$userfields) {
        global $mvsdb;

        $nonce = self::generate_nonce();
        $password = self::generate_password($password, $nonce);

        $fields = array();
        foreach ($userfields as $k => $v)
            $fields[$mvsdb->real_escape_string($k)] = $mvsdb->real_escape_string($v);

        $fieldnames = $fieldvalues = '';
        if (!empty($fields)) {
            $fieldnames = ',`'.implode('`,`',array_keys($fields)).'`';
            $fieldvalues = ",'".implode("','",array_values($fields))."'";
        }

        Movabls_Data::data_query("INSERT INTO `mvs_users` (user_id,password,nonce$fieldnames)
                       VALUES ('','$password','$nonce'$fieldvalues)");

        if ($mvsdb->errno)
			throw new Exception('MYSQL Error: '.$mvsdb->error,500);
        else
            return $mvsdb->insert_id;
    }

	
	  public static function create_group($group_name, $session_term) {
        global $mvsdb;


        Movabls_Data::data_query("INSERT INTO `mvs_groups` (group_id,name,session_term)
                       VALUES ('','$group_name','$session_term')");

        if ($mvsdb->errno)
			throw new Exception('MYSQL Error: '.$mvsdb->error,500);
        else
            $group_id =  $mvsdb->insert_id;
			
	$mvsdb->query("INSERT INTO  `mvs_group_memberships` ( `user_id` ,`group_id` ) VALUES ('6',  '$group_id' );");
			return $group_id;

    }
	

    /**
     * Creates a session for a user based on a unique field => value and a password
     * @param string $field = unique field in the users table
     * @param string $value = value of that unique field for this user
     * @param string $password = the password the user entered
     */
    public static function login($field,$value,$password) {
        global $mvsdb;
        if (isset($GLOBALS->_USER['session_id'])) self::logout();

        $field = $mvsdb->real_escape_string($field);
        $value = $mvsdb->real_escape_string($value);

        $results = Movabls_Data::data_query("SELECT user_id,password,nonce FROM `mvs_users`
                                  WHERE `$field` = '$value'");
        if ($mvsdb->errno)
         return false;   //throw new Exception('MYSQL Error: '.$mvsdb->error,500);
        elseif ($results->num_rows > 1)
          return false;//  throw new Exception ("Login field must be unique",500);
        elseif ($results->num_rows < 1)
          return false;//  throw new Exception ("Incorrect $field - password combination",500);

        $user = $results->fetch_assoc();
        $results->free();

        //TODO: Rate limit login attempts (ie. 3 attempts per minute)

        if (self::generate_password($password,$user['nonce']) != $user['password']) {
            //throw new Exception ("Incorrect $field - password combination",500);
		return false;
			}else{
            Movabls_Session::create_session($user['user_id'],$mvsdb);
		return true;
		}
    }

    
    /**
     * Public wrapper function to destroy the current user's session
     */
    public static function logout() {
        $session_id = $GLOBALS->_USER['session_id'];
        Movabls_Session::delete_session($session_id);
    }

    /**
     * Change a user's password
     * @param user_id $user_id
     * @param string $password
     * @param mysqli handle $mvsdb
     */
    public static function change_password($user_id,$password) {
        global $mvsdb;

        $user_id = $mvsdb->real_escape_string($user_id);
        $nonce = self::generate_nonce();
        $password = self::generate_password($password, $nonce);

        Movabls_Data::data_query("UPDATE `mvs_users` SET password = '$password',nonce = '$nonce'
                       WHERE user_id = $user_id");

        if ($mvsdb->errno)
            throw new Exception('MYSQL Error: '.$mvsdb->error,500);

    }

	
	
	public static function get_users() {

        $result = Movabls_Data::data_query("SELECT g.group_id, g.name, g.session_term, u.email, u.user_id FROM `mvs_users` u
LEFT JOIN `mvs_group_memberships` gm ON u.user_id= gm.user_id
LEFT JOIN `mvs_groups` g ON gm.group_id= g.group_id" );

        if(empty($result))
            return array();
        
        $users=Array();
	   
        while ($row = $result->fetch_assoc()) {
            if(!isset($users[$row["user_id"] ][$row["group_id"] ]["email"]) )
                $users[$row["user_id"] ]["email"] = $row["email"];
            
            $users[$row["user_id"] ][$row["group_id"] ]=$row;
        }

        $result->free();
        return $users;

    }
	
	
	public static function get_groups() {

        $result = Movabls_Data::data_query("SELECT g.group_id, g.name, g.session_term, u.email, u.user_id FROM `mvs_groups` g
LEFT JOIN `mvs_group_memberships` gm ON g.group_id= gm.group_id
INNER JOIN `mvs_users` u ON gm.user_id= u.user_id" );
        if(empty($result))
            return array();
	  $groups=Array();
      
	  while ($row = $result->fetch_assoc()) {
	   if(!isset($groups[$row["group_id"] ]["group_id"]) )$groups[$row["group_id"] ]["group_id"] = $row["group_id"];
	   if(!isset($groups[$row["group_id"] ]["name"]) )$groups[$row["group_id"] ]["name"] = $row["name"];
	   if(!isset($groups[$row["group_id"] ]["session_term"]) )$groups[$row["group_id"] ]["session_term"] = $row["session_term"];
           $groups[$row["group_id"] ][$row["user_id"] ]=$row;
		  
        }

        $result->free();

        return $groups;

    }

	
    /**
     * Generates a password hash from a password and nonce
     * @param string $password
     * @param string $nonce
     */
    private static function generate_password($password,$nonce) {

        $combo = $password . $nonce;
        return hash('sha512',$combo);

    }

    /**
     * Generates a random nonce for salting passwords
     * @return string
     */
    private static function generate_nonce() {
        return md5(mt_rand());
    }

}