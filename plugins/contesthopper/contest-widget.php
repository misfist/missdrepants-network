<?php

/**
* Contains Wordpress widget and main contest presentation "widget".
* @package CHWidget 
*/

/**
* ContestHopper Wordpress widget class
* @package CHWidget
*/
class CH_Contest_WP_Widget extends WP_Widget 
{
    /**
    * Wordpress widget constructor.
    */
    public function __construct() {
        parent::__construct(
            'contesthopper_widget', 
            'ContestHopper',
            array( 'description' => __( 'ContestHopper widget', 'text_domain' ), ) 
        );
    }

    /**
    * Widget presentation method. Calls the main <i>CH_Widget::html()</i> presentation class.
    * @param mixed $args
    * @param mixed $instance
    */
    public function widget( $args, $instance ) 
    {      
        echo $args['before_widget'];
        echo CH_Widget::html(array('contest' => $instance['contest_id']));
        echo $args['after_widget'];
    }

    /**
    * Wordpress widget form process.
    * @param mixed $new_instance
    * @param mixed $old_instance
    */
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['contest_id'] = strip_tags($new_instance['contest_id']);

        return $instance;
    }

    /**
    * Wordpress widget settings.
    * @param mixed $instance
    */
    public function form($instance) 
    {
        $contest_id = '';
        if(isset($instance['contest_id']))
            $contest_id = $instance['contest_id'];
        
        $contests = CH_Contest::get_all();
        
        echo '<p>'._e('Contest:', 'contesthopper').'<select id="'.$this->get_field_id('contest_id').'" name="'.$this->get_field_name('contest_id').'" class="widefat">';
        
        foreach($contests as $contest)
        {
            $selected = '';
            if($contest_id==$contest->ID)
                $selected = ' selected="selected"';
                
            echo '<option value="'.esc_attr($contest->ID).'"'.$selected.'>'.esc_html($contest->ch_headline).'</option>';
        }
        
        echo '</select></p>';
    }

}

/**
* Registering new WordPress widget.
*/
add_action('widgets_init', create_function('', 'register_widget("CH_Contest_WP_Widget");'));


/**
* Main CH front-end widget presentation class.
* @package CHWidget
*/
class CH_Widget
{
    /**
    * Stores all widget data in array form.
    * 
    * <pre>
    * array(
    *   'contest'       => CH_Contest, // this widget is presenting
    *   'widget_id'     => string, // widget identifier (div id, mainly for javascripts)
    *   'url'           => string, // url widget was presented on
    *   'ref'           => string, // referral hash (if user was referred)
    *   'participant'   => CH_Participant // empty if user did not register for the contest yet
    * );
    * </pre>
    * @var mixed
    * @access protected
    */
    protected $data;
    
    /**
    * Stores currently processed contest object.
    * @var CH_Widget
    */
    static $current_widget = '';
    
    /**
    * Tracks number of widgets on the page.
    * @var int
    */
    static $widget_count = 0;
    
    /**
    * Constructs new CH_Widget object
    * 
    * @param mixed $data Widget data
    * <pre>
    * array(
    *   'contest'       => CH_Contest, // contest object widget is presenting
    *   'widget_id'     => string, // widget identifier (div id, mainly for javascripts)
    *   'url'           => string, // url widget was presented on
    *   'ref'           => string, // referral hash (if user was referred)
    *   'participant'   => CH_Participant // empty if user did not register for the contest yet
    * );
    * </pre>
    * @return CH_Widget
    */
    function __construct(&$data)
    {
        $this->data = $data;
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
    }

    /**
    * Builds the Google font API css url for selected contest fonts
    * @param mixed $fonts
    * @return string Google font API css url or empty string.
    */
    function google_style($fonts) // TODO awkward method placement? unnecessary complicated
    {
        $google_style = 'http://fonts.googleapis.com/css?family=';
        $google_fonts = '';
        
        $first = true;
        foreach($fonts as $font)
        {
            if($font['google'])
            {
                if(!$first)
                    $google_fonts .= '|';
                $first = false;
                $google_fonts .= urlencode($font['font']);
            }
        }
        
        if(!empty($google_fonts))
            return $google_style.$google_fonts;
        
        return '';
    }
    
