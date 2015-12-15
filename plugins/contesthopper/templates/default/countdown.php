<?php
/**
* Default countdown template called by main widget.php template.
* @package CHTemplate
*/

$widget = CH_Widget::current_widget();
$widget_id = $widget->widget_id;
$time = $widget->contest->get_utc_time()-(int)current_time('timestamp', 1);

echo <<<HTML
<div class="ch_countdown">
    <span class="ch_countdown_days"></span><span class="ch_countdown_hours"></span><span class="ch_countdown_minutes"></span><span class="ch_countdown_seconds"></span><span class="sh">remaining</span>
    <div class="ch_clear"></div>
</div>
        
<script type="text/javascript">
jQuery(document).ready(function() {   
    ch_ctdn('{$time}', '{$widget_id}');
});
</script>
HTML;
