<?php

/**
* Model: CH_Participant.
* @package CHModel
*/

/**
* Model class <i>CH_Participant</i> represents participant.
* @package CHModel
*/
class CH_Participant
{
    /**
    * Holds required participant fields.
    * @var mixed
    */
    protected $required_fields;
    
    /**
    * Holds all contest attributes in array form.
    * @var mixed
    */
    protected $data;
    
    /**
    * Object can be constructed from array, contest_id or referral code.
    * @param int|mixed $data Participant ID or participant attribute array or referral code.
    * @param boolean $referral If true, loads object by referral code instead of ID.
    * @return CH_Contest
    */
    function __construct($data, $referral = false)
    {
        $this->data['_valid'] = false;
        $this->required_fields = array('contest_id', 'date_gmt', 'ip', 'code', 'email', 'status');
        
        if(is_array($data))
            $this->from_array($data);
        else if(is_numeric($data) && !$referral)
            $this->data['_valid'] = $this->get($data);
        else if($referral)
            $this->data['_valid'] = $this->get($data, true);
    }
    
    /**
    * Checks if participant exists in database.
    * @return boolean
    */
    function exists()
    {
        global $wpdb;
        if(!$this->data['_valid'])
            return false;
            
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        $sql = 'SELECT id FROM '.$tbl_participant.' WHERE `email`="'.esc_sql($this->data['email']).'" AND `contest_id`='.esc_sql($this->data['contest_id']);
        
        $res = $wpdb->get_var($sql);
        if($res==NULL)
            return false;
        return $res;
    }
    
    /**
    * Loads object data from database by participant ID or referral code.
    * @param int|string $id Participant ID or referral code.
    * @param boolean $ref If true loads by referral code.
    */
    protected function get($id, $ref = false)
    {
        global $wpdb;
     
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        $tbl_participantmeta = $wpdb->prefix.CH_Manager::sqlt_participant_meta;
        
        if($ref==false)
        {
            $id = intval($id);
            $sql = 'SELECT id, contest_id, date_gmt, ip, code, email, status FROM `'.$tbl_participant.'` WHERE `id`='.$id.' LIMIT 1';
        }
        else
        {
            $id = esc_sql($id);
            $sql = 'SELECT id, contest_id, date_gmt, ip, code, email, status FROM `'.$tbl_participant.'` WHERE `code`="'.$id.'" LIMIT 1';
        }
        $data = $wpdb->get_results($sql, ARRAY_A);
        if(count($data)!=1)
            return false;

        $this->data = $data[0];
        
        $sql = 'SELECT meta_key, meta_value FROM `'.$tbl_participantmeta.'` WHERE `participant_id`='.$this->data['id'];
        $data = $wpdb->get_results($sql, ARRAY_A);
        foreach($data as $entry)
        {
            $value = $entry['meta_value'];
            if(is_serialized($value))
                $value = unserialize($value);
                
            if(!isset($this->data[$entry['meta_key']]))
                $this->data[$entry['meta_key']] = $value;
            else
            {
                if(is_array($this->data[$entry['meta_key']]))
                    $this->data[$entry['meta_key']][] = $value;
                else
                {
                    $tmp = $this->data[$entry['meta_key']];
                    $this->data[$entry['meta_key']] = array($tmp, $value);
                }
            }
        }
        
        return true;    
    }
    
    /**
    * Loads object data from array.
    * @param mixed $data
    */
    protected function from_array($data)
    {
        $this->data = array_merge($this->data, $data);
        $this->data['_valid'] = $this->validate();
    }
    
    /**
    * Validates object attributes.
    * @return boolean
    */
    protected function validate()
    {
        foreach($this->required_fields as $field)
        {
            if(!isset($this->data[$field]))
                return false;
        }
        
        if(empty($this->data['email']))
            return false;
            
        return true;
    }
    
    /**
    * Returns all object attributes in array form.
    * @return mixed
    */
    function to_array()
    {
        return $this->data;
    }
    
    /**
    * Returns number of all participants. Optional filtering by contest ID and/or participant status.
    * @param int $contest_id
    * @param string $in_status
    * @return Number of participants.
    */
    static function get_num($contest_id = '', $in_status = '')
    {
        global $wpdb;
    
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        
        $sql = 'SELECT count(id) FROM `'.$tbl_participant.'` WHERE 1=1';
        
        if($in_status!='all')
            $sql .= ' AND `status`!="not_confirmed"';
        
        if(is_numeric($contest_id))
            $sql .= ' AND `contest_id`='.esc_sql($contest_id);
        
        $num = $wpdb->get_var($sql);
        if($num==NULL)
            return 0;
        
        return $num;
    }
    
