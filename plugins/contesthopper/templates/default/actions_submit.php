<?php
/**
* Default actions after form submit template called by main widget.php template.
* @package CHTemplate
*/
      
$widget = CH_Widget::current_widget();
$contest = $widget->contest;
$participant = $widget->participant;
$url = $widget->url;
$widget_id = $widget->widget_id;
                             
$url_enc = urldecode($url);
$ref_url = $url;

$out_actions_submit = '';

if($contest->ch_referral_field=='1')
{
    $short_ref = '';
    if(isset($participant->short_ref))
        $short_ref = $participant->short_ref;
        
    if(!empty($short_ref))
        $ref_url = $short_ref;
    else
        $ref_url = add_query_arg('ref', $participant->code, $ref_url);
         
    echo '<div class="social_title">'.__('Earn Extra Entries!', 'contesthopper').'</div><div class="social_message">'.
sprintf(_n('Share the link below and earn %d Entry for each person who enters with your custom link.', 'Share the link below and earn %d Entries for each person who enters with your custom link.', $contest->ch_referral_entries), $contest->ch_referral_entries).'</div>';
}

$social = $contest->ch_social;        

if(is_array($social))
{    
    echo '<div class="social">';

    foreach($social as $s)
    {
        // TODO
        // titles, descriptions, images, ...
        if($s=='googleplus')
        {
            $googleplus_url = urlencode($ref_url);
            $icon = CH_Manager::$plugin_url.'/img/google-plus.png';
            
            echo <<<HTML
<a rel="nofollow" href="http://plusone.google.com/" onclick="popUp=window.open('https://plus.google.com/share?url={$googleplus_url}', 'popupwindow', 'scrollbars=yes,width=800,height=400');popUp.focus();return false"><img src="{$icon}" alt="Google+" /></a>
HTML;
        }
        else if($s=='twitter')
        {
            $twitter_text = str_replace('{URL}', $ref_url, $contest->ch_twitter_text);
            $twitter_text = urlencode($twitter_text);
            $icon = CH_Manager::$plugin_url.'/img/twitter.png';
            
            echo <<<HTML
<a rel="nofollow" href="http://twitter.com/" onclick="popUp=window.open('http://twitter.com/home?status={$twitter_text}', 'popupwindow', 'scrollbars=yes,width=800,height=400');popUp.focus();return false"><img src="{$icon}" alt="Twitter"/></a>
HTML;
        }
        else if($s=='facebook')
        {
            $facebook_url = urlencode($ref_url);
            $facebook_title = urlencode($contest->ch_facebook_title);
            $facebook_image = urlencode($contest->ch_facebook_image);
            $facebook_summary = urlencode($contest->ch_facebook_summary);
            $icon = CH_Manager::$plugin_url.'/img/facebook.png';
            
            echo <<<HTML
<a rel="nofollow" href="http://www.facebook.com/" onclick="popUp=window.open('http://www.facebook.com/sharer.php?s=100&p[url]={$facebook_url}&p[images][0]={$facebook_image}&p[title]={$facebook_title}&p[summary]={$facebook_summary}', 'popupwindow', 'scrollbars=yes,width=800,height=400');popUp.focus();return false"><img src="{$icon}" alt="Facebook" /></a>
HTML;
        }
        else if($s=='pinit')
        {
            $pinterest_url = $url_enc; 
            $pinterest_image = urlencode($contest->ch_pinit_image);
            $pinterest_description = urlencode($contest->ch_pinit_description);
            $icon = CH_Manager::$plugin_url.'/img/pinterest.png';
            
            echo <<<HTML
<a rel="nofollow" href="http://www.pinterest.com/" onclick="popUp=window.open('http://pinterest.com/pin/create/button/?url={$pinterest_url}&media={$pinterest_image}&description={$pinterest_description}', 'popupwindow', 'scrollbars=yes,width=800,height=400');popUp.focus();return false"><img src="{$icon}" alt="Pinterest" /></a>
HTML;
        }
        else if($s=='linkedin')
        {
            $linkedin_url = urlencode($ref_url);
            $linkedin_title = urlencode($contest->ch_linkedin_title);
            $linkedin_summary = urlencode($contest->ch_linkedin_summary);
            $linkedin_source = urlencode($contest->ch_linkedin_source);
            $icon = CH_Manager::$plugin_url.'/img/linkedin.png';
            
            echo <<<HTML
<a rel="nofollow" href="http://www.linkedin.com/" onclick="popUp=window.open('http://www.linkedin.com/shareArticle?mini=true&url={$linkedin_url}&title={$linkedin_title}&summary={$linkedin_summary}&source={$linkedin_source}', 'popupwindow', 'scrollbars=yes,width=800,height=400');popUp.focus();return false" target=”_new”><img src="{$icon}" alt="LinkedIn"/></a>
HTML;
        }
    }

    echo '</div>';
} 

if($contest->ch_referral_field=='1')
{   
    // TOOD move script to footer          
    echo '<div id="d_clip_container-'.$widget_id.'" style="position:relative">
    <div id="d_clip_button-'.$widget_id.'" class="shareurl"><span>'.__('Click to Copy Share URL', 'contesthopper').': <span style="color: #cccccc">'.$ref_url.'</span></span></div>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function() {
          clip = new ZeroClipboard.Client();
          ZeroClipboard.setMoviePath("'.CH_Manager::$plugin_url.'/js/ZeroClipboard.swf");
          clip.setText("'.$ref_url.'");
          clip.setHandCursor( true );
          clip.glue( \'d_clip_button-'.$widget_id.'\', \'d_clip_container-'.$widget_id.'\');
    });
    </script>';
}            
