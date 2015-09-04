<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2015

 See text/EN/licence.txt for full licencing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core_cns
 */

/**
 * Standard code module initialisation function.
 * @ignore
 */
function init__cns_posts_action()
{
    global $ALL_FORUM_POST_COUNT_INFO_CACHE;
    $ALL_FORUM_POST_COUNT_INFO_CACHE = null;
}

/**
 * Get a list of post templates that apply to a certain forum.
 *
 * @param  AUTO_LINK $forum_id The ID of the forum.
 * @return array The list of applicable post templates.
 */
function cns_get_post_templates($forum_id)
{
    if (!addon_installed('cns_post_templates')) {
        return array();
    }

    $all_templates = $GLOBALS['FORUM_DB']->query_select('f_post_templates', array('*'));
    $apply = array();
    foreach ($all_templates as $template) {
        require_code('selectcode');
        $idlist = selectcode_to_idlist_using_db($template['t_forum_multi_code'], 'id', 'f_forums', 'f_forums', 'f_parent_forum', 'f_parent_forum', 'id', true, true, $GLOBALS['FORUM_DB']);
        if (in_array($forum_id, $idlist)) {
            if (strpos($template['t_text'], '{') !== false) {
                require_code('tempcode_compiler');
                $e = template_to_tempcode($template['t_text']);
                $template['t_text'] = $e->evaluate();
            }
            $apply[] = array($template['t_title'], $template['t_text'], $template['t_use_default_forums']);
        }
    }
    return $apply;
}

/**
 * Check a post would be valid.
 *
 * @param  LONG_TEXT $post The post.
 * @param  ?AUTO_LINK $topic_id The ID of the topic the post would be in (null: don't check with regard to any particular topic).
 * @param  ?MEMBER $poster The poster (null: current member).
 * @return ?array Row of the existing post if a double post (single row map-element in a list of rows) (null: not a double post).
 */
function cns_check_post($post, $topic_id = null, $poster = null)
{
    if (is_null($poster)) {
        $poster = get_member();
    }

    require_code('comcode_check');
    check_comcode($post, null, false, null, true);

    if (strlen($post) == 0) {
        warn_exit(do_lang_tempcode('POST_TOO_SHORT'));
    }
    require_code('cns_groups');
    if (strlen($post) > cns_get_member_best_group_property($poster, 'max_post_length_comcode')) {
        warn_exit(make_string_tempcode(escape_html(do_lang('_POST_TOO_LONG'))));
    }

    if (!is_null($topic_id)) {
        if (running_script('stress_test_loader')) {
            return null;
        }

        // Check this isn't the same as the last post here
        $last_posts = $GLOBALS['FORUM_DB']->query_select('f_posts', array('p_post', 'p_poster', 'p_ip_address'), array('p_topic_id' => $topic_id), 'ORDER BY p_time DESC,id DESC', 1);
        if (array_key_exists(0, $last_posts)) {
            if (($last_posts[0]['p_poster'] == $GLOBALS['CNS_DRIVER']->get_guest_id()) && (get_ip_address() != $last_posts[0]['p_ip_address'])) {
                $last_posts[0]['p_poster'] = -1;
            }
            if (($last_posts[0]['p_poster'] == $poster) && (get_translated_text($last_posts[0]['p_post'], $GLOBALS['FORUM_DB']) == $post) && (get_param_integer('keep_debug_notifications', 0) != 1)) {
                warn_exit(do_lang_tempcode('DOUBLE_POST_PREVENTED'));
            }
        }

        return $last_posts;
    }
    return null;
}

