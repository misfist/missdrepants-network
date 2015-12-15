<?php
/**
* Default boxes (number of participant entries, number of all entries, days remaining) template called by main widget.php template.
* @package CHTemplate
*/

$widget = CH_Widget::current_widget();
$contest = $widget->contest;
$participant = $widget->participant;

// entries
$referrals = 0;
$referrals_val = $contest->ch_referral_entries;

$entries = 0;
if(!empty($participant))
{
    $referrals = $participant->get_referral_num();
    $entries = 1 + $referrals*$referrals_val; 
}

// total entries
$total_entries = $contest->get_all_entries();

// days left
$sec_left = $widget->contest->get_utc_time()-(int)current_time('timestamp', 1);
if(!is_numeric($sec_left))
    $sec_left = 0;

$days_left = ceil($sec_left/86400);
if($days_left<0)
    $days_left = 0;

if($days_left==0)
    $days_left = __('Ended', 'contesthopper');
else if($days_left==1)
    $days_left = __('Last day', 'contesthopper');

$your_entries_str = __('Your Entries', 'contesthopper');
$total_entries_str = __('Total Entries', 'contesthopper');
$days_left_str = __('Days Left', 'contesthopper');

echo <<<HTML
<div class="boxes">
    <div class="counts blue">
        {$your_entries_str}
        <div class="entries">{$entries}</div>
    </div>
    <div class="counts blue">
        {$total_entries_str}
        <div class="total_entries">{$total_entries}</div>
    </div>
    <div class="counts red">
        {$days_left_str}
        <div class="num">{$days_left}</div>
    </div>
</div>
HTML;