    /**
    * Retrieves selected contest fonts.
    * @return mixed Array with attributes 'google' => true|false, 'font' => 'font face'.
    */
    function prepare_font()
    {              
        $fonts = array();
        $fonts['headline'] = array('font' => $this->contest->ch_headline_font, 'google' => false);
        $fonts['description'] = array('font' => $this->contest->ch_description_font, 'google' => false);
                
        foreach($fonts as &$font)
        {
            $face = $font['font'];
            
            $is_google = false;
            if(substr($face, 0, 7)=='google_')
            {
                $face = urldecode(substr($font['font'], 7));
                $is_google = true;
            }
            else
            {
                $face = urldecode($face);
                $face = ucwords($face);
            }
            
            $font['google'] = $is_google;
            $font['font'] = $face;
        }
                
        return $fonts;        
    }
    
    /**
    * Main contest widget presentation method.
    * 
    * Called by WordPress widget and shortcode to present the widget to front-end.
    * @param mixed $atts Attributes in array form 
    * <pre>
    * $atts = array(
    *   'contest' => contest ID, 
    *   'preview' => true|false
    * );
    * </pre>
    */
    static function html($atts)
    {
        extract(shortcode_atts(array(
          'contest' => '0',
          'preview' => false
          ), $atts ) );
        
        self::$widget_count += 1;
        
        wp_enqueue_style('ch_css_widget', CH_Manager::$plugin_url.'/templates/default/widget.css'); // TODO template support
        wp_enqueue_script('ch_js_widget');
        wp_localize_script('ch_js_widget', 'ch_ajax', array('ajaxurl' => admin_url('admin-ajax.php')));
                
        wp_enqueue_script('zeroclipboard', CH_Manager::$plugin_url.'/js/ZeroClipboard.min.js');
        
        $data = array();
        $data['contest'] = new CH_Contest($contest);
        
        if(!$data['contest']->_valid) // invalid contest
        {
            // bail
            return __('Invalid contest.', 'contesthopper');
        }
        
        // get widget id
        $data['widget_id'] = 'ch_widget-'.$data['contest']->ID.'_'.self::$widget_count;
        
        // get current ref
        $data['ref'] = '';
        if(isset($_GET[$data['contest']->ref_variable]))
            $data['ref'] = $_GET[$data['contest']->ref_variable];
        
        // get current url
        $www = '';
        if(isset($_SERVER['HTTP_HOST']))
        {
            if(strpos($_SERVER['HTTP_HOST'], '://www.')!==false)
                $www = 'www.';

            if(strpos($_SERVER['SERVER_NAME'], '://www.')!==false)
                $www = '';
        }

        $data['url'] = 'http://'.$www.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
        $data['url'] = remove_query_arg('ref', $data['url']);
        if($preview!=false)
            $data['url'] = 'http://sample.url/';
        
        // get participant if possible
        $data['participant'] = '';
        if(isset($_COOKIE['contesthopper_'.$data['contest']->ID])) // check for existing cookies
        {
            $participant_id = $_COOKIE['contesthopper_'.$data['contest']->ID]; // get participant from the cookie
            $data['participant'] = new CH_Participant($participant_id, true);
            if(!$data['participant']->_valid || $data['participant']->contest_id!=$data['contest']->ID)
                $data['participant'] = '';
        }
        
        // dummy participant used when previewing the contest
        $dummy_participant =  array('id' => 0, 'contest_id' => $data['contest']->ID, 'date_gmt' => date("Y-m-d H:i:s"), 'ip' => '127.0.0.1', 'code' => '1234', 'email' => 'sample@email.url', 'first_name' => 'Sample first', 'last_name' => 'Sample last', '_valid' => true);
        
        if($preview=='before_submit')
            $data['participant'] = '';
        else if($preview=='doubleoptin')
        {
            $dummy_participant['status'] = 'not_confirmed';
            $data['participant'] = new CH_Participant($dummy_participant);
        }
        else if($preview=='after_submit')
        {
            $dummy_participant['status'] = '';
            $data['participant'] = new CH_Participant($dummy_participant);
        }

        self::$current_widget = new CH_Widget($data);
        $output = self::get_template('widget');

        return $output;
    }
    
    /**
    * Returns current widget
    * @return CH_Widget
    */
    static function current_widget()
    {
        return self::$current_widget;
    }
    
    /**
    * Returns the template content.
    * @param string $tpl Template name. No / \ * . characters allowed.
    */
    static function get_template($tpl)
    {
        $template_name = 'default';
        $template_name = apply_filters('ch_template_name', $template_name);
        $template_name = str_replace(array('/', '\\', '*', '.'), '', $template_name);
        
        $tpl = str_replace(array('/', '\\', '*', '.'), '', $tpl);       
        if(file_exists(CH_Manager::$plugin_dir.'/templates/'.$template_name.'/'.$tpl.'.php')) // TODO: better templating support
        {
            ob_start();
            include(CH_Manager::$plugin_dir.'/templates/'.$template_name.'/'.$tpl.'.php');
            $data = ob_get_clean();
            return $data;
        }
        
        return false;        
    }

