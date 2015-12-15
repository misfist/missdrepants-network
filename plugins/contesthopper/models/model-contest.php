<?php

/**
* Model: CH_Contest.
* @package CHModel 
*/

/**
* Model class <i>CH_Contest</i> represents contest.
* @package CHModel
*/
class CH_Contest
{
    /**
    * Holds all contest attributes in array form.
    * @var mixed
    */
    protected $data;
    
    /**
    * Object can be constructed from array or contest_id. 
    * @param int|mixed $data contest ID or $data array
    * @return CH_Contest
    */
    function __construct($data)
    {
        $this->data['_valid'] = false;
        
        if(is_array($data))
            $this->from_array($data);
        else if(is_numeric($data))
            $this->data['_valid'] = $this->get($data);
        
        $this->data['ref_variable'] = apply_filters('contesthopper_ref_variable', 'ref', $this);
    }
    
    /**
    * Returns post ID of custom post type 'contesthopper' with 'auto-draft' status. If none exists, it creates new one.
    * @return int Contest ID
    */
    public static function get_new_id()
    {
        global $wpdb;
        
        $args = array('post_type' => CH_Manager::post_type, 'post_status' => 'auto-draft');
        $query = new WP_Query($args);

        if(count($query->posts)==0) // auto draft doesnt exist, create new
        {
            $new_id = wp_insert_post($args);
            
            $datetimes = self::get_default_times();
            
            update_post_meta($new_id, 'ch_disclaimer_rules_type', 'none');
            update_post_meta($new_id, 'ch_media_description_layout', 'media-top');
            update_post_meta($new_id, 'ch_widget_size', '640');
            update_post_meta($new_id, 'ch_headline_color', '#ffffff');
            update_post_meta($new_id, 'ch_headline_font', 'arial');
            update_post_meta($new_id, 'ch_description_color', '#000000');
            update_post_meta($new_id, 'ch_description_font', 'arial');
            update_post_meta($new_id, 'ch_title_background_color', '#40b3df');
            update_post_meta($new_id, 'ch_background_color', '#ffffff');
            update_post_meta($new_id, 'ch_border_color', '#2a71a2');
            update_post_meta($new_id, 'ch_winners_num', '1');
            update_post_meta($new_id, 'ch_referral_entries', '1');
            update_post_meta($new_id, 'ch_timezone', $datetimes['timezone']);
            update_post_meta($new_id, 'ch_date_start', $datetimes['start']);
            update_post_meta($new_id, 'ch_date_end', $datetimes['end']);
            update_post_meta($new_id, 'ch_submit_text', __('Join sweepstakes', 'contesthopper'));
            update_post_meta($new_id, 'ch_from_email', '"Contesthopper" <'.get_option('admin_email').'>');
            
            return $new_id;
        }    

        return $query->posts[0]->ID; // return existing auto-draft ID
    }
    
    /**
    * Loads object data from database.
    * @param int $id Contest ID
    */
    protected function get($id)
    {
        $id = intval($id);
        
        $args = array('post__in' => array($id), 'post_type' => CH_Manager::post_type, 'post_status' => array('publish', 'auto-draft'));
        $query = new WP_Query($args);
        if(count($query->posts)!=1)
            return false;
        
        $this->data = (array)$query->posts[0];
        
        global $wpdb;
        $sql = 'SELECT meta_key, meta_value FROM '.$wpdb->postmeta.' WHERE post_id='.$id;
        $data = $wpdb->get_results($sql, ARRAY_A);
        foreach($data as $entry)
        {
            if(is_serialized($entry['meta_value']))
                $entry['meta_value'] = unserialize($entry['meta_value']);
            $this->data[$entry['meta_key']] = $entry['meta_value'];

            if($entry['meta_key']=='ch_from_email')
                $this->data[$entry['meta_key']] = stripslashes($this->data[$entry['meta_key']]);
        }
        
        return true;    
    }
    
    public static function get_default_times()
    {
        $wp_offset = get_option('gmt_offset');
        $wp_timezone = get_option('timezone_string');
        
        $default_utc = '+0';
       
        // WP has either the timezone or time offset stored
        if(!empty($wp_timezone))
        {
            $res = timezone_offset_get(new DateTimeZone($wp_timezone), new DateTime());
            if($res!==false)
            {
                if($res<0)
                    $default_utc = '-';
                else
                    $default_utc = '+';
                
                $default_utc .= abs($res);
            }
        }
        
        if(!empty($wp_offset))
        {
            $wp_offset = floatval($wp_offset);
            $res = $wp_offset * 3600;
            
            if($res<0)
                $default_utc = '-';
            else
                $default_utc = '+';
                
            $default_utc .= abs($res);            
        }
        $default_time_start = current_time('timestamp');
        $default_time_end = $default_time_start + (30*24*3600);
        $default_time_start = date('Y-m-d H:i', $default_time_start);
        $default_time_end = date('Y-m-d H:i', $default_time_end);
        
        $output = array(
            'timezone' => $default_utc,
            'start' => $default_time_start,
            'end' => $default_time_end
        );
        
        return $output;
    }
    
