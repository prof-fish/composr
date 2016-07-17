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
 * @package    securitylogging
 */

/**
 * Module page class.
 */
class Module_admin_lookup
{
    /**
     * Find details of the module.
     *
     * @return ?array Map of module info (null: module is disabled).
     */
    public function info()
    {
        $info = array();
        $info['author'] = 'Chris Graham';
        $info['organisation'] = 'ocProducts';
        $info['hacked_by'] = null;
        $info['hack_version'] = null;
        $info['version'] = 2;
        $info['locked'] = false;
        return $info;
    }

    /**
     * Find entry-points available within this module.
     *
     * @param  boolean $check_perms Whether to check permissions.
     * @param  ?MEMBER $member_id The member to check permissions as (null: current user).
     * @param  boolean $support_crosslinks Whether to allow cross links to other modules (identifiable via a full-page-link rather than a screen-name).
     * @param  boolean $be_deferential Whether to avoid any entry-point (or even return null to disable the page in the Sitemap) if we know another module, or page_group, is going to link to that entry-point. Note that "!" and "browse" entry points are automatically merged with container page nodes (likely called by page-groupings) as appropriate.
     * @return ?array A map of entry points (screen-name=>language-code/string or screen-name=>[language-code/string, icon-theme-image]) (null: disabled).
     */
    public function get_entry_points($check_perms = true, $member_id = null, $support_crosslinks = true, $be_deferential = false)
    {
        if ($be_deferential && get_forum_type() == 'cns') {
            return null;
        }

        return array(
            '!' => array('INVESTIGATE_USER', 'menu/adminzone/tools/users/investigate_user'),
        );
    }

    public $title;
    public $param;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        $type = get_param_string('type', 'browse');

        require_lang('lookup');
        require_lang('submitban');
        require_lang('security');

        set_helper_panel_tutorial('tut_trace');

        set_helper_panel_text(comcode_to_tempcode(do_lang('DOC_INVESTIGATE_USER')));

        $param = get_param_string('param', get_param_string('id', ''));

        if ($param == '') {
            breadcrumb_set_parents(array(array('_SEARCH:admin_cns_members:browse', do_lang_tempcode('MEMBERS'))));
        } else {
            breadcrumb_set_parents(array(array('_SEARCH:admin_cns_members:browse', do_lang_tempcode('MEMBERS')), array('_SELF:_SELF:browse', do_lang_tempcode('INVESTIGATE_USER'))));
            breadcrumb_set_self(do_lang_tempcode('RESULT'));
        }

        if ($param == '') {
            $this->title = get_screen_title('INVESTIGATE_USER');
        } else {
            if (is_numeric($param)) {
                $this->title = get_screen_title('INVESTIGATE_USER_BY_MEMBER_ID');
            } elseif (strpos($param, '.') !== false) {
                $this->title = get_screen_title('INVESTIGATE_USER_BY_IP');
            } else {
                $this->title = get_screen_title('INVESTIGATE_USER_BY_USERNAME');
            }
        }

        $this->param = $param;

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        require_lang('submitban');
        require_code('lookup');

        $param = $this->param;

