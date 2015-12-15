<?php

/**
* Admin list table: Participants
* @package CHTable
*/

if(!class_exists('WP_List_Table'))
    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');

/**
* Table class that handles loading and displaying WordPress list table for participants.
* @package CHTable
*/    
class CH_Table_Participants extends WP_List_Table
{
    /**
    * Number of all participants.
    * @var int
    */
    protected $found_posts;
    
    /**
    * Number of participants per page.
    * @var int
    */
    protected $per_page;
    
    /**
    * Contest to load participants from.
    * @var CH_Contest
    */
    protected $contest;
    
    /**
    * Current number of contest winners.
    * @var int
    */
    protected $current_winner_num; // todo move to CH_Contest
    
    /**
    * Extra navigation.
    * @var mixed
    */
    protected $extra_nav;
    
    /**
    * Table constructor with optional contest parameter.
    * @param CH_Contest $contest
    * @return CH_Table_Participants
    */
    function __construct($contest = '')
    {
        $this->per_page = 30;
        $this->contest = $contest;
        if(!is_object($contest))
            $this->contest = '';
        
        if($this->contest!='')
            $this->current_winner_num = $this->contest->get_current_winner_num();
            
        $this->extra_nav = array();
        $contests = CH_Contest::get_all();
        
        foreach($contests as $contest)
            $this->extra_nav[$contest->ID] = $contest->ch_headline;
        
        parent::__construct();
    }
    
    /**
    * Sets table columns.
    * @return mixed
    */
    function get_columns()
    {
        $columns = array(
            'email' => __('Email Address', 'contesthopper'),
            'first_name' => __('First name', 'contesthopper'),
            'last_name' => __('Last name', 'contesthopper'),
            'entries' => __('Entries', 'contesthopper'),
            'referrals' => __('Referrals', 'contesthopper'),
            'winner' => __('Status', 'contesthopper'),
            'ip' => __('IP Address', 'contesthopper'),
            'contest_id' => __('Contest ID', 'contesthopper')
        );
        
        return $columns;
    }
    
    /**
    * Exports requested participant list to CSV file.
    * @param int $contest_id
    */
    public static function export_csv($contest_id = '')
    {
        $items = array();
        
        $orderby = '';
        if(isset($_GET['orderby']))
            $orderby = $_GET['orderby'];
            
        $order = '';
        if(isset($_GET['order']))
            $order = $_GET['order'];
            
        $items = CH_Participant::get_all($contest_id, $orderby, $order);
        
        $csv_output = chr(239) . chr(187) . chr(191); //utf8
        $csv_output .= '"'.__('Email', 'contesthopper').'","'.__('First name', 'contesthopper').'","'.__('Last name', 'contesthopper').'","'.__('Entries', 'contesthopper').'","'.__('Referrals', 'contesthopper').'","'.__('Status', 'contesthopper').'","'.__('IP').'","'.__('Contest ID', 'contesthopper').'"';

        $csv_output .= "\n";
               
        foreach($items as $item)
        {
            $entries = 1;
            $referrals = 0;
            
            $contest = new CH_Contest($item->contest_id);
            if($contest->_valid)
            {
                $entry_val = 1;
                $referral_val = $contest->ch_referral_entries;
                $entries = $entry_val + count($item->referral_to)*intval($referral_val);
                
                $referrals = $item->referral_to;
                if(empty($referrals))
                    $referrals = 0;
                else if(!is_array($referrals))
                    $referrals = 1;
                else
                    $referrals = count($referrals);
            }
            
            $csv_output .= '"'.$item->email.'","'.$item->first_name.'","'.$item->last_name.'","'.$entries.'","'.$referrals.'","'.$item->status.'","'.$item->ip.'","'.$item->contest_id.'"'."\n";
        }
           
        $size = strlen($csv_output);
        
        $csv_file = 'contesthopper-';
        if($contest_id=='')
            $csv_file .= 'all';
        else
            $csv_file .= $contest_id;
        $csv_file .= '-'.gmdate("Ymd_His").'.csv';
            
        $ContentType = "Content-type: application/vnd.ms-excel;charset=utf-8";
        $ContentLength = "Content-Length: $size";
        $ContentDisposition = "Content-Disposition: attachment; filename=\"$csv_file\"";
        
        header($ContentType);
        header($ContentLength);
        header($ContentDisposition);

        echo $csv_output;
        die();
    }
    
    /**
    * Retrieves participant data.
    */
    function get_data()
    {   
        $contest_id = '';
        if(!empty($this->contest))
            $contest_id = $this->contest->ID;
            
        $data = array();
        $orderby = '';
        if(isset($_GET['orderby']))
            $orderby = $_GET['orderby'];
            
        $order = '';
        if(isset($_GET['order']))
            $order = $_GET['order'];
            
        $page = $this->get_pagenum();
        
        $perpage = $this->per_page;
            
        $data = CH_Participant::get_all($contest_id, $orderby, $order, $page, $perpage, 'all');
        $this->found_posts = CH_Participant::get_num($contest_id);
        
        return $data;
    }
    
