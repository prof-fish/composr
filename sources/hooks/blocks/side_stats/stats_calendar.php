<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2016

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    calendar
 */

/**
 * Hook class.
 */
class Hook_stats_calendar
{
    /**
     * Show a stats section.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        if (!addon_installed('calendar')) {
            return new Tempcode();
        }

        require_lang('calendar');

        $bits = new Tempcode();
        if (get_option('calendar_show_stats_count_events') == '1') {
            $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE', array('_GUID' => 'bf4ae0b77a8ee8bef42adb8d7beb3884', 'KEY' => do_lang_tempcode('EVENTS'), 'VALUE' => integer_format($GLOBALS['SITE_DB']->query_select_value('calendar_events', 'COUNT(*)')))));
        }
        if (get_option('calendar_show_stats_count_events_this_week') == '1') {
            require_code('calendar');
            $events = calendar_matches($GLOBALS['FORUM_DRIVER']->get_guest_id(), $GLOBALS['FORUM_DRIVER']->get_guest_id(), true, utctime_to_usertime(time()), utctime_to_usertime(time() + 60 * 60 * 24 * 7));
            $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE', array('_GUID' => '315d49be79dddfe1019c02939d308632', 'KEY' => do_lang_tempcode('EVENTS_THIS_WEEK'), 'VALUE' => integer_format(count($events)))));
        }
        if (get_option('calendar_show_stats_count_events_this_month') == '1') {
            require_code('calendar');
            $events = calendar_matches($GLOBALS['FORUM_DRIVER']->get_guest_id(), $GLOBALS['FORUM_DRIVER']->get_guest_id(), true, utctime_to_usertime(time()), utctime_to_usertime(time() + 60 * 60 * 24 * 31));
            $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE', array('_GUID' => 'c3a3ad0d6ae8e4f98ac3a5d0ceabc841', 'KEY' => do_lang_tempcode('EVENTS_THIS_MONTH'), 'VALUE' => integer_format(count($events)))));
        }
        if (get_option('calendar_show_stats_count_events_this_year') == '1') {
            require_code('calendar');
            $events = calendar_matches($GLOBALS['FORUM_DRIVER']->get_guest_id(), $GLOBALS['FORUM_DRIVER']->get_guest_id(), true, utctime_to_usertime(time()), utctime_to_usertime(time() + 60 * 60 * 24 * 365));
            $bits->attach(do_template('BLOCK_SIDE_STATS_SUBLINE', array('_GUID' => 'f77394adef0febff55cbfc288f979408', 'KEY' => do_lang_tempcode('EVENTS_THIS_YEAR'), 'VALUE' => integer_format(count($events)))));
        }

        if ($bits->is_empty_shell()) {
            return new Tempcode();
        }

        $section = do_template('BLOCK_SIDE_STATS_SECTION', array('_GUID' => 'ff9667093f093bec44a7be5e97bf183c', 'SECTION' => do_lang_tempcode('CALENDAR'), 'CONTENT' => $bits));

        return $section;
    }
}
