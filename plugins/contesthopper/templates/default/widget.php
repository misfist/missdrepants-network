<?php
/**
* Default core template file called automatically by CH_Widget::html().
* @package CHTemplate
*/

$widget = CH_Widget::current_widget();
$contest = $widget->contest;
$participant = $widget->participant;
$url = $widget->url;
$ref = $widget->ref;
$widget_id = $widget->widget_id;

// fonts
//***********
$typekit_id = $contest->ch_typekit;

$headline_font = '';
$description_font = '';

// adobe typekit
if(!empty($typekit_id))
{
    wp_enqueue_script('ch_js_typekit_remote', 'http://use.typekit.net/'.esc_attr($typekit_id).'.js');
    wp_enqueue_script('ch_js_typekit', CH_Manager::$plugin_url.'/js/ch_typekit.js');
}
else // google or default fonts
{
    $fonts = $widget->prepare_font(); // TODO location of font helper functions does not make much sense
    $google_style = $widget->google_style($fonts);
    
    if(!empty($google_style))
        wp_enqueue_style('ch_google_fonts_'.$contest->ID, $google_style);

    $headline_font = ' font-family: \''.esc_attr($fonts['headline']['font']).'\', sans-serif;';
    $description_font =  ' font-family: \''.esc_attr($fonts['description']['font']).'\', serif;';
}

// max widget size
//**********
$widget_size = $contest->ch_widget_size;
if(!empty($widget_size) && is_numeric($widget_size))
    $widget_size = ' max-width: '.$widget_size.'px;';

// widget container
//***********
$widget_container = false;
if($contest->ch_container=='1')
    $widget_container = true;

// colors
//***********
$bg_title_color = esc_attr($contest->ch_title_background_color);
$bg_color = esc_attr($contest->ch_background_color);
$border_color = esc_attr($contest->ch_border_color);
$headline_color = esc_attr($contest->ch_headline_color);
$description_color = esc_attr($contest->ch_description_color);

// description and media layout
//**********
$layout = $contest->ch_media_description_layout;
if(!in_array($layout, array('media-top', 'description-top', 'inline_media-left', 'inline_description-left')))
    $layout = 'inline_description-left';   

$media_css = 'ch_media';
$description_css = 'ch_description';

$override_layout = false;
$description_text = $contest->ch_description;
if(empty($description_text) || !in_array($contest->ch_media, array('image', 'video', 'video_youtube')))
    $override_layout = true;

if($layout=='inline_media-left' && !$override_layout)
{
    $media_css .= ' inline float_left';
    $description_css .= ' inline float_right';
}   
else if($layout=='inline_description-left' && !override_layout)
{
    $media_css .= ' inline float_right';
    $description_css .= ' inline float_left';
}

// description text align
//***********
$description_align = ' text-align: center;';
if($contest->ch_description_align=='left')
    $description_align = ' text-align: left;';
else if($contest->ch_description_align=='right')
    $description_align = ' text-align: right;';

// widget html code start
//**********

// head, title
echo '<div id="'.$widget_id.'" class="ch_widget large" style="'.$widget_size.'background-color: '.$bg_color.'">
<div class="ch_widget-inside" style="border-color: '.$border_color.'">
<div class="ch_title" style="border-bottom-color: '.$border_color.'; background-color: '.$bg_title_color.'; color: '.$headline_color.';'.$headline_font.'">
<img src="'.CH_Manager::$plugin_url.'/img/trophy.png" alt="Trophy"/>'.esc_html($contest->ch_headline).'
</div>';

// boxes
echo CH_Widget::get_template('boxes');

// media, description
$media_data = CH_Widget::get_template('media');
$media = '<div class="'.$media_css.'">'.$media_data.'</div>';
$description = '<div class="'.$description_css.'" style="color: '.$description_color.';'.$description_align.$description_font.'">'.apply_filters('ch_description', $description_text).'</div>';

if($layout=='description-top')
    echo $description.$media;
else // media-top or others
    echo $media.$description;

echo '<div class="ch_actions ch_clear" style="border-top-color: '.$border_color.'">
    <div class="ch_actions_inner">';

if(!$contest->is_expired() && $contest->is_started()) // if the contest is active
{
    if(!empty($participant) && $participant->status!='not_confirmed')
        echo CH_Widget::get_template('actions_submit');
    else if(!empty($participant) && $participant->status=='not_confirmed')
        echo CH_Widget::get_template('double_optin');
    else
        echo CH_Widget::get_template('actions');
            
    if($contest->ch_countdown_field=='1')
        echo CH_Widget::get_template('countdown');
    
    if(!empty($participant) && $participant->status!='not_confirmed')
        echo '<div class="ch_contact_message">'.__('Winner(s) will be contacted by email.', 'contesthopper').'</div>';
}
else if(!$contest->is_started())
    echo '<div class="ch_error">'.__('Contest has not yet started.', 'contesthopper').'</div>';
else
    echo '<div class="ch_error">'.__('This contest expired.', 'contesthopper').'</div>';
 
echo '</div></div>
<div class="ch_footer">
        <span class="ch_rules_disclaimer">';

if($contest->ch_disclaimer_rules_type!='none')
{
    if($contest->ch_disclaimer_rules_type=='popup')
        echo '<a href="#" class="ch_rules_disclaimer_link" id="'.$widget_id.'_dialog_link">';
    else if($contest->ch_disclaimer_rules_type=='url')
        echo '<a href="'.$contest->ch_disclaimer_rules_url.'" target="_blank">';

    echo __('Official Rules', 'contesthopper').'</a>';
}
echo '</span>
        <span class="ch_poweredby">'.__('Powered by', 'contesthopper').' ContestHopper</span>
        <div class="ch_clear"></div>
    </div>
</div>
';

if($contest->ch_disclaimer_rules_type=='popup')
{
    echo '
    <div id="'.$widget_id.'_dialog" class="ch_rules_disclaimer_wrap">
        <div class="ch_rules_disclaimer_dialog">
            <span class="dialog_close"><u>close</u></span>
            <h2>'.__('Contest Rules & Disclaimer', 'contesthopper').'</h2>
            <p>'.nl2br(esc_html($contest->ch_rules)).'</p><p>'.nl2br(esc_html($contest->ch_disclaimer)).'</p>
        </div>
    </div>';
}

echo '</div>';