    /**
    * Returns number of all entries for the contest.
    * @return int Number of entries.
    */
    function get_all_entries()
    {
        if(!$this->data['_valid'])
            return 0;
            
        global $wpdb;
        $sql = 'SELECT id FROM `'.$wpdb->prefix.CH_Manager::sqlt_participant.'` WHERE `contest_id`='.esc_sql($this->data['ID']).' AND `status`!="not_valid" AND `status`!="not_confirmed"';
        $data = $wpdb->get_results($sql, ARRAY_A);
        $num_participants = count($data);
        $num_referrals = 0;
        
        if($num_participants==0)
            return 0;
        
        $in_qry = '';
        foreach($data as $entry)
            $in_qry .= '"'.$entry['id'].'",';
        $in_qry = substr($in_qry, 0, strlen($in_qry)-1);
        
        $sql = 'SELECT COUNT(*) FROM `'.$wpdb->prefix.CH_Manager::sqlt_participant_meta.'` WHERE `meta_key`="referral_to" AND `meta_value` IN ('.$in_qry.')';
        $num = $wpdb->get_var($sql);
        if($num!=NULL)
            $num_referrals = $num;
        
        return intval($num_participants)+(intval($num_referrals)*intval($this->data['ch_referral_entries']));   
    }
    
    /**
    * Loads object data from array.
    * @param mixed $array
    */
    function from_array($array)
    {
        $this->data = array_merge($this->data, $array);
    }
    
    /**
    * Runs customizable SELECT database query and returns array of <i>CH_Contest</i> objects.
    * @param string $in_orderby Parameter to order the list by: 'title', 'participants', 'date_start', 'status', 'id'.
    * @param string $in_order ASC (ascending) or DESC (descending) order.
    * @param mixed $in_page Current page.
    * @param mixed $in_perpage Number of results per page.
    * @param mixed $in_status Contest status.
    * @return mixed Array of CH_Contest objects or empty array.
    */
    static function get_all($in_orderby = '', $in_order = '', $in_page = '', $in_perpage = '', $in_status = '')
    {
        global $wpdb;
        
        $filter = ' AND t1.`post_status`!="auto-draft"';
        
        $orderby_join = '';
        $orderby_where = '';
        $orderby_select = '';
        $orderby_groupby = '';
        
        if($in_orderby=='title')
        {
            $orderby = ' ORDER BY t3.`meta_value`';
            $orderby_join = ' LEFT JOIN `'.$wpdb->postmeta.'` AS t3 ON t1.ID=t3.post_id';
            $orderby_where = ' AND t3.meta_key="ch_headline"';
        }
        else if($in_orderby=='participants')
        {
            $sql_tbl = $wpdb->prefix.CH_Manager::sqlt_participant;
            
            $orderby = ' ORDER BY `num_participants`';
            $orderby_select = ', COUNT(t3.id) as `num_participants`';
            $orderby_join = ' LEFT JOIN `'.$sql_tbl.'` AS t3 ON t1.ID=t3.contest_id';
            $orderby_groupby = ' GROUP BY t3.`contest_id`';
        }
        else if($in_orderby=='date_start')
        {
            $orderby = ' ORDER BY t3.`meta_value`';
            $orderby_join = ' LEFT JOIN `'.$wpdb->postmeta.'` AS t3 ON t1.ID=t3.post_id';
            $orderby_where = ' AND t3.meta_key="ch_date_start"';
        }
        else if($in_orderby=='status')
        {
            $orderby = ' ORDER BY t3.`meta_value`';
            $orderby_join = ' LEFT JOIN `'.$wpdb->postmeta.'` AS t3 ON t1.ID=t3.post_id';
            $orderby_where = ' AND t3.meta_key="ch_status"';
        }
        else
            $orderby = ' ORDER BY t1.`id`';
                    
        $order = ' DESC';
        if(strtolower($in_order)=='asc')
            $order = ' ASC';   
        
        $page = 1;
        if(is_numeric($in_page))
            $page = $in_page;
        
        $perpage = '';
        if(is_numeric($in_perpage))
            $perpage = $in_perpage;
        
        $status = '';
        if(in_array($in_status, array('active', 'winners_picked')))  
            $status = ' AND t2.`meta_value`="'.$in_status.'"';
        
        $limit = '';
        if(!empty($perpage))
        {
            $start = ($page * $perpage) - $perpage; 
            $limit = ' LIMIT '.$start.','.$perpage;
        }
        
        $sql = 'SELECT t1.ID'.$orderby_select.' FROM '.$wpdb->posts.' AS t1 LEFT JOIN '.$wpdb->postmeta.' AS t2 ON t1.ID=t2.post_id'.$orderby_join.' WHERE t1.`post_type`="contesthopper" AND t2.`meta_key`="ch_status"'.$status.$orderby_where.$filter.$orderby_groupby.$orderby.$order.$limit;
        $data = $wpdb->get_results($sql, ARRAY_A);

        $result = array();
        
        foreach($data as $post)
        {
            $contest = new CH_Contest($post['ID']);
            if($contest->_valid)
                $result[] = $contest;       
        }

        return $result;
    }
    
