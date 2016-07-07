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
 * @package    core_notifications
 */

/**
 * Standard code module initialisation function.
 *
 * @ignore
 */
function init__notification_poller()
{
    define('NOTIFICATION_POLL_FREQUENCY', intval(get_option('notification_poll_frequency')));

    define('NOTIFICATION_POLL_SAFETY_LAG_SECS', 8); // Assume a request could have taken this long to happen, so we look back a little further even than NOTIFICATION_POLL_FREQUENCY
}

/**
 * Notification entry script.
 */
function notification_script()
{
    $type = get_param_string('type');
    switch ($type) {
        case 'mark_all_read':
            notification_mark_all_read_script();
        //break; Intentionally continue on
        case 'poller':
            notification_poller_script();
            break;
        case 'display':
            notification_display_script();
            break;
    }
}

/**
 * Notification entry script.
 */
function notification_mark_all_read_script()
{
    $GLOBALS['SITE_DB']->query_update('digestives_tin', array('d_read' => 1), array('d_read' => 0, 'd_to_member_id' => get_member()));

    decache('_get_notifications', null, get_member());
}

/**
 * Notification entry script.
 */
function notification_display_script()
{
    header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
    header('Content-type: text/plain; charset=' . get_charset());

    $max = post_param_integer('max', null);

    list($tpl,) = get_web_notifications($max);
    $tpl->evaluate_echo();
}

/**
 * Notification entry script.
 */
function notification_poller_script()
{
    $xml = '';

    require_code('xml');

    header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
    header('Content-Type: application/xml');
    $xml .= '<' . '?xml version="1.0" encoding="' . get_charset() . '" ?' . '>
' . get_xml_entities() . '
<response>
    <result>
        <time>' . strval(time()) . '</time>
    ';

    $time_barrier = get_param_integer('time_barrier', time() - NOTIFICATION_POLL_FREQUENCY - NOTIFICATION_POLL_SAFETY_LAG_SECS);

    $max = get_param_integer('max', null);

    $forced_update = (get_param_integer('forced_update', 0) == 1);

    // Notifications

    if (is_guest()) {
        $rows = array();
    } else {
        $query = 'SELECT * FROM ' . get_table_prefix() . 'digestives_tin WHERE d_to_member_id=' . strval(get_member());
        $query .= ' AND d_date_and_time>=' . strval($time_barrier);
        $query .= ' AND d_read=0';
        $query .= ' AND d_frequency=' . strval(A_WEB_NOTIFICATION);
        $rows = $GLOBALS['SITE_DB']->query($query);
    }

    if ((count($rows) > 0) || ($forced_update)) {
        foreach ($rows as $row) {
            $xml .= web_notification_to_xml($row);
        }

        if (!is_null($max)) {
            list($display, $unread) = get_web_notifications($max);
            $xml .= '
                    <display_web_notifications>' . $display->evaluate() . '</display_web_notifications>
                    <unread_web_notifications>' . strval($unread) . '</unread_web_notifications>
            ';
        }

        // Only keep around for X days
        $sql = 'd_frequency=' . strval(A_WEB_NOTIFICATION) . ' AND d_date_and_time<' . strval(time() - 60 * 60 * 24 * intval(get_option('notification_keep_days')));
        $rows = $GLOBALS['SITE_DB']->query('SELECT d_message FROM ' . get_table_prefix() . 'digestives_tin WHERE ' . $sql);
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                delete_lang($row['d_message']);
            }

            $GLOBALS['SITE_DB']->query('DELETE FROM ' . get_table_prefix() . 'digestives_tin WHERE ' . $sql);

            decache('_get_notifications', null, get_member());
        }
    }

    // Private topics

    if (get_forum_type() == 'cns') {
        if (get_option('pt_notifications_as_web') == '0') {
            require_code('cns_notifications');
            $rows = cns_get_pp_rows(null, true, false, $time_barrier);

            if ((count($rows) > 0) || ($forced_update)) {
                foreach ($rows as $row) {
                    $xml .= pt_to_xml($row);
                }

                if (!is_null($max)) {
                    list($display, $unread) = get_pts($max);
                    $xml .= '
                        <display_pts>' . $display->evaluate() . '</display_pts>
                        <unread_pts>' . strval($unread) . '</unread_pts>
                    ';
                }
            }
        }
    }

    $xml .= '
    </result>