    /**
    * Runs customizable SELECT database query and returns array of <i>CH_Participant</i> objects.
    * @param int $contest_id Contest ID to select participants from.
    * @param mixed $in_orderby Parameter to order the list by: 'id', 'email', 'status', 'contest_id', 'ip', 'first_name', 'last_name', 'entries'.
    * @param mixed $in_order ASC (ascending) or DESC (descending) order.
    * @param mixed $in_page Current page.
    * @param mixed $in_perpage Number of results per page.
    * @param mixed $in_status Participant status.
    * @return mixed Array of CH_Participant objects or empty array.
    */
    static function get_all($contest_id = '', $in_orderby = '', $in_order = '', $in_page = '', $in_perpage = '', $in_status = '')
    {
        global $wpdb;
        
        $orderby = ' ORDER BY';
        $order = '';
        
        $meta_query = false;
        $aggr_query = false;
        
        if(in_array($in_orderby, array('email', 'status', 'contest_id', 'ip')))
            $orderby .= ' `'.$in_orderby.'`';
        else if(in_array($in_orderby, array('first_name', 'last_name')))
        {
            $orderby .= ' t2.`meta_value`';
            $meta_query = true;
        }
        else if($in_orderby=='entries')
        {
            $in_orderby = 'referral_to';
            $orderby .= ' `entries`';
            $meta_query = true;
            $aggr_query = true;
        }
        else
            $orderby .= ' `id`';
        
        if($in_order=='asc')
            $order = ' ASC';
        else 
            $order = ' DESC';
        
        if($in_status=='all')
            $status = ' t1.`status` LIKE "%"';
        else if($in_status=='winner')
            $status = ' t1.`status` LIKE "winner%"';
        else if($in_status=='not_winner')
            $status .= ' t1.`status`!="not_confirmed" AND t1.`status`!="winner" AND t1.`status`!="not_valid"';
        else
            $status = ' t1.`status`!="not_confirmed"';
        
        $limit = '';
        
        $page = 1;
        if(is_numeric($in_page))
            $page = $in_page;
        
        $perpage = '';
        if(is_numeric($in_perpage))
            $perpage = $in_perpage;
        
        if(!empty($perpage))
        {
            $start = ($page * $perpage) - $perpage; 
            $limit = ' LIMIT '.$start.','.$perpage;
        }
        
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        $tbl_participant_meta = $wpdb->prefix.CH_Manager::sqlt_participant_meta;
        
        $sql = 'SELECT t1.`id`';
        if($aggr_query)
            $sql .= ', COUNT(t1.`id`) AS `entries`';
        
        $sql .= ' FROM `'.$tbl_participant.'` AS t1';
        
        if($meta_query)
            $sql .= ' LEFT JOIN `'.$tbl_participant_meta.'` AS t2 ON t1.`id`=t2.`participant_id`';
            
        $sql .= ' WHERE'.$status;
        if(is_numeric($contest_id))
            $sql .= ' AND t1.`contest_id`='.esc_sql($contest_id);
        
        if($meta_query && !$aggr_query)
            $sql .= ' AND t2.`meta_key`="'.esc_sql($in_orderby).'"';
        
        if($aggr_query)
            $sql .= ' AND t2.`meta_key`!="tmp_referral" GROUP BY t1.`id`';
        
        $sql .= $orderby.$order.$limit;
        
        $id_data = $wpdb->get_results($sql, ARRAY_A);
                
        $data = array();
        foreach($id_data as $id)
            $data[] = new CH_Participant(intval($id['id']));
        
        return $data;           
    }
    
    /**
    * Adds new participant to database.
    */
    function add()
    {
        if(!$this->data['_valid'])
            return false;
            
        global $wpdb;
        
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        
        $contest_id = esc_sql($this->data['contest_id']);
        $date_gmt = esc_sql($this->data['date_gmt']);
        $ip = esc_sql($this->data['ip']);
        $code = esc_sql($this->data['code']);
        $email = esc_sql($this->data['email']);
        $status = esc_sql($this->data['status']);
                
        $sql = 'INSERT INTO `'.$tbl_participant.'` (contest_id, date_gmt, ip, code, email, status) VALUES ('.$contest_id.', "'.$date_gmt.'", "'.$ip.'", "'.$code.'", "'.$email.'", "'.$status.'");';

        $res = $wpdb->query($sql);
        if($res===false)
            return false;
        
        $this->data['id'] = $wpdb->insert_id;
                           
        return $this->data['id'];
    }
    
