<?php
/**
* Default actions template called by main widget.php template.
* @package CHTemplate
*/

$widget = CH_Widget::current_widget();
$contest = $widget->contest;
$participant = $widget->participant;
$url = $widget->url;
$widget_id = $widget->widget_id;
$widget_errors = $widget->error;
$ref = isset($_GET[$contest->ref_variable]) ? $_GET[$contest->ref_variable] : '';

echo '<form id="'.$widget_id.'_form" method="post">';
echo '<input type="hidden" name="contest_id" class="ch_contest_id" value="'.$contest->ID.'" /><input type="hidden" name="ch_ref" class="ch_ref" value="'.esc_attr($ref).'" /><input type="hidden" name="url" class="ch_url" value="'.$url.'" />';

// first/last name field     
if($contest->ch_name_field=='1') 
{
    $first_name_css = 'first_name';
    $last_name_css = 'last_name';
    
    if($contest->ch_name_field_req=='1')
    {
        $first_name_css .= ' ch_required';
        $last_name_css .= ' ch_required';
    }   
   
    if(!empty($widget_errors['first_name']))
        $first_name_css .= ' error_req';
    
    if(!empty($widget_errors['last_name']))
        $last_name_css .= ' error_req';    
    
    echo '<div class="names"><div class="ch_first_name"><input type="text" class="'.$first_name_css.'" name="first_name" placeholder="First name" /></div>
<div class="ch_last_name"><input type="text" class="'.$last_name_css.'" name="last_name" placeholder="Last name" /></div><div class="ch_clear"></div></div>';
}

$email_css = 'ch_email';
if(!empty($widget_errors['email']))
    $email_css .= ' error_req';
    
echo '<div><input type="text" class="'.$email_css.'" name="email" placeholder="email@address.com" /></div><div class="ch_submit_div"><input type="submit" class="ch_submit" value="'.esc_attr($contest->ch_submit_text).'" /></div></form>';