</response>
';
    echo $xml;
}

/**
 * Get web notification templating.
 *
 * @param  ?integer $max Number of notifications to show (null: no limit)
 * @param  integer $start Start offset
 * @return array A pair: Templating, Max rows
 */
function get_web_notifications($max = null, $start = 0)
{
    if (is_guest()) {
        return array(new Tempcode(), 0);
    }

    if ($start == 0) {
        $test = get_cache_entry('_get_notifications', serialize(array($max)), CACHE_AGAINST_MEMBER, 10000);
        if ($test !== null) {
            return $test;
        }
    }

    $where = array(
        'd_to_member_id' => get_member(),
        'd_frequency' => A_WEB_NOTIFICATION,
    );

    $rows = $GLOBALS['SITE_DB']->query_select('digestives_tin', array('*'), $where, 'ORDER BY d_date_and_time DESC', $max, $start);
    $out = new Tempcode();
    foreach ($rows as $row) {
        $member_id = $row['d_from_member_id'];
        if ($member_id <= 0) {
            $username = do_lang('SYSTEM');
            $from_url = '';
            $avatar_url = find_theme_image('cns_default_avatars/default');
        } else {
            $username = $GLOBALS['FORUM_DRIVER']->get_username($member_id, true);
            $from_url = $GLOBALS['FORUM_DRIVER']->member_profile_url($member_id, true);
            $avatar_url = $GLOBALS['FORUM_DRIVER']->get_member_avatar_url($member_id);
        }

        $_message = get_translated_tempcode('digestives_tin', $row, 'd_message');

        $url = mixed();
        switch ($row['d_notification_code']) {
            case 'cns_topic':
                if (is_numeric($row['d_code_category'])) { // Straight forward topic notification
                    $url = $GLOBALS['FORUM_DRIVER']->topic_url(intval($row['d_code_category']), '', true);
                }
                break;
        }

        $rendered = do_template('NOTIFICATION_WEB', array(
            '_GUID' => '314db5380aecd610c7ad2a013743f614',
            'ID' => strval($row['id']),
            'SUBJECT' => $row['d_subject'],
            'MESSAGE' => $_message,
            'FROM_USERNAME' => $username,
            'FROM_MEMBER_ID' => strval($member_id),
            'URL' => $url,
            'FROM_URL' => $from_url,
            'FROM_AVATAR_URL' => $avatar_url,
            'PRIORITY' => strval($row['d_priority']),
            'DATE_TIMESTAMP' => strval($row['d_date_and_time']),
            'DATE_WRITTEN_TIME' => get_timezoned_date($row['d_date_and_time']),
            'NOTIFICATION_CODE' => $row['d_notification_code'],
            'CODE_CATEGORY' => $row['d_code_category'],
            'HAS_READ' => ($row['d_read'] == 1),
        ));
        $out->attach($rendered);
    }

    $max_rows = $GLOBALS['SITE_DB']->query_select_value('digestives_tin', 'COUNT(*)', $where + array('d_read' => 0));

    $ret = array($out, $max_rows);

    if ($start == 0) {
        require_code('caches2');
        put_into_cache('_get_notifications', 60 * 60 * 24, serialize(array($max)), null, get_member(), '', is_null(get_bot_type()) ? 0 : 1, get_users_timezone(get_member()), $ret);
    }

    return $ret;
}

/**
 * Get XML for sending a notification to the current user's web browser.
 *
 * @param  array $row Notification row
 * @return string The XML
 */