/**
 * Add a post.
 *
 * @param  AUTO_LINK $topic_id The ID of the topic to add the post to.
 * @param  SHORT_TEXT $title The title of the post (may be blank).
 * @param  LONG_TEXT $post The post.
 * @param  BINARY $skip_sig Whether to skip showing the posters signature in the post.
 * @param  ?boolean $is_starter Whether the post is the first in the topic (null: work it out).
 * @param  ?BINARY $validated Whether the post is validated (null: unknown, find whether it needs to be marked unvalidated initially).
 * @param  BINARY $is_emphasised Whether the post is marked emphasised.
 * @param  ?string $poster_name_if_guest The name of the person making the post (null: username of current member).
 * @param  ?IP $ip_address The IP address the post is to be made under (null: IP of current user).
 * @param  ?TIME $time The time of the post (null: now).
 * @param  ?MEMBER $poster The poster (null: current member).
 * @param  ?MEMBER $intended_solely_for The member that this post is intended solely for (null: public).
 * @param  ?TIME $last_edit_time The last edit time of the post (null: never edited).
 * @param  ?MEMBER $last_edit_by The member that was last to edit the post (null: never edited).
 * @param  boolean $check_permissions Whether to check permissions for whether the post may be made as it is given.
 * @param  boolean $update_caching Whether to update the caches after making the post.
 * @param  ?AUTO_LINK $forum_id The forum the post will be in (null: find out from the DB).
 * @param  boolean $support_attachments Whether to allow attachments in this post.
 * @param  ?string $topic_title The title of the topic (null: find from the DB).
 * @param  BINARY $sunk Whether the topic is a sunk topic.
 * @param  ?AUTO_LINK $id Force an ID (null: don't force an ID)
 * @param  boolean $anonymous Whether to make the post anonymous
 * @param  boolean $skip_post_checks Whether to skip post checks
 * @param  ?boolean $is_pt Whether this is for a new Private Topic (null: work it out)
 * @param  boolean $insert_comcode_as_admin Whether to explicitly insert the Comcode with admin privileges
 * @param  ?AUTO_LINK $parent_id Parent post ID (null: none-threaded/root-of-thread)
 * @param  boolean $send_notification Whether to send out notifications
 * @return AUTO_LINK The ID of the new post.
 */