    /**
    * Main contest widget process method.
    * Called by AJAX script on contest registration form submit.
    */
    public static function process()
    {
        global $wpdb;

        // check for valid contest
        $contest = new CH_Contest(intval($_POST['contest_id']));
        if($contest->_valid==false)
        {
            echo '<div class="error">'.__('Invalid contest', 'contesthopper').'</div>';
            die();
        }
        
        // get POST data
        $widget_id = esc_attr($_POST['div_id']);
        $url = urldecode($_POST['url']);
        $email = esc_sql($_POST['email']);
        $first_name = '';
        if(!empty($_POST['first_name']))
            $first_name = esc_sql($_POST['first_name']);
        $last_name = '';
        if(!empty($_POST['last_name']))
            $last_name = esc_sql($_POST['last_name']);
        $ref = '';
        if(!empty($_POST['ch_ref']))
            $ref = esc_sql($_POST['ch_ref']);
       
        $do_process = true; // flag to indicate invalid input ~ do not process any data, just print the widget
        
        // init widget data
        $data = array();
        $data['contest'] =& $contest;
        $data['widget_id'] = $widget_id;
        $data['url'] = $url;
        $data['ref'] = $ref;
        $data['participant'] = '';
        $data['error'] = array();
        $data['status'] = '';
        
        // if the contest did not start yet or has already ended, just echo the original widget (started/expired error messages are handled in the template)
        if(!$contest->is_started() || $contest->is_expired())
        {
            self::$current_widget = new CH_Widget($data);
            echo self::get_template('widget');
            die();
        }
        
        // validate email       
        $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,4}$/';
        if(preg_match($pattern, $email)!=1)
        {
            $data['error']['email'] = 1;
            $do_process = false;
        }
        
        // validate names
        if($contest->ch_name_field=='1' && $contest->ch_name_field_req=='1')
        {
            if(empty($first_name))
            {
                $data['error']['first_name'] = 1;
                $do_process = false;
            }
            
            if(empty($last_name))
            {
                $data['error']['last_name'] = 1;
                $do_process = false;
            }
        }

        if($do_process) // if input is ok
        {
            $double_optin = false;
            if($contest->ch_double_optin=='1')
                $double_optin = true;
            
            // prepare participant data
            $participant_data = array('ip' => $_SERVER['REMOTE_ADDR'],
                'date_gmt' => current_time('mysql', 1),
                'contest_id' => $contest->ID,
                'code' => CH_Participant::generate_code(),
                'email' => $email,
                'status' => ''                    
            );
                    
            if($double_optin)
                $participant_data['status'] = 'not_confirmed';
        
            $participant = new CH_Participant($participant_data);
            $participant_id = $participant->exists();
                
            if($participant_id===false) // we have a new participant (unique email address for this contest)
            {
                $participant_id = $participant->add();

                if($contest->ch_name_field=='1') // if using name fields, store the values
                {
                    $participant->add_meta('first_name', $first_name);
                    $participant->add_meta('last_name', $last_name);
                }

                if($contest->ch_referral_field=='1') // if using referral field generate and store short url for the new participant referral link
                {
                    $ref_url = add_query_arg($contest->ref_variable, $participant->code, $url);
                    $ref_url_short = self::process_shorten_url($contest, $participant, $ref_url);
                    if(!empty($ref_url_short))
                        $participant->add_meta('short_ref', $ref_url_short);
                }

                if(!empty($ref) && $contest->ch_referral_field=='1') // if participant was referred, store it
                { 
                    if(!$double_optin)
                    {
                        $referral = new CH_Participant($ref, true);
                        if($referral->_valid && !empty($participant_id))
                            $referral->add_meta('referral_to', $participant_id); // store that participant was referred by $referral
                    }
                    else // if is double-optin, can't store referral until participant confirms his email
                        $participant->add_meta('tmp_referral', $ref);
                }

                if(!$double_optin)
                {
                    // process stuff
                    self::process_email($contest, $participant);
                    self::process_integration($contest, $participant);
                    self::process_cookie($contest, $participant);
                }
                else
                {
                    // if double_optin, process different stuff
                    self::process_optin_email($contest, $participant, $url);
                }
            }
            else // already existing participant (non-unique email address for this contest)
            {
                $participant = new CH_Participant($participant_id);
                if($participant->status!='not_confirmed') // if existing participant is confirmed 
                {
                    self::process_cookie($contest, $participant); // reset his cookie
                }
                else
                { // if existing participant is not_confirmed (did not confirm the double_optin)
                    self::process_optin_email($contest, $participant, $url); // resend the confirmation email
                }
            }
        }
        
