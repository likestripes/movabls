<?php
/**
 * Core Movabls Class - Instantiating this class runs the system
 * @author Travis Hardman
 */
class Movabls_Run {

    private $mvsdb; //MySQLi database handle
    private $media; //Media lambda handles
    private $functions; //Function lambda handles
    private $places; //Place urls
    private $interfaces; //Interface objects

    public function __construct() {

        //TODO: Database Authentication and Permissions (and files for that matter)

        $this->mvsdb = new mysqli('localhost','root','h4ppyf4rmers','db_filet');
        $place = $this->get_place();
        if (!empty($place->interface_GUID))
            $this->get_interface($place->interface_GUID);
        else
            $place->interface_GUID = null;
        $this->select_movabls($place->media_GUID);
        print_r($this->run_movabl('media',$place->media_GUID,$place->interface_GUID));

    }

    /**
     * Takes the Request URI and determines which place to run, adding it to the place array along the way
     * @return array from database
     */
    private function get_place() {

        //Find correct place to use (static places [without %] take precedence over dynamic places [with %])
        $url = $this->mvsdb->real_escape_string($GLOBALS->_SERVER['REQUEST_URI']);
        $result = $this->mvsdb->query("SELECT place_GUID,url,https,media_GUID,interface_GUID FROM `mvs_places`
					   WHERE ('$url' LIKE url OR '$url/' LIKE url)");
        //Logic: Look for the URL with the greatest length before a '%' sign
        $max = 0;
        while($row = $result->fetch_object()) {
            if ($row->url == $url) {
                $place = $row;
                break;
            }
            $static = strpos($row->url,'%');
            if ($static > $max) {
                $max = $static;
                $place = $row;
            }
        }
        $result->free();

        if (!isset($place))
            throw new Exception ('Place Not Found',404);

        $this->places->{$place->place_GUID} = $place->url;

        if ($place->https && !$GLOBALS->_SERVER['HTTPS']) {
            header('Location: https://'.$GLOBALS->_SERVER['HTTP_HOST'].$GLOBALS->_SERVER['REQUEST_URI']);
            die();
        }
        
        return $place;

    }

    /**
     * Selects an interface, gets its tags, and adds it to the interfaces array
     * @param <string> $interface_GUID
     */
    private function get_interface($interface_GUID) {

        if (!isset($this->interfaces->$interface_GUID)) {
            $interface_GUID = $this->mvsdb->real_escape_string($interface_GUID);
            $result = $this->mvsdb->query("SELECT content FROM mvs_interfaces WHERE interface_GUID = '$interface_GUID'");
            $interface = $result->fetch_object();
            $result->free();
            if (empty($interface))
                return null;
                
            $interface = json_decode($interface->content);
            $this->interfaces->$interface_GUID = $interface;
            $this->get_tags($interface);
        }

    }

    /**
     * Runs through interface and Populates lists of media and functions to select
     * @param <stdClass> $tags
     */
    private function get_tags($tags) {
        
        if (empty($tags))
            return false;

        foreach($tags as $value) {
            if (isset($value->movabl_GUID) && $value->movabl_type == 'media') {
                if (!isset($this->media->{$value->movabl_GUID}))
                    $this->media->{$value->movabl_GUID} = null;
            }
            elseif (isset($value->movabl_GUID) && $value->movabl_type == 'function') {
                if (!isset($this->functions->{$value->movabl_GUID}))
                    $this->functions->{$value->movabl_GUID} = null;
            }
            elseif (isset($value->movabl_GUID) && $value->movabl_type == 'place') {
                if (!isset($this->places->{$value->movabl_GUID}))
                    $this->places->{$value->movabl_GUID} = null;
            }
            if (isset($value->tags))
                $this->get_tags($value->tags);
            elseif (isset($value->interface_GUID))
                $this->get_interface($value->interface_GUID);
        }

    }

    /**
     * Runs sql queries to collect all media and functions, and sets the mimetype
     * for the place
     * @param <string> $primary_GUID = the primary media for the place
     */
    private function select_movabls($primary_GUID) {

        if (!isset($this->media->$primary_GUID))
            $this->media->$primary_GUID = null;

        foreach ($this->media as $key => $val)
            $media[] = $this->mvsdb->real_escape_string($key);
        $media = '"'.implode('","',$media).'"';

        $result = $this->mvsdb->query("SELECT media_GUID,inputs,mimetype,content FROM mvs_media
                                       WHERE media_GUID IN ($media)");
        if (empty($result))
            throw new Exception ("No Media Found",500);

        while ($row = $result->fetch_object()) {

            $content_mime_type=split("/",$row->mimetype);

            if ($content_mime_type[0] != "text")
                $row->content  = (binary)$row->content;
                
            $row->inputs = json_decode($row->inputs);
            if (empty($row->inputs)) {
                $row->inputs = array();
                $argstring = '';
            }
            else
                $argstring = '$'.implode(',$',$row->inputs);
                
            $renderer = new Movabls_MediaRender($row->content,$row->inputs);

            if ($content_mime_type[0] == "text")
                $code = 'ob_start(); ?>'.$renderer->output.'<?php return ob_get_clean();';
            else{ 
                $safe_binary_string = base64_encode($renderer->output);
                $code = "return base64_decode(\"$safe_binary_string\");";
            }

            $this->media->{$row->media_GUID} = new StdClass();
            $this->media->{$row->media_GUID}->inputs = $row->inputs;
            $this->media->{$row->media_GUID}->handle = create_function($argstring, $code);
            if ($row->media_GUID == $primary_GUID)
                header('Content-Type: '.$row->mimetype);
        }
        $result->free();

        if (!empty($this->functions)) {
            
            foreach ($this->functions as $key => $val)
                $functions[] = $this->mvsdb->real_escape_string($key);
            $functions = '"'.implode('","',$functions).'"';

            $result = $this->mvsdb->query("SELECT function_GUID,inputs,content FROM mvs_functions
                                           WHERE function_GUID IN ($functions)");
            if (empty($result))
                throw new Exception ("Functions Not Found",500);
            while ($row = $result->fetch_object()) {
                $row->inputs = json_decode($row->inputs);
                if (empty($row->inputs)) {
                    $row->inputs = array();
                    $argstring = '';
                }
                else
                    $argstring = '$'.implode(',$',$row->inputs);
                $this->functions->{$row->function_GUID} = new StdClass();
                $this->functions->{$row->function_GUID}->inputs = $row->inputs;
                $this->functions->{$row->function_GUID}->handle = create_function($argstring, $row->content);
            }
            $result->free();
            
        }

        if (!empty($this->places)) {

            foreach ($this->places as $key => $val)
                $places[] = $this->mvsdb->real_escape_string($key);
            $places = '"'.implode('","',$places).'"';

            $result = $this->mvsdb->query("SELECT place_GUID,url FROM mvs_places
                                           WHERE place_GUID IN ($places)");
            if (empty($result))
                throw new Exception ("Places Not Found",500);
            while ($row = $result->fetch_object()) {
                $this->places->{$row->place_GUID} = $row->url;
            }
            $result->free();

        }

    }

    /**
     * Runs a movabl and returns the result
     * @param <string> $type = media or function
     * @param <string> $media_GUID
     * @param <string> $interface_GUID = interface we're running
     * @param <StdClass> $tags = tags passed to this media
     * @param <bool> $toplevel = whether this is the top level of the interface
     * @return <string> rendered content
     */
    private function run_movabl($type,$movabl_GUID,$interface_GUID,$tags = null,$toplevel = true) {

        if ($type == 'place')
            return $this->places->$movabl_GUID;
        elseif ($type == 'function')
            $type = 'functions';
        elseif ($type != 'media')
            return null;

        if (empty($this->$type->$movabl_GUID))
            return null;

        $movabl = $this->$type->$movabl_GUID;

        if (empty($movabl->inputs) || (empty($tags) && !$toplevel))
            $inputs = array();
        elseif ($toplevel)
            $inputs = $this->run_tags($interface_GUID,$this->interfaces->$interface_GUID,$movabl->inputs,true);
        else //Run tags within interface
            $inputs = $this->run_tags($interface_GUID,$tags,$movabl->inputs,false);

        return call_user_func_array($movabl->handle,$inputs);

    }

    /**
     * Runs tags specified at a level of an interface, be they movabls, toplevel tags, or expressions
     * Runs tags in order given by the interface (even the ones that don't apply to
     * an input), then applies them to the inputs given in the $inputs argument.
     * @param <string> $interface_GUID
     * @param <StdClass> $tags = all tags to set and their instructions
     * @param <array> $inputs = tags to return (null if for a php standard library function)
     * @param <bool> $toplevel = whether this is an interface top-level
     * @return <array> set inputs
     */
    private function run_tags($interface_GUID,$tags,$inputs,$toplevel = false) {

        foreach ($tags as $name => $tag) {

            if (isset($tag->toplevel_tag))
                $tags->$name = $this->interfaces->$interface_GUID->{$tag->toplevel_tag};
            elseif (isset($tag->expression))
                $tags->$name = $this->run_expression($tag->expression);
            elseif (isset($tag->movabl_GUID)) {
                if (isset($tag->interface_GUID))
                    $tags->$name = $this->run_movabl($tag->movabl_type, $tag->movabl_GUID, $tag->interface_GUID);
                elseif (isset($tag->tags))
                    $tags->$name = $this->run_movabl($tag->movabl_type, $tag->movabl_GUID, $interface_GUID, $tag->tags, false);
                else
                    $tags->$name = $this->run_movabl($tag->movabl_type, $tag->movabl_GUID, $interface_GUID, null, false);
            }
            else
                $tags->$name = null;

            if ($toplevel)//if this is top-level, set the tag in $this->interfaces
                $this->interfaces->$interface_GUID->$name = $tags->$name;

        }

        $return = array();
        //PHP standard library functions do not have defined inputs - use all tags in order
        if ($inputs === null) {
            foreach ($tags as $tag)
                $return[] = $tag;
        }
        //Otherwise, use inputs as specified
        else {
            foreach ($inputs as $input) {
                if (isset($tags->$input))
                    $return[] = $tags->$input;
                else
                    $return[] = null;
            }
        }
        
        return $return;
        
    }

    /**
     * Runs a php expression and returns its value;
     * @param <string> $expression - php expression
     * @return <mixed> evaluated value
     */
    private function run_expression($expression) {

        $tempfunction = create_function('',"return $expression;");
        return call_user_func($tempfunction);

    }

}
?>