        if ($param == '') {
            require_code('form_templates');
            $submit_name = do_lang_tempcode('INVESTIGATE_USER');
            $post_url = build_url(array('page' => '_SELF'), '_SELF', null, false, true);
            $fields = form_input_line(do_lang_tempcode('DETAILS'), do_lang_tempcode('DESCRIPTION_INVESTIGATE'), 'param', '', false);

            return do_template('FORM_SCREEN', array('_GUID' => '9cc407037ec01a8f3483746a22889471', 'GET' => true, 'SKIP_WEBSTANDARDS' => true, 'HIDDEN' => '', 'TITLE' => $this->title, 'TEXT' => '', 'SUBMIT_ICON' => 'buttons__proceed', 'SUBMIT_NAME' => $submit_name, 'FIELDS' => $fields, 'URL' => $post_url));
        } else {
            $test = explode(' ', get_param_string('sort', 'date_and_time DESC'), 2);
            if (count($test) == 1) {
                $test[1] = 'DESC';
            }
            list($sortable, $sort_order) = $test;
            $name = mixed();
            $id = mixed();
            $ip = mixed();
            $rows = lookup_member_page($param, $name, $id, $ip);
            if (is_null($name)) {
                $name = do_lang('UNKNOWN');
            }
            if (is_null($id)) {
                $id = $GLOBALS['FORUM_DRIVER']->get_guest_id();
            }
            if (is_null($ip)) {
                $ip = '';
            }

            if (addon_installed('securitylogging')) {
                $all_banned = collapse_1d_complexity('ip', $GLOBALS['SITE_DB']->query('SELECT ip FROM ' . get_table_prefix() . 'banned_ip WHERE i_ban_positive=1 AND (i_ban_until IS NULL OR i_ban_until>' . strval(time()) . ')'));
            } else
            {
                $all_banned = array();
            }

            $ip_list = new Tempcode();
            $groups = array();
            foreach ($rows as $row) {
                if (strpos($row['ip'], ':') !== false) {
                    $bits = explode(':', $row['ip']);
                    $bits[count($bits) - 1] = '*';
                    $ip_masked = implode(':', $bits);
                } else {
                    $bits = explode('.', $row['ip']);
                    $bits[count($bits) - 1] = '*';
                    $ip_masked = implode('.', $bits);
                }
                if (!array_key_exists($ip_masked, $groups)) {
                    $groups[$ip_masked] = array();
                }
                $groups[$ip_masked][] = $row;
            }
            $all_ips = array();
            foreach ($groups as $mask => $group) {
                foreach ($group as $row) {
                    $all_ips[] = $row['ip'];
                }
                $all_ips[] = $mask;
            }
            if (cms_srv('REQUEST_METHOD') == 'POST') {
                if (!array_key_exists('banned', $_POST)) {
                    $_POST['banned'] = array();
                }

                require_code('failure');
                $all_banned_filtered = array();
                foreach ($all_ips as $bip) {
                    if (addon_installed('securitylogging')) {
                        if (in_array($bip, $_POST['banned'])) {
                            add_ip_ban($bip);
                            $all_banned_filtered[] = $bip;
                        } else {
                            remove_ip_ban($bip);
                        }
                    }
                }
                $all_banned = $all_banned_filtered;
            }
            foreach ($groups as $mask => $group) {
                $inner_ip_list = new Tempcode();
                $one_sub_is_banned = false;
                foreach ($group as $row) {
                    $date = get_timezoned_date_time($row['date_and_time']);
                    $lookup_url = build_url(array('page' => '_SELF', 'param' => $row['ip']), '_SELF');
                    $inner_ip_list->attach(do_template('LOOKUP_IP_LIST_ENTRY', array('_GUID' => '94a133f5f711bbf09100346661e3f7c9', 'LOOKUP_URL' => $lookup_url, 'DATE' => $date, '_DATE' => strval($row['date_and_time']), 'IP' => $row['ip'], 'BANNED' => in_array($row['ip'], $all_banned))));
                    if (in_array($row['ip'], $all_banned)) {
                        $one_sub_is_banned = true;
                    }
                }
                $ip_list->attach(do_template('LOOKUP_IP_LIST_GROUP', array('_GUID' => '10612a64654f3a75fca65d089e039e9a', 'OPEN_DEFAULT' => $one_sub_is_banned, 'BANNED' => in_array($mask, $all_banned), 'MASK' => $mask, 'GROUP' => $inner_ip_list)));
            }

            $stats = get_stats_track($id, $ip, get_param_integer('start', 0), get_param_integer('max', 10), $sortable, $sort_order);

            $points_url = addon_installed('points') ? build_url(array('page' => 'points', 'type' => 'member', 'id' => $id), get_module_zone('points')) : null;
            if (addon_installed('authors')) {
                $author_url = ($name == do_lang('UNKNOWN')) ? null : build_url(array('page' => 'authors', 'author' => $name), get_module_zone('authors'));
            } else {
                $author_url = null;
            }
            if (addon_installed('search')) {
                $search_url = ($name == do_lang('UNKNOWN')) ? null : build_url(array('page' => 'search', 'type' => 'results', 'content' => '', 'author' => $name, 'days' => '-1', 'sort' => 'add_date', 'direction' => 'DESC'), get_module_zone('search'));
            } else {
                $search_url = null;
            }
            $profile_url = (is_guest($id)) ? null : $GLOBALS['FORUM_DRIVER']->member_profile_url($id, false, true);
            if (addon_installed('actionlog')) {
                $actionlog_url = (is_guest($id)) ? null : build_url(array('page' => 'admin_actionlog', 'type' => 'list', 'id' => $id), get_module_zone('admin_actionlog'));
            } else {
                $actionlog_url = null;
            }

            if ($ip == '') {
                $alerts = new Tempcode();
            } else {
                list($alerts,) = find_security_alerts(array('ip' => $ip));
            }

            $member_banned = $GLOBALS['FORUM_DRIVER']->is_banned($id);
            $ip_banned = false;
            if ($ip != '' && addon_installed('securitylogging')) {
                $ban_until = $GLOBALS['SITE_DB']->query_select('banned_ip', array('i_ban_until'), array('i_ban_positive' => 1, 'ip' => $ip));
                if (array_key_exists(0, $ban_until)) {
                    $ip_banned = is_null($ban_until[0]['i_ban_until']) || $ban_until[0]['i_ban_until'] > time();
                }
            }
            $banned_test_2 = $GLOBALS['SITE_DB']->query_select_value_if_there('usersubmitban_member', 'the_member', array('the_member' => $id));
            $submitter_banned = !is_null($banned_test_2);

            $member_ban_link = null;
            $ip_ban_link = null;
            $submitter_ban_link = null;
            if (addon_installed('securitylogging')) {
                if (((get_forum_type() == 'cns') && (!is_guest($id))) && ($id != get_member())) {
                    $member_ban_link = do_template('ACTIONLOGS_TOGGLE_LINK', array('_GUID' => '840c361ab217959f8b85141497e6e6a6', 'URL' => build_url(array('page' => 'admin_ip_ban', 'type' => 'toggle_member_ban', 'id' => $id, 'redirect' => get_self_url(true)), get_module_zone('admin_actionlog'))));
                }
                if (($ip != '') && ($ip != get_ip_address()) && ($ip != cms_srv('SERVER_ADDR'))) {
                    $ip_ban_link = do_template('ACTIONLOGS_TOGGLE_LINK', array('_GUID' => '76979d80cdd7d3e664c9a4ec04419bc6', 'URL' => build_url(array('page' => 'admin_ip_ban', 'type' => 'toggle_ip_ban', 'id' => $ip), get_module_zone('admin_actionlog'))));
                }
                if ((!is_guest($id)) && ($id != get_member())) {
                    $submitter_ban_link = do_template('ACTIONLOGS_TOGGLE_LINK', array('_GUID' => '03834262af908bf78c4eef69e78c8cff', 'URL' => build_url(array('page' => 'admin_ip_ban', 'type' => 'toggle_submitter_ban', 'id' => $id, 'redirect' => get_self_url(true)), get_module_zone('admin_actionlog'))));
                }
            }

            $tpl = do_template('LOOKUP_SCREEN', array(
                '_GUID' => 'dc6effaa043949940b809f6aa5a1f944',
                'TITLE' => $this->title,
                'ALERTS' => $alerts,
                'STATS' => $stats,
                'IP_LIST' => $ip_list,
                'IP_BANNED' => $ip_banned ? do_lang_tempcode('YES') : do_lang_tempcode('NO'),
                'SUBMITTER_BANNED' => $submitter_banned ? do_lang_tempcode('YES') : do_lang_tempcode('NO'),
                'MEMBER_BANNED' => $member_banned ? do_lang_tempcode('YES') : do_lang_tempcode('NO'),
                'MEMBER_BAN_LINK' => $member_ban_link,
                'SUBMITTER_BAN_LINK' => $submitter_ban_link,
                'IP_BAN_LINK' => $ip_ban_link,
                'ID' => strval($id),
                'IP' => $ip,
                'NAME' => $name,
                'SEARCH_URL' => $search_url,
                'AUTHOR_URL' => $author_url,
                'POINTS_URL' => $points_url,
                'PROFILE_URL' => $profile_url,
                'ACTIONLOG_URL' => $actionlog_url
            ));

            require_code('templates_internalise_screen');
            return internalise_own_screen($tpl);
        }
    }
}