    /**
    * Generates extra table navigation - contest filter.
    */
    function extra_tablenav()
    {                
        if(!isset($_GET['contest']) || empty($_GET['contest']))
            $selected = ' selected="selected"';
            
        echo '<form style="float: left;" action="admin.php" method="get">
        <input type="hidden" name="page" value="'.CH_Page_Participants::page_id.'" />
        <select name="contest">';
        echo '<option'.$selected.' value="">'.__('All Contests', 'contesthopper').'</option>';

        foreach($this->extra_nav as $name => $title) 
        {
            $selected = '';
            if(isset($_GET['contest']) && $name==$_GET['contest'])
                $selected = ' selected="selected"';
            echo '<option value="'.$name.'"'.$selected.'>'.$name.': '.$title.'</option>';
        }
        
        echo '</select>';
        submit_button(__('Apply'), 'button-secondary action', false, false);
        echo '</form>';
        
        $url = add_query_arg('export', '1');
            
        echo '<form action="'.$url.'" method="post" style="margin-left: 25px; float: left">';
        submit_button(__('Export to CVS', 'contesthopper'), 'button-secondary action', false, false);
        echo '</a></form>';
        
        if($this->contest!='')
        {
            if(!$this->contest->is_expired() || $this->contest->ch_status=='expired') // if contest has not yet expired or was forced to expire
            {
                // display stop / resume contest
                $url = add_query_arg('setexpired', '1');
                $url = admin_url('admin.php?page='.CH_Page_Participants::page_id.'&contest='.$_GET['contest']);
                 
                echo '<form action="'.$url.'" method="post" style="float: left; margin-left: 5px"><input type="hidden" name="setexpired" value="1" />';
                $text = __('Stop Contest', 'contesthopper');
                if($this->contest->ch_status=='expired')
                    $text = __('Resume Contest', 'contesthopper');
                    
                submit_button($text, 'button-secondary action', false, false);
                echo '</form>';
            }
            
            if($this->contest->is_expired() && $this->contest->ch_status=='winners_picked') // expired contest and winners picked
            {
                // display reset contest button
                $url = admin_url('admin.php?page='.CH_Page_Participants::page_id.'&contest='.$_GET['contest']);
                        
                echo '<form action="'.$url.'" method="post" style="float: left; margin-left: 5px"><input type="hidden" name="resetcontest" value="1" />';
                $text = __('Reset Contest', 'contesthopper');
                    
                submit_button($text, 'button-secondary action', false, false);
                echo '</form>';
            }
            
            // if not all winners were picked, show random pick button
            $winners_num = 1;
            if(isset($this->contest->ch_winners_num) && is_numeric($this->contest->ch_winners_num) && $this->contest->ch_winners_num>0)
                $winners_num = $this->contest->ch_winners_num;
            
            $confirm_class = '';
            if($this->current_winner_num==0 && $this->contest->ch_status!='winners_picked')
                $confirm_class = 'confirm_setwinner';
            
            if($this->current_winner_num<$winners_num)
            {
                $text =  __('Pick Random Winners', 'contesthopper');
                $url = admin_url('admin.php?page='.CH_Page_Participants::page_id.'&contest='.$_GET['contest']);
                    
                echo '<form class="'.$confirm_class.'" action="'.$url.'" method="post" style="float: left; margin-left: 5px"><input type="hidden" name="pickwinners" value="1" />';
                submit_button($text, 'button-secondary action', false, false);
                echo '</form>';
            }

            // if there are some winners picked, show clear winners button
            if($this->current_winner_num>0)
            {
                $url = admin_url('admin.php?page='.CH_Page_Participants::page_id.'&contest='.$_GET['contest']);
                $text =  __('Clear All Winners', 'contesthopper');
                
                echo '<form action="'.$url.'" method="post" style="float: left; margin-left: 5px"><input type="hidden" name="clearwinners" value="1" />';
                submit_button($text, 'button-secondary action', false, false);
                echo '</form>';
            }        
        }
    }

    /**
    * Sets sortable table columns.
    * @return mixed
    */
    function get_sortable_columns()
    {
         $sortable_columns = array(
            'email' => array('email', true),
            'first_name' => array('first_name', false),
            'last_name'  => array('last_name', false),
            'entries' => array('entries', false),
            'referrals' => array('entries', false),
            'winner' => array('status', false),
            'ip' => array('ip', false),
            'contest_id' => array('contest_id', false) 
          );
          
          if($this->contest=='')
            $sortable_columns['contest_id'] = array('contest_id', false);
          
          return $sortable_columns;
    }
    
