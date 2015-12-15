<?php

/**
* Admin page: list.
* @package CHPage 
*/

/**
* Page class handles back-end page <i>List contests</i>.
* @package CHPage
*/
class CH_Page_List
{
    /**
    * Page slug.
    */
    const page_id = 'ch_page_list';
    
    /**
    * Page hook.
    * @var string
    */
    protected $page_hook;
    
    /**
    * Constructs new page object and adds new entry to WordPress admin menu
    */
    function __construct()
    {
        add_menu_page('ContestHopper', 'ContestHopper', 'manage_options', self::page_id, array(&$this, 'generate'));
        $this->page_hook = add_submenu_page(self::page_id, __('All Contests', 'contesthopper'), __('All Contests', 'contesthopper'), 'manage_options', self::page_id, array(&$this, 'generate'));   
        add_action('load-'.$this->page_hook, array(&$this, 'init'));
    }
    
    /**
    * Init method, called when accessing the page.
    */
    function init()
    {
        add_action('admin_footer', array(&$this, 'list_scripts'));
        
        if(isset($_GET['action']) && isset($_GET['contest']) && $_GET['action']=='del')
        {
            $contest = new CH_Contest($_GET['contest']);
            if($contest->_valid)
                $contest->delete(true);
                
            wp_safe_redirect(admin_url('admin.php?page='.self::page_id));
        }
    }
    
    /**
    * Lists javascripts in page footer.
    */
    function list_scripts()
    {
        $text = __('Are you sure you want to delete this contest?', 'contesthopper');
        echo <<<HTML
<script type="text/javascript">
jQuery(".confirm_delete").click(function() {
    var res = confirm("{$text}");
    if(!res)
        return false;
    return true;
});
</script>
HTML;
    }
    
    /**
    * Generates page content.
    * @uses CH_Table_Contests
    */
    function generate()
    {        
        echo '<div class="wrap">
<h2>ContestHopper <a href="admin.php?page='.CH_Page_Contest::page_id.'" class="add-new-h2">'.__('Add New', 'contesthopper').'</a></h2>';

        $table_list = new CH_Table_Contests();
        $table_list->prepare_items();
        $table_list->display();

        echo '</div>';
    }
}