    /**
    * Returns number of all contests that are not auto-drafts.
    * @return int Number of contests.
    */
    static function get_num()
    {
        global $wpdb;
        $sql = 'SELECT count(ID) FROM '.$wpdb->posts.' WHERE post_type="contesthopper" AND post_status!="auto-draft"';
        $num = $wpdb->get_var($sql);
        if($num==NULL)
            return 0;
        return $num;
    }
    
    /**
    * Creates, loads and returns <i>CH_Contest</i> object that was referred by specified referral code.
    * @param $code Referral code.
    * @return CH_Contest
    */
    public static function get_from_code($code)
    {
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        
        global $wpdb;
        
        $sql = 'SELECT `contest_id` FROM `'.$tbl_participant.'` WHERE `code`="'.esc_sql($code).'" LIMIT 1';
        $contest_id = $wpdb->get_var($sql);
        $contest = new CH_Contest($contest_id);
        return $contest;
    }
    
    /**
    * Saves object data to database.
    */
    function save()
    {
        if(!$this->data['_valid'])
            return false;
        
        $post_args = array('ID' => $this->data['ID'], 'post_status' => 'publish');
        wp_update_post($post_args);
        
        update_post_meta($this->data['ID'], 'ch_status', 'active');
        foreach($this->data as $key=>$val)
        {
            if(substr($key, 0, 3)=='ch_')
                update_post_meta($this->data['ID'], $key, $val);
        }

        return true;
    }
    
    /**
    * Changes contest status.
    * @param string $status New contest status.
    */
    function set_status($status)
    {            
        global $wpdb;                  
        update_post_meta($this->data['ID'], 'ch_status', esc_sql($status));
    }
    
    /**
    * Returns the contest start/expiry datetime/timestamp in UTC timezone.
    * @param string $what 'start' for contest start datetime, 'expire' for contest expiry datetime
    * @param string $type 'timestamp' for unix timestamp, 'mysql' for Y-m-d H:i:s formatted datetime
    */
    function get_utc_time($what = 'expire', $type = 'timestamp')
    {
        $real_time = strtotime($this->data['ch_date_end']);
        if($what=='start')
            $real_time = strtotime($this->data['ch_date_start']);
        
        $timezone = $this->data['ch_timezone'];
        if(!empty($timezone))
        {
            $timezone = intval($timezone);
            if($timezone<0)
                $real_time += abs($timezone);
            else
                $real_time -= $timezone;  
        }
        
        if($type=='mysql')
            return date('Y-m-d H:i:s', $real_time);
            
        return (int)$real_time;
    }
    
    /**
    * Checks if contest is started.
    * @return boolean
    */
    function is_started()
    {
        if(current_time('timestamp', 1)>=$this->get_utc_time('start'))
            return true;
        return false;
    }
    
    /**
    * Checks if contest is expired.
    * @return boolean
    */
    function is_expired()
    {
        if($this->data['ch_status']=='expired' || $this->data['ch_status']=='winners_picked' || $this->data['ch_status']=='expired_auto')
            return true;
                
        if(current_time('timestamp', 1)>$this->get_utc_time('expire'))
            return true;
        
        return false;
    }
    
    /**
    * Deletes the contest with all participants.
    * @param boolean $force
    */
    function delete($force = true)
    {        
        if(!$this->data['_valid'])
            return false;
            
        if($force!==true)
            $force = false;
         
        $res = wp_delete_post($this->data['ID'], $force);
        if($res===false)
            return false;
            
        $participants = CH_Participant::get_all($this->data['ID'], '', '' ,'', '', 'all');
        
        foreach($participants as $participant)
            $participant->delete();
        
        return true;        
    }
    