    /**
    * Initializes table data.
    */
    function prepare_items()
    {
        $columns = $this->get_columns();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, array(), $sortable);
        
        $this->items = $this->get_data();
        
        $this->set_pagination_args( array(
            'total_items' => $this->found_posts,
            'per_page'    => $this->per_page
        ));        
    }
    
    /**
    * Generates columns.
    * 
    * @param CH_Participant $item Current participant.
    * @param string $column_name Current column text ID.
    */
    function column_default($item, $column_name)
    {
        switch($column_name)
        {
            case 'first_name':
                $first_name = '';
                if(isset($item->first_name))
                    $first_name = esc_html($item->first_name);
                    
                return $first_name;
            
            case 'last_name':
                $last_name = '';
                if(isset($item->last_name))
                    $last_name = esc_html($item->last_name);
                    
                return $last_name;
                
            case 'email':
                $output = esc_html($item->email);
                
                $url = 'admin.php?page='.CH_Page_Participants::page_id;
                if(isset($_GET['contest']))
                    $url .= '&contest='.$item->contest_id;

                $actions = array();
                if($item->status!='not_confirmed')
                {
                    $actions['not_valid'] = '<a href="'.admin_url($url.'&togglevalid='.$item->id).'">';
                    if($item->status=='not_valid')
                        $actions['not_valid'] .= __('Valid entry', 'contesthopper');
                    else
                        $actions['not_valid'] .= __('Invalid entry', 'contesthopper');
                    $actions['not_valid'] .= '</a>';
                }
                
                $actions['delete'] = '<a class="confirm_delete" href="'.admin_url($url.'&del='.$item->id).'">'.__('Delete').'</a>';
                
                $output .= $this->row_actions($actions);
                
                return $output;
               
            case 'entries':
                $contest = $this->contest;
                if(!isset($contest->_valid) || !$contest->_valid || $contest->ID!=$item->contest_id)
                    $contest = new CH_Contest($item->contest_id);
                    
                if(!$contest->_valid)
                    return 0;
                    
                $entry_val = 1;
                $referral_val = isset($contest->ch_referral_entries)?$contest->ch_referral_entries : 0;
                $referral_to = isset($contest->ch_referral_to)?$contest->ch_referral_to : array();
                                
                $num_entries = $entry_val + count($referral_to)*intval($referral_val);
                return $num_entries;
            
            case 'referrals':
                $referrals = isset($item->referral_to) ? $item->referral_to : array();
                if(empty($referrals))
                    $output = 0;
                else if(!is_array($referrals))
                    $output = 1;
                else
                    $output = count($referrals);
                    
                return $output;
            
            case 'winner':
                $status = $item->status;
                $contest = $this->contest;
                
                $output = esc_html($status);
                                    
                if(!empty($output))
                {
                    $array = array(
                        'not_valid' => __('Invalid Entry', 'contesthopper'), 
                        'not_confirmed' => __('Not Confirmed', 'contesthopper')
                    );
                    if(array_key_exists($output, $array))
                        $output = $array[$output];
                }
                
                if(!isset($contest->_valid) || !$contest->_valid)
                    return $output;
                                
                $winners_num = 1;
                if(isset($contest->ch_winners_num) && is_numeric($contest->ch_winners_num) && $contest->ch_winners_num>0)
                    $winners_num = $contest->ch_winners_num;
                    
                $confirm_class = '';
                if($this->current_winner_num==0 && $contest->ch_status!='winners_picked')
                    $confirm_class = 'confirm_setwinner';
                
                // if is winner
                if($item->status=='winner')
                {
                    $output = '<form action="admin.php?page='.CH_Page_Participants::page_id.'&contest='.$item->contest_id.'" method="post"><input type="hidden" name="removewinner" value="'.esc_attr($item->id).'"><input type="submit" class="button" value="'.__('Winner', 'contesthopper').'" /></form>';
                }
                // else if has empty status and more winners can be picked for this contest
                else if(empty($status) && is_object($contest) && $winners_num>$this->current_winner_num)
                {
                    $output = '<form class="'.$confirm_class.'" action="admin.php?page='.CH_Page_Participants::page_id.'&contest='.$item->contest_id.'" method="post"><input type="hidden" name="pickwinner" value="'.esc_attr($item->id).'"><input type="submit" class="button" value="'.__('Pick Winner', 'contesthopper').'" /></form>';
                }
                
                return $output;
                
            case 'contest_id':
                $output = isset($item->contest_id) ? $item->contest_id : '';
                return $output;
                
            case 'ip':
                $output = isset($item->ip) ? $item->ip : '';
                return $output;
                
            default:
                $output = '';
                return $output;
        }
    }
}

?>
