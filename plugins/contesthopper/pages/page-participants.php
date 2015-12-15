<?php

/**
* Admin page: participants.
* @package CHPage
*/

/**
* Page class that handles back-end page <i>Participants</i>
* @package CHPage
*/
class CH_Page_Participants
{
    /**
    * Page slug.
    */
    const page_id = 'ch_page_participants';
    
    /**
    * Page slug.
    * @var string
    */
    protected $page_hook;
    
    /**
    * Constructs new page object and adds new entry to WordPress admin menu.
    */
    function __construct()
    {
        $this->page_hook = add_submenu_page(CH_Page_List::page_id, __('Participants', 'contesthopper'), __('Participants', 'contesthopper'), 'manage_options', self::page_id, array(&$this, 'generate'));   
        add_action('load-'.$this->page_hook, array(&$this, 'init'));
    }
    
    /**
    * Init method, called when accessing the page. Handles processing of participant and contest $_POST and $_GET requests.
    */
    function init()
    {
        $contest = '';
        if(isset($_GET['contest']))
            $contest = $_GET['contest'];
            
        if(isset($_GET['export']))
            CH_Table_Participants::export_csv($contest);
            
        if(isset($_GET['togglevalid']))
        {
            $participant = new CH_Participant($_GET['togglevalid']);
            if($participant->_valid)
            {
                if($participant->status=='not_valid')
                    $participant->set_status('');
                else if($participant->status!='not_confirmed')
                    $participant->set_status('not_valid');
            }
            
            $url = 'admin.php?page='.self::page_id;
            if(isset($_GET['contest']))
                $url .= '&contest='.$_GET['contest'];
                
            wp_safe_redirect(admin_url($url));
            die();
        }
        
        if(isset($_GET['del']))
        {
            $participant = new CH_Participant($_GET['del']);
            if($participant->_valid)
                $participant->delete();
            
            $url = 'admin.php?page='.self::page_id;
            if(isset($_GET['contest']))
                $url .= '&contest='.$_GET['contest'];
                
            wp_safe_redirect(admin_url($url));
            die();
        }
        
        if(isset($_GET['contest']))
        {
            $contest = new CH_Contest($_GET['contest']);
            if($contest->_valid!=true) 
            {            
                wp_safe_redirect(admin_url('admin.php?page='.CH_Page_Participants::page_id));
                die();    
            }
        
            if(isset($_POST['resetcontest']))
            {
                if($contest->ch_status=='winners_picked')
                {
                    $contest->set_status('active');
                    $contest->clear_winners();
                }
                
                if(isset($_GET['redirect']) && $_GET['redirect']=='contest')
                    wp_safe_redirect(admin_url('admin.php?page='.CH_Page_Contest::page_id.'&contest='.$contest->ID.'&ch_page=dashboard'));
                else
                    wp_safe_redirect(admin_url('admin.php?page='.self::page_id.'&contest='.$contest->ID));
                die();
            }
        
            if(isset($_POST['setexpired']))
            {
                if($contest->ch_status=='expired')
                    $contest->set_status('active');
                else if($contest->ch_status=='active' || $contest->ch_status=='')
                    $contest->set_status('expired');
                
                if(isset($_GET['redirect']) && $_GET['redirect']=='contest')
                    wp_safe_redirect(admin_url('admin.php?page='.CH_Page_Contest::page_id.'&contest='.$contest->ID.'&ch_page=dashboard'));
                else
                    wp_safe_redirect(admin_url('admin.php?page='.self::page_id.'&contest='.$contest->ID));
                die();
            }
            
            if(isset($_POST['pickwinners']))
            {
                $winners_num = 1;
                if(isset($contest->ch_winners_num) && is_numeric($contest->ch_winners_num) && $contest->ch_winners_num>0)
                    $winners_num = $contest->ch_winners_num;
                    
                $current_winners = $contest->get_current_winner_num();
                $pick_num = intval($winners_num) - intval($current_winners);
                
                if($pick_num>0)
                    $contest->pick_winners($pick_num);

                if(isset($_GET['redirect']) && $_GET['redirect']=='contest')
                    wp_safe_redirect(admin_url('admin.php?page='.CH_Page_Contest::page_id.'&contest='.$contest->ID.'&ch_page=dashboard'));     
                else
                    wp_safe_redirect(admin_url('admin.php?page='.self::page_id.'&contest='.$contest->ID));
                die();
            }
            
            if(isset($_POST['clearwinners']))
            {
                $contest->clear_winners();
                
                if(isset($_GET['redirect']) && $_GET['redirect']=='contest')
                    wp_safe_redirect(admin_url('admin.php?page='.CH_Page_Contest::page_id.'&contest='.$contest->ID.'&ch_page=dashboard'));
                else
                    wp_safe_redirect(admin_url('admin.php?page='.self::page_id.'&contest='.$contest->ID));
                die();
            }
            
            if(isset($_POST['pickwinner']))
            {
                $winners_num = 1;
                if(isset($contest->ch_winners_num) && is_numeric($contest->ch_winners_num) && $contest->ch_winners_num>0)
                    $winners_num = $contest->ch_winners_num;
                    
                if($contest->get_current_winner_num()<$winners_num)
                {
                    $participant = new CH_Participant($_POST['pickwinner']);
                    if($participant->_valid)
                    {
                        $participant->set_status('winner');
                        $contest->set_status('winners_picked');
                    }
                }
                
                if(isset($_GET['redirect']) && $_GET['redirect']=='contest')
                    wp_safe_redirect(admin_url('admin.php?page='.CH_Page_Contest::page_id.'&contest='.$contest->ID.'&ch_page=dashboard'));
                else
                    wp_safe_redirect(admin_url('admin.php?page='.self::page_id.'&contest='.$contest->ID));
                die();
            }
            
            if(isset($_POST['removewinner']))
            {
                $participant = new CH_Participant($_POST['removewinner']);
                if($participant->_valid)
                {
                    if(substr($participant->status, 0, 6)=='winner')
                        $participant->set_status('');
                }
                
                if(isset($_GET['redirect']) && $_GET['redirect']=='contest')
                    wp_safe_redirect(admin_url('admin.php?page='.CH_Page_Contest::page_id.'&contest='.$contest->ID.'&ch_page=dashboard'));
                else
                    wp_safe_redirect(admin_url('admin.php?page='.self::page_id.'&contest='.$contest->ID));
                die();
            }
        }
        
        add_action('admin_footer', array(&$this, 'list_scripts'));
    }
    
    /**
    * Lists javascript in page footer.
    */
    function list_scripts()
    {
        echo '
        <script type="text/javascript">
        jQuery(".confirm_delete").click(function() {
            var res = confirm("'.__('Are you sure you want to delete this participant?', 'contesthopper').'");
            if(!res)
                return false;
            return true;
        });
        </script>';
        /*
        <script type="text/javascript">
        jQuery(".confirm_setwinner").submit(function() {
            var res = confirm("'.__('Are you sure you want to pick winner(s)? You will not be able to resume this contest.', 'contesthopper').'");
            if(!res)
                return false;
            return true;
        });
        </script>
        '; */
    }
    
    /**
    * Generates page content.
    * @uses CH_Table_Participants
    */
    function generate()
    {        
        $contest_id = '';
        if(isset($_GET['contest']))
            $contest_id = $_GET['contest'];

        $contest = new CH_Contest($contest_id);
        if(!$contest->_valid)
            $contest = '';
            
        echo '<div class="wrap">
<h2>'.__('Participants', 'contesthopper').'</h2>';

        $table_list = new CH_Table_Participants($contest);
        $table_list->prepare_items();
        $table_list->display();

        echo '</div>';
    }
}
