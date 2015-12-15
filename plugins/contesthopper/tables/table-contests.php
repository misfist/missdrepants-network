<?php

/**
* Admin list table: Contests
* @package CHTable
*/

if(!class_exists('WP_List_Table'))
    require_once(ABSPATH.'wp-admin/includes/class-wp-list-table.php');

/**
* Table class that handles loading and displaying WordPress list table for contests.
* @package CHTable
*/
class CH_Table_Contests extends WP_List_Table
{
    /**
    * Number of all contests.
    * @var int
    */
    private $found_posts;
    
    /**
    * Number of contests per page.
    * @var int
    */
    private $per_page;
    
    /**
    * Table constructor.
    * @return CH_Table_Contests
    */
    function __construct()
    {
        $this->per_page = 15;

        parent::__construct();
    }
    
    /**
    * Sets table columns.
    * @return mixed
    */
    function get_columns()
    {
        $columns = array(
            'title' => __('Title', 'contesthopper'),
            'participants' => __('Participants', 'contesthopper'),
            'date_start' => __('Start time', 'contesthopper'),
            'duration' => __('Duration', 'contesthopper'),
            'status' => __('Status', 'contesthopper')
        );
        
        return $columns;
    }
    
    /**
    * Retrieves contest data.
    */
    function get_data()
    {            
        $orderby = '';
        $order = 'desc';
        if(isset($_GET['orderby']))
            $orderby = $_GET['orderby'];
        if(isset($_GET['order']))
            $order = $_GET['order'];
        
        $data = array();
        $data = CH_Contest::get_all($orderby, $order, $this->get_pagenum(), $this->per_page);
        $this->found_posts = CH_Contest::get_num();
        
        return $data;
    }
    
    /**
    * Sets sortable table columns.
    */
    function get_sortable_columns()
    {
         $sortable_columns = array(
            'title' => array('title', false),
            'participants' => array('participants', false),
            'date_start'  => array('date_start', false),
            //'duration' => array('duration', false),
            'status' => array('status', false) 
          );
          
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
    * @param CH_Contest $item Current contest.
    * @param string $column_name Current column text ID.
    */
    function column_default($item, $column_name)
    {
        switch($column_name)
        {
            case 'title':
                $headline = isset($item->ch_headline) ? esc_html($item->ch_headline) : '';
                $output = '<b>'.esc_html($item->ID).':</b> <a href="'.admin_url('admin.php?page='.CH_Page_Contest::page_id.'&contest='.$item->ID).'">'.$headline.'</a>';
                
                $actions = array();
                $actions['edit'] = '<a href="'.admin_url('admin.php?page='.CH_Page_Contest::page_id.'&contest='.$item->ID).'">'.__('Edit').'</a>';
                $actions['delete'] = '<a class="confirm_delete" href="'.admin_url('admin.php?page='.CH_Page_List::page_id.'&action=del&contest='.$item->ID).'">'.__('Delete').'</a>';
                $actions['dashboard'] = '<a href="'.admin_url('admin.php?page='.CH_Page_Contest::page_id.'&contest='.$item->ID.'&ch_page=dashboard').'">'.__('Dashboard', 'contesthopper').'</a>';
                $actions['participants'] = '<a href="'.admin_url('admin.php?page='.CH_Page_Participants::page_id.'&contest='.$item->ID).'">'.__('Participants', 'contesthopper').'</a>';

                $output .= $this->row_actions($actions);

                return $output;
            
            case 'participants':                
                $part_num = CH_Participant::get_num($item->ID);
                $part_num_all = CH_Participant::get_num($item->ID, 'all');
                
                $participant_num = $part_num;
                if($part_num_all!=$part_num)
                    $participant_num .= ' ('.$part_num_all.')';

                return $participant_num;
                            
            case 'date_start':
                $date_start = isset($item->ch_date_start) ? $item->ch_date_start : '';
                return $date_start;
            
            case 'duration':
                $date_start = isset($item->ch_date_start) ? $item->ch_date_start : '';
                $date_end = isset($item->ch_date_end) ? $item->ch_date_end : '';
                
                $start_time = strtotime($date_start);
                $end_time = strtotime($date_end);
                
                $duration = $end_time - $start_time;
                $duration_text = '';
                if($duration<3600*24)
                {
                    $hrs = round($duration / 3600);
                    $duration_text = $hrs.' hour(s)';
                }   
                else
                {
                    $days = intval(floor($duration / 86400));
                    $hrs = round(($duration-($days*86400)) / 3600);
                    $duration_text = $days.' day(s), '.$hrs.' hour(s)';
                }
                $duration_text .= ' (ends '.$item->ch_date_end.')';
                return $duration_text;
            
            case 'status':
                $status = isset($item->ch_status) ? $item->ch_status : '';
                
                $isexpired = $item->is_expired();
                $isstarted = $item->is_started();
                
                $contest_status = __('Expired', 'contesthopper');
                if(!$isstarted)
                    $contest_status = __('Not active yet', 'contesthopper');
                else if($isexpired && $item->ch_status=='expired')
                    $contest_status = __('Expired (forced)', 'contesthopper');
                else if($isexpired && $item->ch_status=='active')
                    $contest_status = __('Expired (automatic)', 'contesthopper');
                else if($isexpired && $item->ch_status=='winners_picked')
                    $contest_status = __('Expired (winners picked)', 'contesthopper');
                else if($isstarted && !$isexpired)
                    $contest_status = __('Active', 'contesthopper');
                   
                return $contest_status;
                                            
            default:
                return '';
        }
    }
}