function web_notification_to_xml($row)
{
    $member_id = $row['d_from_member_id'];
    $username = $GLOBALS['FORUM_DRIVER']->get_username($member_id, true);
    if (is_null($username)) {
        $username = do_lang('UNKNOWN');
    }
    $from_url = $GLOBALS['FORUM_DRIVER']->member_profile_url($member_id, true);
    $avatar_url = $GLOBALS['FORUM_DRIVER']->get_member_avatar_url($member_id);

    $_message = get_translated_tempcode('digestives_tin', $row, 'd_message');

    $rendered = do_template('NOTIFICATION_WEB_DESKTOP', array(
        '_GUID' => '1641fa5c5b62421ae535680859e89636',
        'ID' => strval($row['id']),
        'SUBJECT' => $row['d_subject'],
        'MESSAGE' => $_message,
        'FROM_USERNAME' => $username,
        'FROM_MEMBER_ID' => strval($member_id),
        'FROM_URL' => $from_url,
        'FROM_AVATAR_URL' => $avatar_url,
        'PRIORITY' => strval($row['d_priority']),
        'DATE_TIMESTAMP' => strval($row['d_date_and_time']),
        'DATE_WRITTEN_TIME' => get_timezoned_date($row['d_date_and_time']),
        'NOTIFICATION_CODE' => $row['d_notification_code'],
        'CODE_CATEGORY' => $row['d_code_category'],
    ));

    //sound="' . (($row['d_priority'] < 3) ? 'on' : 'off') . '"
    return '
        <web_notification
            id="' . strval($row['id']) . '"
            subject="' . escape_html($row['d_subject']) . '"
            rendered="' . escape_html($rendered->evaluate()) . '"
            message="' . escape_html(static_evaluate_tempcode($_message)) . '"
            from_username="' . escape_html($username) . '"
            from_member_id="' . escape_html(strval($member_id)) . '"
            from_url="' . escape_html($from_url) . '"
            from_avatar_url="' . escape_html($avatar_url) . '"
            priority="' . escape_html(strval($row['d_priority'])) . '"
            date_timestamp="' . escape_html(strval($row['d_date_and_time'])) . '"
            date_written_time="' . escape_html(get_timezoned_date($row['d_date_and_time'])) . '"
            notification_code="' . escape_html($row['d_notification_code']) . '"
            code_category="' . escape_html($row['d_code_category']) . '"
            sound="on"
        />
    ';
}

/**
 * Get PTs templating.
 *
 * @param  ?integer $max Number of PTs to show (null: no limit)
 * @param  integer $start Start offset
 * @return array A pair: Templating, Max rows
 */