    /**
    * Deletes the participant with all metadata.
    * @return Number of deleted participants.
    */
    function delete()
    {
        if(!$this->data['_valid'])
            return false;
        
        global $wpdb;
        
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        $tbl_participant_meta = $wpdb->prefix.CH_Manager::sqlt_participant_meta;
        
        $affected_rows = 0;
        $sql = 'DELETE FROM `'.$tbl_participant_meta.'` WHERE `participant_id`='.esc_sql($this->data['id']);
        $res = $wpdb->query($sql);
        if($res===false)
            return false;
        $affected_rows += $res;
        
        $sql = 'DELETE FROM `'.$tbl_participant_meta.'` WHERE `meta_key`="referral_to" AND `meta_value`="'.esc_sql($this->data['id']).'"';
        $res = $wpdb->query($sql);
        if($res===false)
            return false;
        $affected_rows += $res;
        
        $sql = 'DELETE FROM `'.$tbl_participant.'` WHERE `id`='.esc_sql($this->data['id']).' LIMIT 1';
        $res = $wpdb->query($sql);
        if($res===false)
            return false;
        $affected_rows += $res;
        
        return $affected_rows;
    }
    
    /**
    * Generates random character sequence.
    * @param int $length
    */
    static function random_pass($length = 8)
    {
        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
        $pass = array();
        for($i=0;$i<$length;$i++) 
        {
            $n = rand(0, strlen($alphabet)-1);
            $pass[$i] = $alphabet[$n];
        }
        
        return implode($pass);
    }
    
    /**
    * Generates unique participant code.
    * @return string Random code.
    */
    static function generate_code()
    {
        global $wpdb;
        
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        
        $res = NULL;
        $random_code = '';
        do
        {
            $random_code = md5(uniqid().self::random_pass());
            $sql = 'SELECT id FROM '.$tbl_participant.' WHERE code="'.esc_sql($random_code).'"';
            $res = $wpdb->get_var($sql);
        }while($res!=NULL);
        
        return $random_code;    
    }
    
    /**
    * Adds metadata to participant.
    * @param string $key
    * @param mixed $value
    */
    function add_meta($key, $value)
    {
        if(!$this->data['_valid'])
            return false;
            
        global $wpdb;
        
        $tbl_participant_meta = $wpdb->prefix.CH_Manager::sqlt_participant_meta;
        
        $sql = 'INSERT INTO `'.$tbl_participant_meta.'` (participant_id, meta_key, meta_value) VALUES('.esc_sql($this->data['id']).', "'.esc_sql($key).'", "'.esc_sql($value).'");';
        $res = $wpdb->query($sql);

        if($res===false)
            return false;
        
        if(isset($this->data[$key]) && !is_array($this->data[$key]))
        {
            $tmp = $this->data[$key];
            $this->data[$key] = array($tmp, $value);
        }
        else if(isset($this->data[$key]) && is_array($this->data[$key]))
            $this->data[$key][] = $value;
        else
            $this->data[$key] = $value;
        
        return true;        
    }
    
    /**
    * Deletes participant metadata.
    * @param string $key
    */
    function del_meta($key)
    {
        if(!$this->data['_valid'])
            return false;
            
        global $wpdb;
        
        $tbl_participant_meta = $wpdb->prefix.CH_Manager::sqlt_participant_meta;
        
        $sql = 'DELETE FROM `'.$tbl_participant_meta.'` WHERE `participant_id`='.esc_sql($this->data['id']).' AND `meta_key`="'.esc_sql($key).'";';
        $res = $wpdb->query($sql);
        if($res===false)
            return false;
        
        if(isset($this->data[$key]))
            unset($this->data[$key]);
        
        return true;        
    }
    
    /**
    * Changes participant status.
    * @param string $status New participant status.
    */
    function set_status($status)
    {
        if(!$this->data['_valid'])
            return false;
                
        global $wpdb;
        
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        
        $sql = 'UPDATE `'.$tbl_participant.'` SET `status`="'.esc_sql($status).'" WHERE `id`='.esc_sql($this->data['id']).' LIMIT 1';
        $res = $wpdb->query($sql);
        if($res===false)
            return false;
            
        return true;   
    }
    
    /**
    * Returns number of referrals for current participant.
    * @return int Number of referrals.
    */
    public function get_referral_num()
    {
        $referrals = 0;
        if(isset($this->data['referral_to']))
        {   
            $ref_to = $this->data['referral_to'];
            if(is_array($ref_to))
                $referrals = count($ref_to);
            else
                $referrals = 1;
        }
    
        return $referrals;
    }
    
    /**
    * Magic function.
    * @param mixed $name
    * @return mixed
    */
    public function __get($name)
    {
        if (array_key_exists($name, $this->data)) 
            return $this->data[$name];

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);

        return null;
    }

    /**
    * Magic function.
    * @param mixed $name
    * @return mixed
    */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
}