function cns_make_post($topic_id, $title, $post, $skip_sig = 0, $is_starter = false, $validated = null, $is_emphasised = 0, $poster_name_if_guest = null, $ip_address = null, $time = null, $poster = null, $intended_solely_for = null, $last_edit_time = null, $last_edit_by = null, $check_permissions = true, $update_caching = true, $forum_id = null, $support_attachments = true, $topic_title = '', $sunk = 0, $id = null, $anonymous = false, $skip_post_checks = false, $is_pt = false, $insert_comcode_as_admin = false, $parent_id = null, $send_notification = true)
{
    cms_profile_start_for('cns_make_post');

    require_code('cns_topics');
    require_code('cns_posts');

    if (is_null($poster)) {
        $poster = get_member();
    }

    if (is_null($is_starter)) {
        $is_starter = is_null($GLOBALS['FORUM_DB']->query_select_value_if_there('f_posts', 'id', array('p_topic_id' => $topic_id)));
    }
    if (is_null($is_pt)) {
        $is_pt = false;
        if ($is_starter) {
            $is_pt = is_null($GLOBALS['FORUM_DB']->query_select_value_if_there('f_topics', 't_forum_id', array('id' => $topic_id)));
        }
    }

    if (!running_script('install')) {
        require_code('antispam');
        inject_action_spamcheck($poster_name_if_guest, get_param_string('email', null));
    }

    if ($check_permissions) {
        if (strlen($title) > 120) {
            warn_exit(do_lang_tempcode('TITLE_TOO_LONG'));
        }

        if ((is_null($intended_solely_for)) && (!$skip_post_checks)) {
            cms_profile_start_for('cns_make_post:cns_check_post');
            cns_check_post($post, $topic_id, $poster);
            cms_profile_end_for('cns_make_post:cns_check_post');
        }
    }

    if (is_null($ip_address)) {
        $ip_address = get_ip_address();
    }
    if (is_null($time)) {
        $time = time();
    }
    if (is_null($poster_name_if_guest)) {
        if (($poster == $GLOBALS['CNS_DRIVER']->get_guest_id()) || ($anonymous)) {
            $poster_name_if_guest = do_lang('GUEST');
        } else {
            $poster_name_if_guest = $GLOBALS['CNS_DRIVER']->get_username($poster, true);
            if (is_null($poster_name_if_guest)) {
                $poster_name_if_guest = do_lang('UNKNOWN');
            }
        }
    }

    if ((is_null($forum_id)) || (($topic_title == '') && (!$is_starter))) {
        $info = $GLOBALS['FORUM_DB']->query_select('f_topics', array('t_is_open', 't_pt_from', 't_pt_to', 't_forum_id', 't_cache_last_member_id', 't_cache_first_title'), array('id' => $topic_id), '', 1);
        if (!array_key_exists(0, $info)) {
            warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
        }
        $forum_id = $info[0]['t_forum_id'];
        $topic_title = $info[0]['t_cache_first_title'];
        if ($topic_title == '') {
            $topic_title = $title;
        }

        if ($check_permissions) {
            if (((($info[0]['t_pt_from'] != $poster) && ($info[0]['t_pt_to'] != $poster) && (!cns_has_special_pt_access($topic_id))) && (!has_privilege($poster, 'view_other_pt')) && (is_null($forum_id)))) {
                access_denied('I_ERROR');
            }
        }
    }
    if (is_null($forum_id)) {
        if (($check_permissions) && ($poster == $GLOBALS['CNS_DRIVER']->get_guest_id())) {
            access_denied('I_ERROR');
        }
        $validated = 1; // Personal posts always validated
    } else {
        if ($check_permissions) {
            $last_member_id = $is_starter ? null : $info[0]['t_cache_last_member_id'];
            $closed = $is_starter ? false : ($info[0]['t_is_open'] == 0);
            if ((!cns_may_post_in_topic($forum_id, $topic_id, $last_member_id, $closed, $poster, !is_null($intended_solely_for))) && (!$is_starter)) {
                access_denied('I_ERROR');
            }
        }
    }

    // Ensure parent post is from the same topic
    if (!is_null($parent_id)) {
        $test_topic_id = $GLOBALS['FORUM_DB']->query_select_value_if_there('f_posts', 'p_topic_id', array('id' => $parent_id), ' AND ' . cns_get_topic_where($topic_id, $poster));
        if (is_null($test_topic_id)) {
            $parent_id = null;
        }
    }

    if ((is_null($validated)) || (($validated == 1) && ($check_permissions))) {
        if ((!is_null($forum_id)) && (!has_privilege($poster, 'bypass_validation_lowrange_content', 'topics', array('forums', $forum_id)))) {
            $validated = 0;
        } else {
            $validated = 1;
        }
    }

    if (!addon_installed('unvalidated')) {
        $validated = 1;
    }
    $map = array(
        'p_title' => substr($title, 0, 255),
        'p_ip_address' => $ip_address,
        'p_time' => $time,
        'p_poster' => $anonymous ? db_get_first_id() : $poster,
        'p_poster_name_if_guest' => substr($poster_name_if_guest, 0, 80),
        'p_validated' => $validated,
        'p_topic_id' => $topic_id,
        'p_is_emphasised' => $is_emphasised,
        'p_cache_forum_id' => $forum_id,
        'p_last_edit_time' => $last_edit_time,
        'p_last_edit_by' => $last_edit_by,
        'p_intended_solely_for' => $intended_solely_for,
        'p_skip_sig' => $skip_sig,
        'p_parent_id' => $parent_id
    );
    if (!is_null($id)) {
        $map['id'] = $id;
    }

    if (!$support_attachments) {
        cms_profile_start_for('cns_make_post:insert_lang_comcode');
        $map += insert_lang_comcode('p_post', $post, 4, $GLOBALS['FORUM_DB'], $insert_comcode_as_admin);
        cms_profile_end_for('cns_make_post:insert_lang_comcode');
    } else {
        @ignore_user_abort(true);

        if (multi_lang_content()) {
            $map['p_post'] = 0;
        } else {
            $map['p_post'] = '';
            $map['p_post__text_parsed'] = '';
            $map['p_post__source_user'] = db_get_first_id();
        }
    }

    $post_id = $GLOBALS['FORUM_DB']->query_insert('f_posts', $map, true);

    if ($support_attachments) {
        require_code('attachments2');
        cms_profile_start_for('cns_make_post:insert_lang_comcode_attachments');
        $map = insert_lang_comcode_attachments('p_post', 4, $post, 'cns_post', strval($post_id), $GLOBALS['FORUM_DB'], false, $poster) + $map;
        $GLOBALS['FORUM_DB']->query_update('f_posts', $map, array('id' => $post_id), '', 1);
        cms_profile_end_for('cns_make_post:insert_lang_comcode_attachments');
    }

    @ignore_user_abort(false);

    $_url = build_url(array('page' => 'topicview', 'type' => 'findpost', 'id' => $post_id), 'forum', null, false, false, true, 'post_' . strval($post_id));
    $url = $_url->evaluate();
    if ($validated == 0) {
        if ($check_permissions) {
            // send_validation_mail is used for other content - but forum is special
            $subject = do_lang('POST_REQUIRING_VALIDATION_MAIL_SUBJECT', $topic_title, null, null, get_site_default_lang());
            $post_text = get_translated_text($map['p_post'], $GLOBALS['FORUM_DB'], get_site_default_lang());
            $mail = do_lang('POST_REQUIRING_VALIDATION_MAIL', comcode_escape($url), comcode_escape($poster_name_if_guest), array($post_text, strval($anonymous ? db_get_first_id() : $poster)));
            require_code('notifications');
            dispatch_notification('needs_validation', null, $subject, $mail, null, $poster, 3, false, false, null, null, '', '', '', '', null, true);
        }
    } else {
        if ($send_notification) {
            $post_comcode = get_translated_text($map['p_post'], $GLOBALS['FORUM_DB']);

            require_code('cns_posts_action2');
            cms_profile_start_for('cns_make_post:cns_send_topic_notification');
            cns_send_topic_notification($url, $topic_id, $forum_id, $anonymous ? db_get_first_id() : $poster, $is_starter, $post_comcode, $topic_title, $intended_solely_for, $is_pt);
            cms_profile_end_for('cns_make_post:cns_send_topic_notification');

            // Send a notification for the inline PP
            if (!is_null($intended_solely_for)) {
                require_code('notifications');
                $msubject = do_lang('NEW_PERSONAL_POST_SUBJECT', $topic_title, null, null, get_lang($intended_solely_for));
                $mmessage = do_lang('NEW_PERSONAL_POST_MESSAGE', comcode_escape($GLOBALS['FORUM_DRIVER']->get_username($anonymous ? db_get_first_id() : $poster, true)), comcode_escape($topic_title), array(comcode_escape($url), $post_comcode, strval($anonymous ? db_get_first_id() : $poster)), get_lang($intended_solely_for));
                dispatch_notification('cns_new_pt', null, $msubject, $mmessage, array($intended_solely_for), $anonymous ? db_get_first_id() : $poster);
            }
        }
    }

    if ($check_permissions) { // Not automated, so we'll have to be doing run-time progressing too
        // Is the user gonna automatically enable notifications for this?
        $auto_monitor_contrib_content = $GLOBALS['CNS_DRIVER']->get_member_row_field($poster, 'm_auto_monitor_contrib_content');
        if ($auto_monitor_contrib_content == 1) {
            require_code('notifications');
            cms_profile_start_for('cns_make_post:enable_notifications');
            enable_notifications('cns_topic', strval($topic_id), $poster);
            cms_profile_end_for('cns_make_post:enable_notifications');
        }
    }

    if ($update_caching) {
        if (function_exists('get_member')) {
            if (function_exists('cns_ping_topic_read')) {
                cms_profile_start_for('cns_make_post:cns_ping_topic_read');
                cns_ping_topic_read($topic_id, $poster);
                cms_profile_end_for('cns_make_post:cns_ping_topic_read');
            }

            if (is_null($forum_id)) {
                $with = $info[0]['t_pt_from'];
                if ($with == $poster) {
                    $with = $info[0]['t_pt_to'];
                }

                decache(array(
                    array('side_cns_personal_topics', array($with)),
                    array('_new_pp', array($with)),
                ));
            }
        }

        if (is_null($intended_solely_for)) {
            if ($validated == 1) {
                require_code('cns_posts_action2');
                cms_profile_start_for('cns_make_post:cns_force_update_topic_caching');
                cns_force_update_topic_caching($topic_id, 1, true, $is_starter, $post_id, $time, $title, $map['p_post'], $poster_name_if_guest, $poster);
                cms_profile_end_for('cns_make_post:cns_force_update_topic_caching');
            }
            if ($validated == 1) {
                if (!is_null($forum_id)) {
                    /*if ($sunk==1) {    Don't hide posts to sunk topics actually, it's too weird
                        $GLOBALS['FORUM_DB']->query('UPDATE '.$GLOBALS['FORUM_DB']->get_table_prefix().'f_forums SET f_cache_num_topics=(f_cache_num_topics+'.(($is_starter)?'1':'0').'),f_cache_num_posts=(f_cache_num_posts+1) WHERE id='.strval($topic_id));
                    } else {*/
                    require_code('cns_posts_action2');

                    // Find if the topic is validated. This can be approximate, if we don't get 1 then cns_force_update_forum_caching will do a search, making the code very slightly slower
                    if ((!$check_permissions) || (is_null($forum_id))) {
                        $topic_validated = 1;
                    } else {
                        if ($is_starter) {
                            $topic_validated = has_privilege($poster, 'bypass_validation_midrange_content', 'topics', array('forums', $forum_id)) ? 1 : 0;
                        } else {
                            $topic_validated = $GLOBALS['FORUM_DB']->query_select_value('f_topics', 't_validated', array('id' => $topic_id));
                        }
                    }

                    cms_profile_start_for('cns_make_post:cns_force_update_forum_caching');
                    cns_force_update_forum_caching($forum_id, ($is_starter) ? 1 : 0, 1, ($topic_validated == 0) ? null : $topic_id, ($topic_validated == 0) ? null : $topic_title, ($topic_validated == 0) ? null : $time, ($topic_validated == 0) ? null : $poster_name_if_guest, ($topic_validated == 0) ? null : $poster, ($topic_validated == 0) ? null : $forum_id);
                    cms_profile_end_for('cns_make_post:cns_force_update_forum_caching');
                    //}
                }
            }
        }

        if (!is_null($forum_id)) {
            $post_counts = is_null($forum_id) ? 1 : $GLOBALS['FORUM_DB']->query_select_value_if_there('f_forums', 'f_post_count_increment', array('id' => $forum_id));
            if (($post_counts === 1) && (!$anonymous) && ($validated == 1)) {
                // Update post count
                cms_profile_start_for('cns_make_post:cns_force_update_member_post_count');
                cns_force_update_member_post_count($poster, 1);
                cms_profile_end_for('cns_make_post:cns_force_update_member_post_count');
            }

            // Block decache
            if ($check_permissions) {
                cms_profile_start_for('cns_make_post:cns_decache_cms_blocks');
                cns_decache_cms_blocks($forum_id, null, $intended_solely_for); // i.e. we don't run this if in installer
                cms_profile_end_for('cns_make_post:cns_decache_cms_blocks');
            }
        }

        // Promotions
        if ($poster != $GLOBALS['CNS_DRIVER']->get_guest_id()) {
            require_code('cns_posts_action2');
            cms_profile_start_for('cns_make_post:cns_member_handle_promotion');
            cns_member_handle_promotion($poster);
            cms_profile_end_for('cns_make_post:cns_member_handle_promotion');
        }
    }

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        cms_profile_start_for('cns_make_post:generate_resourcefs_moniker');
        require_code('resource_fs');
        generate_resourcefs_moniker('post', strval($post_id), null, null, true);
        cms_profile_end_for('cns_make_post:generate_resourcefs_moniker');
    }

    cms_profile_start_for('cns_make_post:dispatch_member_mention_notifications');
    require_code('member_mentions');
    dispatch_member_mention_notifications('post', strval($post_id), $anonymous ? db_get_first_id() : $poster);
    cms_profile_end_for('cns_make_post:dispatch_member_mention_notifications');

    if (($is_starter) && (!$is_pt)) {
        require_code('sitemap_xml');
        notify_sitemap_node_add('SEARCH:topicview:id=' . strval($topic_id), $time, $last_edit_time, SITEMAP_IMPORTANCE_LOW, 'daily', has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'forums', strval($forum_id)));
    }

    cms_profile_end_for('cns_make_post', '#' . strval($post_id));

    return $post_id;
}