    /**
    * Clear all assigned winners.
    */
    function clear_winners()
    {
        global $wpdb;
        
        if(!$this->data['_valid'])
            return false;
        
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        $sql = 'UPDATE `'.$tbl_participant.'` SET `status`="" WHERE `status` LIKE "winner%" AND `contest_id`='.esc_sql($this->data['ID']);
        
        $res = $wpdb->query($sql);
        if($res===false)
            return false;
            
        return true;
    }
    
    /**
    * Get current number of assigned winners.
    */
    function get_current_winner_num()
    {
        if(!$this->data['_valid'])
            return 0;
            
        global $wpdb;
        $tbl_participant = $wpdb->prefix.CH_Manager::sqlt_participant;
        $sql = 'SELECT count(*) FROM `'.$tbl_participant.'` WHERE `status`="winner" AND `contest_id`='.esc_sql($this->data['ID']);
        
        $res = $wpdb->get_var($sql);
        $num = 0;
        if($res!=NULL)
            $num = $res;
        return $num;
    }
    
    /**
    * Pick winners.
    * @param int $num Number of winners to pick.
    */
    function pick_winners($num = 1)
    {
        if(!is_numeric($num) || $num<1)
            return false;
            
        if(!$this->data['_valid'])
            return false;
                
        $participants = CH_Participant::get_all($this->data['ID'], '', '', '', '', 'not_winner');
        
        if(count($participants)<1)
            return;
        
        $this->set_status('winners_picked');
        
        // generate a big entry_list
        $entry_list = array();
        $i = 0;
        foreach($participants as $participant)
        {
            $entry_list[$i] = $participant->id;
            $i++;
            if(isset($participant->referral_to))
            {
                $num_referrals = $participant->referral_to;
                $num_referrals = count($num_referrals);
                
                $entries_for_referral = 1;
                if(isset($this->data['ch_referral_entries']) && is_numeric($this->data['ch_referral_entries']) && $this->data['ch_referral_entries']>0)
                    $entries_for_referral = $this->data['ch_referral_entries'];
                    
                for($x=0;$x<($num_referrals*$entries_for_referral);$x++)
                {
                    $entry_list[$i] = $participant->id;
                    $i++;
                }
            }
        }
        
        $admin_email = get_option('admin_email');
        
       // echo '<pre>';var_dump($entry_list);echo '</pre>';
        
        // check for random.org quota
        $url = 'http://www.random.org/quota/?format=plain';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => $admin_email
        ));
        
        $resp = curl_exec($curl);        
        curl_close($curl);

        // echo '<pre>';var_dump(intval($resp));echo '</pre>';

        $min = 0;
        $max = count($entry_list)-1;
            
        if(empty($resp) || intval($resp)<=0) // use backup
        {
            $resp = range($min, $max);
            $result = shuffle($resp);
            if($result)
                $result = $resp;
            else
                die('Error generating random array.');
        }
        else // use random.org
        {       
            $url = 'http://www.random.org/sequences/?min='.$min.'&max='.$max.'&col=1&format=plain&rnd=new';
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url,
                CURLOPT_USERAGENT => $admin_email
            ));
            
            $resp = curl_exec($curl);        
            curl_close($curl);
            
            if(empty($resp) || strpos($resp, 'Error:')!==false) // use backup
            {                
                $resp = range($min, $max);
                $result = shuffle($resp);
                if($result)
                    $result = $resp;
                else
                    die('Error generating random array.');
            }
            else
            {    
                $result = explode("\n", $resp);
                if(empty($result[count($result)-1]))
                unset($result[count($result)-1]);
            }
        }
        
       //  echo '<pre>';var_dump($result);echo '</pre>';
        
        $winners_num = $num;
        if($winners_num>count($participants))
            $winners_num = count($participants);
        
        // echo '<pre>';var_dump($winners_num);echo '</pre>';
        
        $result_helper = 0;     
        $winner_helper = 1;
        
        $winners = array();
        
        while($winner_helper<=$winners_num)
        {
            $tmp = $result[$result_helper];
            $result_helper += 1;
            
            if(!in_array($entry_list[$tmp], $winners))
            {
                $winners[$winner_helper] = $entry_list[$tmp];
                $winner_helper += 1;
            }
            
            if($result_helper>count($result)-1)
                break;
        }
        
        // echo '<pre>';var_dump($winners);echo '</pre>';
        
        foreach($winners as $winner)
        {
            $participant = new CH_Participant($winner);
            $participant->set_status('winner');
        }
        
        return true;
    }
    
    /**
    * Magic function.
    * @param mixed $name
    */
    public function __get($name)
    {
        if(array_key_exists($name, $this->data)) 
            return $this->data[$name];

        return '';
        
        /*
        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;*/
    }

    /**
    * Magic function.
    * @param mixed $name
    */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }
}