        $data['participant'] = $participant;
        self::$current_widget = new CH_Widget($data);
        echo self::get_template('widget');  
        die();
    }
    
    /**
    * Shortens the $url with goo.gl
    * @param CH_Contest $contest
    * @param CH_Participant $participant
    * @param string $url
    * @return string|boolean Shortened url or false on error.
    */
    static function process_shorten_url($contest, $participant, $url)
    {
        // user should register for a contest only once -> storing short url only once, 
        // if there is the same contest on multiple pages and user registers twice (deletes his cookie), only one short url will be saved (the first)
        if(isset($participant->short_ref)) 
            return false;
                   
        $google_apikey = urlencode($contest->ch_googleapi);
        if(empty($google_apikey))
            $google_apikey = 'AIzaSyDE2OGgu88jiLw9UD4ACQNd_prf_V8CwRE';

        $api_url = 'https://www.googleapis.com/urlshortener/v1/url/?key='.$google_apikey;

        $data = array('longUrl' => $url);
        $data_string = json_encode($data);
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                                                               
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($data_string)));                                                                                                                   
        $result = curl_exec($ch);
        $result_data = json_decode($result, true);
        
        if(isset($result_data['error']))
            return false;
            
        if(!isset($result_data['id']))
            return false;
            
        return $result_data['id'];
    }
    
    /**
    * Sends confirmation email to participant.
    * @param CH_Contest $contest
    * @param CH_Participant $participant
    */
    static function process_email($contest, $participant)
    {
        $confirmation_email = $contest->ch_confirmation_email;        
        if($confirmation_email!='1')
            return;

        $to = $participant->email;
        $subject = $contest->ch_confirmation_email_subject;
        
        $first_name = isset($participant->first_name) ? $participant->first_name : '';
        $last_name = isset($participant->last_name) ? $participant->last_name : '';

        $subject = str_replace('{FIRST_NAME}', $first_name, $subject);
        $subject = str_replace('{LAST_NAME}', $last_name, $subject);
        
        $message = $contest->ch_confirmation_email_text;
        $message = str_replace('{FIRST_NAME}', $first_name, $message);
        $message = str_replace('{LAST_NAME}', $last_name, $message);

        $from_email = '';
        if(isset($contest->ch_from_email))
            $from_email = $contest->ch_from_email;

        if(empty($from_email))
            $from_email = 'Contesthopper <'.get_option('admin_email').'>';

        $headers = 'From: '.$from_email."\r\n";
        
        $res = wp_mail($to, $subject, $message, $headers);
    }
    
    /**
    * Sends double opt-in email to participant.
    * @param CH_Contest $contest
    * @param CH_Participant $participant
    * @param string $url
    */
    static function process_optin_email($contest, $participant, $url)
    {
        if(isset($_GET[$contest->ref_variable]))
            $url = remove_query_arg($contest->ref_variable, $url);
        
        $confirm_url = add_query_arg('contesthopper_confirm', $participant->code, $url);
        
        $to = $participant->email;
        $subject = $contest->ch_double_optin_subject;

        $first_name = isset($participant->first_name) ? $participant->first_name : '';
        $last_name = isset($participant->last_name) ? $participant->last_name : '';

        $subject = str_replace('{FIRST_NAME}', $first_name, $subject);
        $subject = str_replace('{LAST_NAME}', $last_name, $subject);
        
        $message = $contest->ch_double_optin_email;
        $message = str_replace('{URL}', $confirm_url, $message);
        $message = str_replace('{FIRST_NAME}', $first_name, $message);
        $message = str_replace('{LAST_NAME}', $last_name, $message);
        
        $from_email = '';
        if(isset($contest->ch_from_email))
            $from_email = $contest->ch_from_email;
        
        if(empty($from_email))
            $from_email = 'Contesthopper <'.get_option('admin_email').'>';

        $headers = 'From: '.$from_email."\r\n";
        
        $res = wp_mail($to, $subject, $message, $headers);
        
        // TODO add antispam when(if?) resending emails
    }
    
    /**
    * Processes email confirmation when double opt-in is set.
    * @param string $confirm_code
    */
    static function process_optin($confirm_code)
    {
        if(empty($confirm_code))
            return false;
        
        $participant = new CH_Participant($confirm_code, true); // get participant based on the confirmation code
        if(!$participant->_valid)
            return false;
        
        if($participant->status!='not_confirmed') // check participant status, has not be not_confirmed or there is nothing to confirm
            return false;
            
        $contest = new CH_Contest($participant->contest_id); // check if the contest participant is in, is valid
        if(!$contest->_valid)
            return false;
               
        if(isset($participant->tmp_referral)) // if the participant was referred to the contest
        {
            // get referring participant and credit him the referral
            $referral = new CH_Participant($participant->tmp_referral, true);
            if($referral->_valid)
                $referral->add_meta('referral_to', $participant->id); 
                
            $participant->del_meta('tmp_referral');
        }
        
        $participant->set_status(''); // change the participant status
        
        // process stuff
        self::process_email($contest, $participant);
        self::process_integration($contest, $participant);
        self::process_cookie($contest, $participant);        
    }
    
    /**
    * Sets a cookie to remember the participant.
    * @param CH_Contest $contest
    * @param CH_Participant $participant
    */
    static function process_cookie($contest, $participant)
    {       
        $now = current_time('timestamp', 1);
        $end = $contest->get_utc_time();

        $res = setcookie('contesthopper_'.$contest->ID, $participant->code, time()+($end-$now), '/');
    }
    
    /**
    * Processes integration with 3rd party APIs.
    * @param CH_Contest $contest
    * @param CH_Participant $participant
    */
    static function process_integration($contest, $participant)
    {
        // mailing lists
        
        $email = $participant->email;
        $name = '';
        $first_name = '';
        $last_name = '';
        
        if($contest->ch_name_field=='1')
        {
            $name = $participant->first_name.' '.$participant->last_name;
            $first_name = $participant->first_name;
            $last_name = $participant->last_name;
        }
                
        if($contest->ch_participants_export=='campaignmonitor')
        {
             if(!class_exists(CS_REST_Subscribers))
                require(CH_Manager::$plugin_dir.'lib/campaign_monitor/csrest_subscribers.php');
             
             $wrap = new CS_REST_Subscribers($contest->ch_campaignmonitor_list, $contest->ch_campaignmonitor_key);
                          
             $res = $wrap->add(array(
                'EmailAddress' => $email,
                'Name' => $name,
                'Resubscribe' => true                                    
             )); 
             
             // $res->was_successful()
             // $res->http_status_code
             // $res->response        
        }
        else if($contest->ch_participants_export=='mailchimp')
        {
            if(!class_exists(MCAPI))
                require(CH_Manager::$plugin_dir.'lib/MCAPI.class.php');
            
            $double_optin = true;
            if($contest->ch_double_optin=='1')
                $double_optin = false;
            
            $api = new MCAPI($contest->ch_mailchimp_key);
            $api->listSubscribe($contest->ch_mailchimp_list, $email, array('FNAME' => $first_name, 'LNAME' => $last_name), 'html', $double_optin); // double optin;
            
            // if($api->errorCode)
            // $api->errorCode
            // $api->errorMessage
        }
        else if($contest->ch_participants_export=='getresponse')
        {
            require(CH_Manager::$plugin_dir.'lib/GetResponseAPI.class.php');
                
            $api = new GetResponseAPI($contest->ch_getresponse_key);
            $response = $api->addContact($contest->ch_getresponse_list, $name, $email);
            
            // var_dump($response);
        }
        else if($contest->ch_participants_export=='aweber')
        {
            require(CH_Manager::$plugin_dir.'lib/aweber/aweber_api.php');
                        
            $aweber_auth = $contest->ch_aweber_auth;
            if(!empty($aweber_auth) && is_array($aweber_auth))
            {
                $api = new AWeberAPI($aweber_auth['consumer_key'], $aweber_auth['consumer_secret']);
                
                try {
                    $account = $api->getAccount($aweber_auth['access_key'], $aweber_auth['access_secret']);
                    $listURL = "/accounts/{$account->id}/lists/{$contest->ch_aweber_list}";
                    $list = $account->loadFromUrl($listURL);

                    // create a subscriber
                    $params = array(
                        'email' => $email,
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'name' => $name,
                    );
                    $subscribers = $list->subscribers;
                    $new_subscriber = $subscribers->create($params);

                }
                catch(AWeberAPIException $exc) 
                {
                    // TODO log (post comments?)
                }
            }
        }
        
        do_action('ch_process_integration', $contest, $participant);
    }
}
