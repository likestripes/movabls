<?php
/**
 * Session class sets and maintains user authentication
 * @author Travis Hardman
 */
class Movabls_Session {

    /**
     * Uses the cookies variable to get the session and create the $_USER global
     * @param mysqli handle $mvs_db
     * @global array $_USER
     * @global array $_SESSION
     */
    public static function get_session() {
        global $mvs_db;
        //This code can only be called in bootstrapping before $GLOBALS is created
        if (!empty($GLOBALS->_USER))
            return;

        global $_USER,$_SESSION;
        $_USER = array();
        $_SESSION = array();

        //Approx every 10000 requests, delete expired sessions
        if (mt_rand(1,10000) == 14)
            self::delete_expired_sessions($mvs_db);

        if (!empty($_COOKIE['sslsession'])) {
            $type = 'ssl';
            $session = $_COOKIE['sslsession'];
        }
        elseif (!empty($_COOKIE['httpsession'])) {
            $type = 'http';
            $session = $_COOKIE['httpsession'];
        }

        unset($_COOKIE['httpsession'],$_COOKIE['sslsession']);

        if (!isset($type))
            return;

        $results = Movabls_Data::data_query("SELECT * FROM mvs_sessions WHERE {$type}session = '$session'", DATA_RESULT, FALSE);

        if ($results->num_rows > 0) {
            $session = $results->fetch_assoc();
            $results->free();

            if (strtotime($session['expiration']) < time()) {
                self::delete_session($session['session_id'],$mvs_db);
                return;
            }
            else {
                //Update expiration times
                $expiration = date('Y-m-d H:i:s',time()+$session['term']);
                Movabls_Data::data_query("UPDATE mvs_sessions SET expiration = '$expiration'
                               WHERE session_id = {$session['session_id']}", DATA_RESULT, FALSE);
                self::set_cookie($type.'session', $session[$type.'session'], $session['term']);

                //Create $_SESSION array
                $results = Movabls_Data::data_query("SELECT `key`,`value` FROM mvs_sessiondata
                                          WHERE session_id = {$session['session_id']}", DATA_RESULT, FALSE);
                while ($row = $results->fetch_assoc())
                    $_SESSION[$row['key']] = json_decode($row['value'],true);
                $results->free();
                
                //Create $_USER array
                $results = Movabls_Data::data_query("SELECT * FROM `mvs_users`
                                          WHERE user_id = {$session['user_id']}");
                if ($mvs_db->errno)
                    throw new Exception('MYSQL Error: '.$mvs_db->error,500);
                $_USER = $results->fetch_assoc();
                $_USER['session_id'] = $session['session_id'];
                unset($_USER['password'],$_USER['nonce']);
                $results->free();
                
                //Add $_USER['groups']
                $results = Movabls_Data::data_query("SELECT DISTINCT group_id FROM `mvs_group_memberships`
                                          WHERE user_id = {$session['user_id']}");
                if ($mvs_db->errno)
                    throw new Exception('MYSQL Error: '.$mvs_db->error,500);
                $_USER['groups'] = array();
                while($row = $results->fetch_assoc())
                    $_USER['groups'][] = $row['group_id'];
                $results->free();
            }
        }
        else {
            self::remove_cookies();
            return;
        }

    }

    /**
     * Sets a key => value pair of session data
     * @param string $key
     * @param mixed $value
     */
    public static function set($key,$value) {
        global $mvs_db;

        $key = $mvs_db->real_escape_string($key);
        $value = json_encode($value);

        if (isset($GLOBALS->_SESSION[$key])) {
            Movabls_Data::data_query("UPDATE mvs_sessiondata SET value = '$value'
                           WHERE session_id = {$GLOBALS->_USER['session_id']}
                           AND key = '$key'", DATA_RESULT, FALSE);
        }
        else {
            Movabls_Data::data_query("INSERT INTO mvs_sessiondata (session_id,key,value)
                           VALUES ({$GLOBALS->_USER['session_id']},'$key','$value')", DATA_RESULT, FALSE);
        }
        $GLOBALS->set_session_data($key,$value);

    }

    /**
     * Unsets a session data key
     * @param string $key
     */
    public static function delete($key) {

        Movabls_Data::data_query("DELETE mvs_sessiondata
                       WHERE session_id = {$GLOBALS->_USER['session_id']}
                       AND key = '$key'", DATA_RESULT, FALSE);
        $GLOBALS->set_session_data($key,null);

    }

    /**
     * Creates a session for the specified user
     * @param int $user_id
     * @param mysqli handle $mvs_db
     */
    public static function create_session($user_id) {
        global $mvs_db;

        //TODO: Uncomment this when you have HTTPS set up
        //if (!$GLOBALS->_SERVER['HTTPS'])
          //  throw new Exception('Users may only log in over a secure (HTTPS) connection',500);

        $sslsession = self::get_token();
        $httpsession = self::get_token();
        $user_id = $mvs_db->real_escape_string($user_id);

        //To determine session term, take the term settings for each of the
        //user's groups and use the shortest term
        $results = Movabls_Data::data_query("SELECT MIN(g.session_term) AS term FROM `mvs_groups` g
                                  INNER JOIN `mvs_group_memberships` m ON g.group_id = m.group_id
                                  WHERE m.user_id = $user_id AND g.session_term != 'NULL'", DATA_RESULT, FALSE);
        if ($mvs_db->errno)
            throw new Exception('MYSQL Error: '.$mvs_db->error,500);
        $row = $results->fetch_assoc();
        $results->free();
        $term = $row['term'];

        //If term is not defined. Session will remain open for a year.
        if (empty($term))
            $term = 31536000;

        $expiration = date('Y-m-d H:i:s',time()+$term);

        Movabls_Data::data_query("INSERT INTO mvs_sessions (sslsession,httpsession,user_id,term,expiration)
                       VALUES ('$sslsession','$httpsession',$user_id,$term,'$expiration')", DATA_RESULT, FALSE);

        self::set_cookie('sslsession', $sslsession, $term);
        self::set_cookie('httpsession', $httpsession, $term);

    }

    /**
     * Creates an authentication token to tie the cookie to the database
     */
    private static function get_token() {

        return uniqid(sha1(mt_rand(0,100000).time().@$_SERVER['REMOTE_ADDR']), true);

    }

    /**
     * Sets a movabls session cookie
     * @param string $name
     * @param string $token
     * @param string $expiration
     */
    private static function set_cookie($name,$token,$term) {

        $expiration = time()+$term;
        $secure = $name == 'sslsession';
        setcookie($name,$token,$expiration,'/',$_SERVER['HTTP_HOST'],$secure,true);
        //TODO: Cookies aren't updating when we set them again.  WTF?  Means we can't reset expirations
        //or delete the cookies.  That's very annoying.

    }

    /**
     * Delete the specified session from the database and remove session cookies
     * @param int $session_id
     * @param mysqli handle $mvs_db
     */
    public static function delete_session($session_id = null) {
        global $mvs_db;

        if (!empty($session_id)) {
            Movabls_Data::data_query("DELETE FROM mvs_sessions WHERE session_id = $session_id", DATA_RESULT, FALSE);
            Movabls_Data::data_query("DELETE FROM mvs_sessiondata WHERE session_id = $session_id", DATA_RESULT, FALSE);
        }

        self::remove_cookies();
        
    }

    /**
     * Remove all session-related cookies
     */
    private static function remove_cookies() {

        self::set_cookie('sslsession',false,-86400);
        self::set_cookie('httpsession',false,-86400);

    }

    /**
     * Runs through the database and deletes expired sessions
     * @param mysqli_handle $mvs_db
     */
    private static function delete_expired_sessions() {
        global $mvs_db;

        Movabls_Data::data_query("DELETE FROM mvs_sessiondata d
                       INNER JOIN mvs_sessions s ON d.session_id = s.session_id
                       WHERE s.expires < NOW()", DATA_RESULT, FALSE);
        Movabls_Data::data_query("DELETE FROM mvs_sessions WHERE expiration < NOW()", DATA_RESULT, FALSE);

    }


}