/**
 * Force a members post count to be recalculated.
 *
 * @param  MEMBER $member_id The member.
 * @param  ?integer $member_post_count_dif The amount to add to the post count (null: fully recalculate the post count).
 */
function cns_force_update_member_post_count($member_id, $member_post_count_dif = null)
{
    if ($GLOBALS['CNS_DRIVER']->get_guest_id() == $member_id) {
        return;
    }
    if (get_db_type() == 'xml') {
        return;
    }

    if (is_null($member_post_count_dif)) {
        // This is gonna take a while!!
        global $ALL_FORUM_POST_COUNT_INFO_CACHE;
        if (is_null($ALL_FORUM_POST_COUNT_INFO_CACHE)) {
            $ALL_FORUM_POST_COUNT_INFO_CACHE = collapse_2d_complexity('id', 'f_post_count_increment', $GLOBALS['FORUM_DB']->query('SELECT id,f_post_count_increment FROM ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_forums WHERE f_cache_num_posts>0'));
        }
        $member_post_count = 0;
        foreach ($ALL_FORUM_POST_COUNT_INFO_CACHE as $forum_id => $post_count_increment) {
            if ($post_count_increment == 1) {
                $map = array('p_poster' => $member_id, 'p_cache_forum_id' => $forum_id);
                if (addon_installed('unvalidated')) {
                    $map['p_validated'] = 1;
                }
                $member_post_count += $GLOBALS['FORUM_DB']->query_select_value('f_posts', 'COUNT(*)', $map);
            }
        }
        $map = array('p_poster' => $member_id, 'p_cache_forum_id' => null);
        if (addon_installed('unvalidated')) {
            $map['p_validated'] = 1;
        }
        $member_post_count += $GLOBALS['FORUM_DB']->query_select_value('f_posts', 'COUNT(*)', $map);
        $GLOBALS['FORUM_DB']->query_update('f_members', array('m_cache_num_posts' => $member_post_count), array('id' => $member_id));
    } else {
        $GLOBALS['FORUM_DB']->query('UPDATE ' . $GLOBALS['FORUM_DB']->get_table_prefix() . 'f_members SET m_cache_num_posts=(m_cache_num_posts+' . strval($member_post_count_dif) . ') WHERE id=' . strval($member_id));
    }
}

/**
 * Decache cached Conversr elements depending on a certain forum, and optionally a certain member.
 *
 * @param  AUTO_LINK $updated_forum_id The ID of the forum.
 * @param  ?string $forum_name The name of the forum (null: find it from the DB).
 * @param  ?MEMBER $member The member (null: do no member decaching).
 */
function cns_decache_cms_blocks($updated_forum_id, $forum_name = null, $member = null)
{
    if (is_null($forum_name)) {
        $forum_name = $GLOBALS['FORUM_DB']->query_select_value('f_forums', 'f_name', array('id' => $updated_forum_id));
    }

    $decache = array(
        array('main_forum_news', null),
        array('main_forum_topics', null),
        array('side_forum_news', null),
        array('bottom_news', array($forum_name)),
    );

    if (!is_null($member)) {
        $decache[] = array('side_cns_personal_topics', array($member));
        $decache[] = array('_new_pp', array($member));
    }

    if (get_option('show_post_validation') == '1') {
        $decache[] = array('main_staff_checklist', null);
    }

    decache($decache);
}