function get_pts($max = null, $start = 0)
{
    if (get_forum_type() != 'cns') {
        return array(new Tempcode(), 0);
    }

    if (is_guest()) {
        return array(new Tempcode(), 0);
    }

    if ($start == 0) {
        $test = get_cache_entry('_get_pts', serialize(array($max)), CACHE_AGAINST_MEMBER, 10000);
        if ($test !== null) {
            return $test;
        }
    }

    cns_require_all_forum_stuff();

    require_code('cns_notifications');
    $rows = cns_get_pp_rows($max, false, false);
    $max_rows = count(cns_get_pp_rows(intval(get_option('general_safety_listing_limit')), true, false));

    $out = new Tempcode();
    foreach ($rows as $i => $topic) {
        $topic_url = build_url(array('page' => 'topicview', 'id' => $topic['t_id']), get_module_zone('topicview'));
        $title = $topic['t_cache_first_title'];
        $date = get_timezoned_date($topic['t_cache_last_time'], true);
        $num_posts = $topic['t_cache_num_posts'];

        $last_post_by_username = $topic['t_cache_last_username'];
        $last_post_by_member_url = $GLOBALS['CNS_DRIVER']->member_profile_url($topic['t_cache_last_member_id'], false, true);

        $with_poster_id = ($topic['t_pt_from'] == get_member()) ? $topic['t_pt_to'] : $topic['t_pt_from'];
        $with_username = $GLOBALS['FORUM_DRIVER']->get_username($with_poster_id);
        $with_member_url = $GLOBALS['CNS_DRIVER']->member_profile_url($with_poster_id, false, true);

        $is_unread = ($topic['t_cache_last_time'] > time() - 60 * 60 * 24 * intval(get_option('post_read_history_days'))) && ((is_null($topic['l_time'])) || ($topic['l_time'] < $topic['p_time']));

        $out->attach(do_template('CNS_PRIVATE_TOPIC_LINK', array(
            '_GUID' => '6a36e785b05d10f53e7ee76acdfb9f80',
            'TOPIC_URL' => $topic_url,
            'TITLE' => $title,
            'DATE' => $date,
            'DATE_RAW' => strval($topic['t_cache_last_time']),
            'LAST_POST_BY_POSTER_URL' => $last_post_by_member_url,
            'LAST_POST_BY_USERNAME' => $last_post_by_username,
            'LAST_POST_BY_POSTER_ID' => strval($topic['t_cache_last_member_id']),
            'WITH_POSTER_URL' => $with_member_url,
            'WITH_USERNAME' => $with_username,
            'WITH_POSTER_ID' => strval($with_poster_id),
            'NUM_POSTS' => integer_format($num_posts),
            'HAS_READ' => !$is_unread,
        )));

        if ($i === $max) {
            break;
        }
    }

    $ret = array($out, $max_rows);

    if ($start == 0) {
        require_code('caches2');
        put_into_cache('_get_pts', 60 * 60 * 24, serialize(array($max)), null, get_member(), '', is_null(get_bot_type()) ? 0 : 1, get_users_timezone(get_member()), $ret);
    }

    return $ret;
}

/**
 * Get XML for sending a PT alert to the current user's web browser.
 *
 * @param  array $row Notification row
 * @return string The XML
 */
function pt_to_xml($row)
{
    $member_id = $row['p_poster'];
    $username = $GLOBALS['FORUM_DRIVER']->get_username($member_id, true);
    $url = $GLOBALS['FORUM_DRIVER']->member_profile_url($member_id, true);
    $avatar_url = $GLOBALS['FORUM_DRIVER']->get_member_avatar_url($member_id);

    $just_post_row = db_map_restrict($row, array('id', 'p_post'));
    $_message = get_translated_tempcode('f_posts', $just_post_row, 'p_post', $GLOBALS['SITE_DB']);

    $rendered = do_template('NOTIFICATION_PT_DESKTOP', array(
        '_GUID' => '624df70cf0cbb796c5d5ce1d18ae39f7',
        'ID' => strval($row['p_id']),
        'SUBJECT' => $row['t_cache_first_title'],
        'MESSAGE' => $_message,
        'FROM_USERNAME' => $username,
        'FROM_MEMBER_ID' => strval($member_id),
        'URL' => $url,
        'FROM_AVATAR_URL' => $avatar_url,
        'DATE_TIMESTAMP' => strval($row['p_time']),
        'DATE_WRITTEN_TIME' => get_timezoned_date($row['p_time']),
    ));

    return '
        <pt
            id="' . strval($row['p_id']) . '"
            subject="' . escape_html($row['t_cache_first_title']) . '"
            rendered="' . escape_html($rendered->evaluate()) . '"
            message="' . escape_html(static_evaluate_tempcode($_message)) . '"
            from_username="' . escape_html($username) . '"
            from_member_id="' . escape_html(strval($member_id)) . '"
            url="' . escape_html($url) . '"
            from_avatar_url="' . escape_html($avatar_url) . '"
            date_timestamp="' . escape_html(strval($row['p_time'])) . '"
            date_written_time="' . escape_html(get_timezoned_date($row['p_time'])) . '"
            sound="on"
        />
    ';
}
