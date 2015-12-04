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
 * @package    wiki
 */

/*
The concept of a chain is crucial to proper understanding of the Wiki+ system. Pages in Wiki+ are not tied to any paticular hierarchical location, but rather may be found via a "chain" of links. For usability, a "bread crumb" trail is shown as you move through Wiki+, and this should reflect the path chosen to get to the current page - thus a chain is passed through the URLs to encode this.
*/

/**
 * Get Tempcode for a Wiki+ post 'feature box' for the given row
 *
 * @param  array $row The database field row of it
 * @param  ID_TEXT $zone The zone to use
 * @param  boolean $give_context Whether to include context (i.e. say WHAT this is, not just show the actual content)
 * @param  boolean $include_breadcrumbs Whether to include breadcrumbs (if there are any)
 * @param  ?AUTO_LINK $root Virtual root to use (null: none)
 * @param  ID_TEXT $guid Overridden GUID to send to templates (blank: none)
 * @return Tempcode A box for it, linking to the full page
 */
function render_wiki_post_box($row, $zone = '_SEARCH', $give_context = true, $include_breadcrumbs = true, $root = null, $guid = '')
{
    require_lang('wiki');

    $just_wiki_post_row = db_map_restrict($row, array('id', 'the_message'));

    $map = array('page' => 'wiki', 'type' => 'browse', 'id' => $row['page_id']);
    if (!is_null($root)) {
        $map['keep_forum_root'] = $root;
    }
    $url = build_url($map, $zone);
    $url->attach('#post_' . strval($row['id']));

    $breadcrumbs = mixed();
    if ($include_breadcrumbs) {
        $breadcrumbs = breadcrumb_segments_to_tempcode(wiki_breadcrumbs(wiki_derive_chain($row['page_id'], $root), null, true));
    }

    $title = mixed();
    if ($give_context) {
        $title = do_lang_tempcode('WIKI_POST');
    }

    return do_template('SIMPLE_PREVIEW_BOX', array(
        '_GUID' => ($guid != '') ? $guid : 'f271c035af57eb45b7f3b37e437baf3c',
        'ID' => strval($row['id']),
        'TITLE' => $title,
        'BREADCRUMBS' => $breadcrumbs,
        'SUMMARY' => get_translated_tempcode('wiki_posts', $just_wiki_post_row, 'the_message'),
        'URL' => $url,
    ));
}

/**
 * Get Tempcode for a Wiki+ post 'feature box' for the given row
 *
 * @param  array $row The database field row of it
 * @param  ID_TEXT $zone The zone to use
 * @param  boolean $give_context Whether to include context (i.e. say WHAT this is, not just show the actual content)
 * @param  boolean $include_breadcrumbs Whether to include breadcrumbs (if there are any)
 * @param  ?AUTO_LINK $root Virtual root to use (null: none)
 * @param  ID_TEXT $guid Overridden GUID to send to templates (blank: none)
 * @return Tempcode A box for it, linking to the full page
 */
function render_wiki_page_box($row, $zone = '_SEARCH', $give_context = true, $include_breadcrumbs = true, $root = null, $guid = '')
{
    require_lang('wiki');

    $just_wiki_page_row = db_map_restrict($row, array('id', 'description'));

    $content = get_translated_tempcode('wiki_pages', $just_wiki_page_row, 'description');

    $map = array('page' => 'wiki', 'type' => 'browse', 'id' => $row['id']);
    if (!is_null($root)) {
        $map['keep_forum_root'] = $root;
    }
    $url = build_url($map, $zone);

    $_title = escape_html(get_translated_text($row['title']));
    $title = $give_context ? do_lang('CONTENT_IS_OF_TYPE', do_lang('_WIKI_PAGE'), $_title) : $_title;

    $breadcrumbs = mixed();
    if ($include_breadcrumbs) {
        $chain = wiki_derive_chain($row['id'], $root);
        $chain = preg_replace('#/[^/]+#', '', $chain);
        if ($chain != '') {
            $breadcrumbs = breadcrumb_segments_to_tempcode(wiki_breadcrumbs($chain, null, true));
        }
    }

    return do_template('SIMPLE_PREVIEW_BOX', array(
        '_GUID' => ($guid != '') ? $guid : 'd2c37a1f68e684dc4ac85e3d4e4bf959',
        'ID' => strval($row['id']),
        'TITLE' => $title,
        'BREADCRUMBS' => $breadcrumbs,
        'SUMMARY' => $content,
        'URL' => $url,
    ));
}

/**
 * Edit a Wiki+ post
 *
 * @param  AUTO_LINK $page_id The page ID
 * @param  string $message The new post
 * @param  BINARY $validated Whether the post will be validated
 * @param  ?MEMBER $member The member doing the action (null: current member)
 * @param  boolean $send_notification Whether to send out a notification out
 * @param  ?TIME $add_time The add time (null: now)
 * @param  integer $views The number of views so far
 * @param  ?TIME $edit_date The edit time (null: N/A)
 * @return AUTO_LINK The post ID
 */
function wiki_add_post($page_id, $message, $validated = 1, $member = null, $send_notification = true, $add_time = null, $views = 0, $edit_date = null)
{
    if (is_null($member)) {
        $member = get_member();
    }
    if (is_null($add_time)) {
        $add_time = time();
    }

    require_lang('wiki');

    ignore_user_abort(true);

    require_code('comcode_check');
    check_comcode($message, null, false, null, true);

    if (!addon_installed('unvalidated')) {
        $validated = 1;
    }
    $map = array(
        'validated' => $validated,
        'member_id' => $member,
        'date_and_time' => $add_time,
        'page_id' => $page_id,
        'wiki_views' => $views,
        'edit_date' => $edit_date
    );
    if (multi_lang_content()) {
        $map['the_message'] = 0;
    } else {
        $map['the_message'] = '';
        $map['the_message__text_parsed'] = '';
        $map['the_message__source_user'] = get_member();
    }
    $id = $GLOBALS['SITE_DB']->query_insert('wiki_posts', $map, true);

    require_code('attachments2');
    $GLOBALS['SITE_DB']->query_update('wiki_posts', insert_lang_comcode_attachments('the_message', 2, $message, 'wiki_post', strval($id)), array('id' => $id), '', 1);

    // Log
    $GLOBALS['SITE_DB']->query_insert('wiki_changes', array('the_action' => 'WIKI_MAKE_POST', 'the_page' => $page_id, 'ip' => get_ip_address(), 'member_id' => $member, 'date_and_time' => time()));

    // Update post count
    if (((addon_installed('points')) && (strlen($message) > 1024)) {
        require_code('points');
        $_count = point_info($member);
        $count = array_key_exists('points_gained_wiki', $_count) ? $_count['points_gained_wiki'] : 0;
        $GLOBALS['FORUM_DRIVER']->set_custom_field($member, 'points_gained_wiki', $count + 1);
    }

    // Stat
    update_stat('num_wiki_posts', 1);

    if ($send_notification) {
        if (post_param_integer('send_notification', null) !== 0) {
            dispatch_wiki_post_notification($id, 'ADD');
        }
    }

    if (get_option('show_post_validation') == '1') {
        decache('main_staff_checklist');
    }

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resourcefs_moniker('wiki_post', strval($id), null, null, true);
    }

    @ignore_user_abort(false);

    return $id;
}

/**
 * Edit a Wiki+ post
 *
 * @param  AUTO_LINK $id The post ID
 * @param  string $message The new post
 * @param  BINARY $validated Whether the post will be validated
 * @param  ?MEMBER $member The member doing the action (null: current member)
 * @param  ?AUTO_LINK $page_id The page ID (null: do not change)
 * @param  ?TIME $edit_time Edit time (null: either means current time, or if $null_is_literal, means reset to to NULL)
 * @param  ?TIME $add_time Add time (null: do not change)
 * @param  ?integer $views Number of views (null: do not change)
 * @param  boolean $null_is_literal Determines whether some NULLs passed mean 'use a default' or literally mean 'set to NULL'
 */
function wiki_edit_post($id, $message, $validated, $member = null, $page_id = null, $edit_time = null, $add_time = null, $views = null, $null_is_literal = false)
{
    if (is_null($edit_time)) {
        $edit_time = $null_is_literal ? null : time();
    }

    $rows = $GLOBALS['SITE_DB']->query_select('wiki_posts', array('*'), array('id' => $id), '', 1);
    if (!array_key_exists(0, $rows)) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'wiki_post'));
    }
    $myrow = $rows[0];
    $original_poster = $myrow['member_id'];
    $page_id = $myrow['page_id'];

    $_message = $GLOBALS['SITE_DB']->query_select_value('wiki_posts', 'the_message', array('id' => $id));

    require_code('attachments2');
    require_code('attachments3');

    if (!addon_installed('unvalidated')) {
        $validated = 1;
    }

    require_code('submit');
    $just_validated = (!content_validated('wiki_post', strval($id))) && ($validated == 1);
    if ($just_validated) {
        send_content_validated_notification('wiki_post', strval($id));
    }

    $update_map = array(
        'validated' => $validated,
    );
    $update_map += update_lang_comcode_attachments('the_message', $_message, $message, 'wiki_post', strval($id), null, true, $original_poster);

    if (!is_null($page_id)) {
        $update_map['page_id'] = $page_id;
    }

    $update_map['edit_date'] = $edit_time;
    if (!is_null($add_time)) {
        $update_map['add_date'] = $add_time;
    }
    if (!is_null($views)) {
        $update_map['wiki_views'] = $views;
    }
    if (!is_null($member)) {
        $update_map['submitter'] = $member;
    }

    $GLOBALS['SITE_DB']->query_update('wiki_posts', $update_map, array('id' => $id), '', 1);

    $GLOBALS['SITE_DB']->query_insert('wiki_changes', array('the_action' => 'WIKI_EDIT_POST', 'the_page' => $page_id, 'ip' => get_ip_address(), 'member_id' => is_null($member) ? get_member() : $member, 'date_and_time' => time()));

    if (post_param_integer('send_notification', null) !== 0) {
        if ($just_validated) {
            dispatch_wiki_post_notification($id, 'ADD');
        } else {
            dispatch_wiki_post_notification($id, 'EDIT');
        }
    }

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resourcefs_moniker('wiki_post', strval($id));
    }
}

/**
 * Delete a Wiki+ post
 *
 * @param  AUTO_LINK $post_id The post ID
 * @param  ?MEMBER $member The member doing the action (null: current member)
 */
function wiki_delete_post($post_id, $member = null)
{
    if (is_null($member)) {
        $member = get_member();
    }

    $original_poster = $GLOBALS['SITE_DB']->query_select_value('wiki_posts', 'member_id', array('id' => $post_id));

    $_message = $GLOBALS['SITE_DB']->query_select_value('wiki_posts', 'the_message', array('id' => $post_id));

    require_code('attachments2');
    require_code('attachments3');
    delete_lang_comcode_attachments($_message, 'wiki_post', strval($post_id));

    $GLOBALS['SITE_DB']->query_delete('wiki_posts', array('id' => $post_id), '', 1);
    $GLOBALS['SITE_DB']->query_delete('rating', array('rating_for_type' => 'wiki_post', 'rating_for_id' => $post_id));

    $GLOBALS['SITE_DB']->query_insert('wiki_changes', array('the_action' => 'WIKI_DELETE_POST', 'the_page' => $post_id, 'ip' => get_ip_address(), 'member_id' => $member, 'date_and_time' => time()));

    if (addon_installed('catalogues')) {
        update_catalogue_content_ref('wiki_post', strval($post_id), '');
    }

    // Stat
    update_stat('num_wiki_posts', -1);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        expunge_resourcefs_moniker('wiki_post', strval($post_id));
    }
}

/**
 * Add a Wiki+ page
 *
 * @param  SHORT_TEXT $title The page title
 * @param  LONG_TEXT $description The page description
 * @param  LONG_TEXT $notes Hidden notes pertaining to the page
 * @param  BINARY $hide_posts Whether to hide the posts on the page by default
 * @param  ?MEMBER $member The member doing the action (null: current member)
 * @param  ?TIME $add_time The add time (null: now)
 * @param  integer $views The number of views so far
 * @param  ?SHORT_TEXT $meta_keywords Meta keywords for this resource (null: do not edit) (blank: implicit)
 * @param  ?LONG_TEXT $meta_description Meta description for this resource (null: do not edit) (blank: implicit)
 * @param  ?TIME $edit_date The edit time (null: N/A)
 * @param  boolean $send_notification Whether to send a notification
 * @return AUTO_LINK The page ID
 */
function wiki_add_page($title, $description, $notes, $hide_posts, $member = null, $add_time = null, $views = 0, $meta_keywords = '', $meta_description = '', $edit_date = null, $send_notification = true)
{
    if (is_null($member)) {
        $member = get_member();
    }
    if (is_null($add_time)) {
        $add_time = time();
    }

    require_code('comcode_check');
    check_comcode($description, null, false, null, true);

    // Update post count
    if (((addon_installed('points')) && (strlen($description) > 1024)) {
        require_code('points');
        $_count = point_info($member);
        $count = array_key_exists('points_gained_wiki', $_count) ? $_count['points_gained_wiki'] : 0;
        $GLOBALS['FORUM_DRIVER']->set_custom_field($member, 'points_gained_wiki', $count + 1);
    }

    $map = array(
        'hide_posts' => $hide_posts,
        'notes' => $notes,
        'submitter' => $member,
        'wiki_views' => $views,
        'add_date' => time(),
        'edit_date' => $edit_date,
    );
    if (multi_lang_content()) {
        $map['description'] = 0;
    } else {
        $map['description'] = '';
        $map['description__text_parsed'] = '';
        $map['description__source_user'] = get_member();
    }
    $map += insert_lang('title', $title, 2);
    if ($description != '') {
        $id = $GLOBALS['SITE_DB']->query_insert('wiki_pages', $map, true);

        require_code('attachments2');
        $GLOBALS['SITE_DB']->query_update('wiki_pages', insert_lang_comcode_attachments('description', 2, $description, 'wiki_page', strval($id), null, false, $member), array('id' => $id), '', 1);
    } else {
        $map += insert_lang_comcode('description', $description, 2);
        $id = $GLOBALS['SITE_DB']->query_insert('wiki_pages', $map, true);
    }

    update_stat('num_wiki_pages', 1);

    $GLOBALS['SITE_DB']->query_insert('wiki_changes', array('the_action' => 'WIKI_ADD_PAGE', 'the_page' => $id, 'date_and_time' => time(), 'ip' => get_ip_address(), 'member_id' => $member));

    require_code('seo2');
    if (($meta_keywords == '') && ($meta_description == '')) {
        seo_meta_set_for_implicit('wiki_page', strval($id), array($title, $description), $description);
    } else {
        seo_meta_set_for_explicit('wiki_page', strval($id), $meta_keywords, $meta_description);
    }

    if ($send_notification) {
        if (post_param_integer('send_notification', null) !== 0) {
            dispatch_wiki_page_notification($id, 'ADD');
        }
    }

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resourcefs_moniker('wiki_page', strval($id), null, null, true);
    }

    require_code('sitemap_xml');
    notify_sitemap_node_add('SEARCH:wiki:browse:' . strval($id), null, $edit_date, ($id == db_get_first_id()) ? SITEMAP_IMPORTANCE_HIGH : SITEMAP_IMPORTANCE_MEDIUM, 'weekly', has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'wiki', strval($id)));

    return $id;
}

/**
 * Edit a Wiki+ page
 *
 * @param  AUTO_LINK $id The page ID
 * @param  SHORT_TEXT $title The page title
 * @param  LONG_TEXT $description The page description
 * @param  LONG_TEXT $notes Hidden notes pertaining to the page
 * @param  BINARY $hide_posts Whether to hide the posts on the page by default
 * @param  SHORT_TEXT $meta_keywords Meta keywords
 * @param  LONG_TEXT $meta_description Meta description
 * @param  ?MEMBER $member The member doing the action (null: current member)
 * @param  ?TIME $edit_time Edit time (null: either means current time, or if $null_is_literal, means reset to to NULL)
 * @param  ?TIME $add_time Add time (null: do not change)
 * @param  ?integer $views Views (null: do not change)
 * @param  boolean $null_is_literal Determines whether some NULLs passed mean 'use a default' or literally mean 'set to NULL'
 */
function wiki_edit_page($id, $title, $description, $notes, $hide_posts, $meta_keywords, $meta_description, $member = null, $edit_time = null, $add_time = null, $views = null, $null_is_literal = false)
{
    if (is_null($edit_time)) {
        $edit_time = $null_is_literal ? null : time();
    }

    $pages = $GLOBALS['SITE_DB']->query_select('wiki_pages', array('*'), array('id' => $id), '', 1);
    if (!array_key_exists(0, $pages)) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'wiki_page'));
    }
    $page = $pages[0];
    $_description = $page['description'];
    $_title = $page['title'];

    $update_map = array(
        'hide_posts' => $hide_posts,
        'notes' => $notes,
    );

    $update_map['edit_date'] = $edit_time;
    if (!is_null($add_time)) {
        $update_map['add_date'] = $add_time;
    }
    if (!is_null($views)) {
        $update_map['wiki_views'] = $views;
    }
    if (!is_null($member)) {
        $update_map['submitter'] = $member;
    } else {
        $member = $page['submitter'];
    }

    require_code('attachments2');
    require_code('attachments3');

    $update_map += lang_remap('title', $_title, $title);
    $update_map += update_lang_comcode_attachments('description', $_description, $description, 'wiki_page', strval($id), null, true, $member);

    $GLOBALS['SITE_DB']->query_update('wiki_pages', $update_map, array('id' => $id), '', 1);

    $GLOBALS['SITE_DB']->query_insert('wiki_changes', array('the_action' => 'WIKI_EDIT_PAGE', 'the_page' => $id, 'date_and_time' => time(), 'ip' => get_ip_address(), 'member_id' => is_null($member) ? get_member() : $member));

    require_code('seo2');
    seo_meta_set_for_explicit('wiki_page', strval($id), $meta_keywords, $meta_description);

    if (post_param_integer('send_notification', null) !== 0) {
        dispatch_wiki_page_notification($id, 'EDIT');
    }

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resourcefs_moniker('wiki_page', strval($id));
    }

    require_code('sitemap_xml');
    notify_sitemap_node_edit('SEARCH:wiki:browse:' . strval($id), has_category_access($GLOBALS['FORUM_DRIVER']->get_guest_id(), 'wiki', strval($id)));
}

/**
 * Delete a Wiki+ page
 *
 * @param  AUTO_LINK $id The page ID
 */
function wiki_delete_page($id)
{
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    $start = 0;
    do {
        $posts = $GLOBALS['SITE_DB']->query_select('wiki_posts', array('id'), array('page_id' => $id), '', 500, $start);
        foreach ($posts as $post) {
            wiki_delete_post($post['id']);
        }
        $start += 500;
    } while (array_key_exists(0, $posts));
    $pages = $GLOBALS['SITE_DB']->query_select('wiki_pages', array('*'), array('id' => $id), '', 1);
    if (!array_key_exists(0, $pages)) {
        warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'wiki_page'));
    }
    $page = $pages[0];
    $_description = $page['description'];
    $_title = $page['title'];
    delete_lang($_description);
    delete_lang($_title);
    $GLOBALS['SITE_DB']->query_delete('wiki_pages', array('id' => $id), '', 1);
    $GLOBALS['SITE_DB']->query_delete('wiki_children', array('parent_id' => $id));
    $GLOBALS['SITE_DB']->query_delete('wiki_children', array('child_id' => $id));
    $GLOBALS['SITE_DB']->query_delete('wiki_changes', array('the_page' => $id));

    if (addon_installed('catalogues')) {
        update_catalogue_content_ref('wiki_page', strval($id), '');
    }

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        expunge_resourcefs_moniker('wiki_page', strval($id));
    }

    require_code('sitemap_xml');
    notify_sitemap_node_delete('SEARCH:wiki:browse:' . strval($id));
}

/**
 * Get a chain script parameter or just an ID, in which case it does more work), and converts it into a ID/chain pair
 *
 * @param  ID_TEXT $parameter_name The name of the GET parameter that stores the chain
 * @param  ?string $default_value The default value for the chain (null: no default)
 * @return array An array of two elements: an ID and a chain
 */
function get_param_wiki_chain($parameter_name, $default_value = null)
{
    if (is_null($default_value)) {
        $default_value = strval(db_get_first_id());
    }
    $value = get_param_string($parameter_name, $default_value, true);
    if (is_numeric($value)) { // If you head to a page directly, e.g. via [[example]], should auto-derive breadcrumbs
        $id = intval($value);
        $chain = wiki_derive_chain($id);
    } else {
        require_code('urls2');

        $chain = $value;
        $parts = explode('/', $chain);
        $part = $parts[count($parts) - 1];
        if (is_numeric($part)) {
            $id = intval($part);
        } else {
            $url_moniker_where = array('m_resource_page' => 'wiki', 'm_moniker' => $part);
            $id = intval($GLOBALS['SITE_DB']->query_select_value('url_id_monikers', 'm_resource_id', $url_moniker_where));
        }
    }
    return array($id, $chain);
}

/**
 * Convert a Wiki+ chain to a nice breadcrumb trail.
 *
 * @param  string $chain The chain to convert (which should include the current page ID)
 * @param  ?string $current_title The title of the current Wiki+ page (if not given, it is looked up) (null: work it out)
 * @param  boolean $final_link Whether to show the final breadcrumbs element with a link to it (all others will always have links if $links is true)
 * @param  boolean $links Whether to show links to pages in the breadcrumbs
 * @param  boolean $this_link_virtual_root Whether to make the link as a virtual-root link (only applies if $final_link is true)
 * @return array Breadcrumbs
 */
function wiki_breadcrumbs($chain, $current_title = null, $final_link = false, $links = true, $this_link_virtual_root = false)
{
    $segments = array();
    $token = strtok($chain, '/');
    $rebuild_chain = '';
    while ($token !== false) {
        $next_token = strtok('/');

        if ($rebuild_chain != '') {
            $rebuild_chain .= '/';
        }
        $rebuild_chain .= $token;

        $link_id = ($this_link_virtual_root && ($next_token === false)) ? $token : $rebuild_chain;

        if (is_numeric($token)) {
            $id = intval($token);
        } else {
            $url_moniker_where = array('m_resource_page' => 'wiki', 'm_moniker' => $token);
            $id = intval($GLOBALS['SITE_DB']->query_select_value('url_id_monikers', 'm_resource_id', $url_moniker_where));
        }

        $page_link = build_page_link(array('page' => 'wiki', 'type' => 'browse', 'id' => $link_id) + (($this_link_virtual_root && ($next_token === false)) ? array('keep_wiki_root' => $id) : array()), get_module_zone('wiki'));

        if ($next_token !== false) { // If not the last token (i.e. not the current page)
            $title = $GLOBALS['SITE_DB']->query_select_value_if_there('wiki_pages', 'title', array('id' => $id));
            if (is_null($title)) {
                continue;
            }
            $token_title = get_translated_text($title);
            $segments[] = $links ? array($page_link, escape_html($token_title)) : array('', make_string_tempcode(escape_html($token_title)));
        } else {
            if (is_null($current_title)) {
                $_current_title = $GLOBALS['SITE_DB']->query_select_value_if_there('wiki_pages', 'title', array('id' => $id));
                $current_title = is_null($_current_title) ? do_lang('MISSING_RESOURCE', 'wiki_page') : get_translated_text($_current_title);
            }
            if ($final_link) {
                $segments[] = array($page_link, $current_title);
            } else {
                $segments [] = array('', $current_title);
            }
        }

        $token = $next_token;
    }

    return $segments;
}

/**
 * Create a Wiki+ chain from the specified page ID
 *
 * @param  AUTO_LINK $id The ID of the page to derive a chain for
 * @param  ?AUTO_LINK $root Virtual root to use (null: none)
 * @return string The Wiki+ chain derived
 */
function wiki_derive_chain($id, $root = null)
{
    static $parent_details = array();

    if (is_null($root)) {
        $root = get_param_integer('keep_wiki_root', db_get_first_id());
    }

    require_code('urls2');

    $page_id = $id;
    $chain = '';
    $seen_before = array();
    while ($page_id > $root) {
        $seen_before[$page_id] = 1;

        if (!array_key_exists($page_id, $parent_details)) {
            $parent_rows = $GLOBALS['SITE_DB']->query_select('wiki_children', array('parent_id', 'title'), array('child_id' => $page_id), '', 1);
            $new_page_id = mixed();
            if (!array_key_exists(0, $parent_rows)) {
                break; // Orphaned, so we can't find a chain
            }
            $parent_details[$page_id] = array($parent_rows[0]['parent_id'], $parent_rows[0]['title']);
        }

        if ($chain != '') {
            $chain = '/' . $chain;
        }
        if (get_option('url_monikers_enabled') == '1') {
            $moniker_src = $parent_details[$page_id][1];
            $page_moniker = suggest_new_idmoniker_for('wiki', 'browse', strval($page_id), '', $moniker_src);
        } else {
            $page_moniker = strval($page_id);
        }
        $chain = $page_moniker . $chain;

        $page_id = $parent_details[$page_id][0]; // For next time
        if (array_key_exists($page_id, $seen_before)) {
            break; // Stop loops
        }
    }
    if ($chain == '') {
        $chain = strval($page_id);
    }
    return $chain;
}

/**
 * Get a nice formatted XHTML list of all the children beneath the specified Wiki+ page. This function is recursive.
 *
 * @param  ?AUTO_LINK $select The Wiki+ page to select by default (null: none)
 * @param  ?AUTO_LINK $id The Wiki+ page to look beneath (null: the root)
 * @param  string $breadcrumbs Breadcrumbs built up so far, in recursion (blank: starting recursion)
 * @param  boolean $include_orphans Whether to include orphaned pages in the breadcrumbs
 * @param  boolean $use_compound_list Whether to create a compound list (gets pairs: Tempcode, and comma-separated list of children)
 * @param  boolean $ins_format Whether to use titles in IDs after a ! (used on tree edit page)
 * @return mixed Tempcode for the list / pair of Tempcode and compound
 */
function create_selection_list_wiki_page_tree($select = null, $id = null, $breadcrumbs = '', $include_orphans = true, $use_compound_list = false, $ins_format = false)
{
    if (is_null($id)) {
        $id = db_get_first_id();
    }

    if ($GLOBALS['SITE_DB']->query_select_value('wiki_pages', 'COUNT(*)') > 1000) {
        return new Tempcode();
    }

    $wiki_seen = array();
    $title = get_translated_text($GLOBALS['SITE_DB']->query_select_value('wiki_pages', 'title', array('id' => $id)));
    $out = _create_selection_list_wiki_page_tree($wiki_seen, $select, $id, $breadcrumbs, $title, $use_compound_list, $ins_format);

    if ($include_orphans) {
        if (!db_has_subqueries($GLOBALS['SITE_DB']->connection_read)) {
            $wiki_seen = array(db_get_first_id());
            get_wiki_page_tree($wiki_seen, is_null($id) ? null : intval($id)); // To build up $wiki_seen
            $where = '';
            foreach ($wiki_seen as $seen) {
                if ($where != '') {
                    $where .= ' AND ';
                }
                $where .= 'p.id<>' . strval($seen);
            }

            $orphans = $GLOBALS['SITE_DB']->query('SELECT p.id,p.title FROM ' . get_table_prefix() . 'wiki_pages p WHERE ' . $where . ' ORDER BY add_date DESC', intval(get_option('general_safety_listing_limit'))/*reasonable limit*/, null, false, true);
        } else {
            $orphans = $GLOBALS['SITE_DB']->query('SELECT p.id,p.title FROM ' . get_table_prefix() . 'wiki_pages p WHERE p.id<>' . strval(db_get_first_id()) . ' AND NOT EXISTS(SELECT * FROM ' . get_table_prefix() . 'wiki_children WHERE child_id=p.id) ORDER BY add_date DESC', intval(get_option('general_safety_listing_limit'))/*reasonable limit*/);
        }

        foreach ($orphans as $i => $orphan) {
            $orphans[$i]['_title'] = get_translated_text($orphan['title']);
        }
        if (count($orphans) < intval(get_option('general_safety_listing_limit'))) {
            sort_maps_by($orphans, '_title');
        }

        foreach ($orphans as $orphan) {
            if (!has_category_access(get_member(), 'wiki_page', strval($orphan['id']))) {
                continue;
            }

            $title = $orphan['_title'];
            $out->attach(form_input_list_entry($ins_format ? (strval($orphan['id']) . '!' . $title) : strval($orphan['id']), false, do_lang('WIKI_ORPHANED') . ' > ' . $title));
        }
    }

    return $out;
}

/**
 * Helper function. Get a nice formatted XHTML list of all the children beneath the specified Wiki+ page. This function is recursive.
 *
 * @param  array $wiki_seen A list of pages we've already seen (we don't repeat them in multiple list positions)
 * @param  ?AUTO_LINK $select The Wiki+ page to select by default (null: none)
 * @param  AUTO_LINK $id The Wiki+ page to look beneath
 * @param  string $breadcrumbs Breadcrumbs built up so far, in recursion (blank: starting recursion)
 * @param  SHORT_TEXT $title The title of the Wiki+ page to look beneath
 * @param  boolean $use_compound_list Whether to create a compound list (gets pairs: Tempcode, and comma-separated list of children)
 * @param  boolean $ins_format Whether to use titles in IDs after a ! (used on tree edit page)
 * @return mixed Tempcode for the list / pair of Tempcode and compound
 * @ignore
 */
function _create_selection_list_wiki_page_tree(&$wiki_seen, $select, $id, $breadcrumbs, $title, $use_compound_list = false, $ins_format = false)
{
    $wiki_seen[] = $id;

    $sub_breadcrumbs = ($breadcrumbs == '') ? ($title . ' > ') : ($breadcrumbs . $title . ' > ');

    $rows = $GLOBALS['SITE_DB']->query_select('wiki_children', array('*'), array('parent_id' => $id), 'ORDER BY title', intval(get_option('general_safety_listing_limit'))/*reasonable limit*/);
    $compound_list = strval($id) . ',';
    $_below = new Tempcode();
    foreach ($rows as $i => $myrow) {
        if (!in_array($myrow['child_id'], $wiki_seen)) {
            if (!has_category_access(get_member(), 'wiki_page', strval($myrow['child_id']))) {
                continue;
            }

            if (is_null($myrow['title'])) {
                $temp_rows = $GLOBALS['SITE_DB']->query_select('wiki_pages', array('title'), array('id' => $myrow['child_id']), '', 1);
                $myrow['title'] = get_translated_text($temp_rows[0]['title']);
                $rows[$i]['title'] = $myrow['title'];
                $GLOBALS['SITE_DB']->query_update('wiki_children', array('title' => $myrow['title']), array('parent_id' => $id, 'child_id' => $myrow['child_id']));
            }
            $below = _create_selection_list_wiki_page_tree($wiki_seen, $select, $myrow['child_id'], $sub_breadcrumbs, $myrow['title'], $use_compound_list, $ins_format);
            if ($use_compound_list) {
                list($below, $_compound_list) = $below;
                $compound_list .= $_compound_list;
            }
            $_below->attach($below);
        }
    }

    // $out = form_input_list_entry(strval($id), ($select==$id), do_template('WIKI_LIST_TREE_LINE', array('_GUID'=>'d9d4a951df598edd3f08f87be634965b', 'BREADCRUMBS'=>$breadcrumbs, 'TITLE'=>$title, 'ID'=>$id)));
    // $out = '<option value="' . (!$use_compound_list ? $id : $compound_list) . '">' . $breadcrumbs . escape_html($title) . '</option>' . "\n";
    // $out .= $_below;
    $out = form_input_list_entry(((!$use_compound_list) ? strval($id) : $compound_list) . ($ins_format ? ('!' . $title) : ''), false, $breadcrumbs . $title);
    $out->attach($_below);

    if ($use_compound_list) {
        return array($out, $compound_list);
    } else {
        return $out;
    }
}

/**
 * Get a list of maps containing all the subpages, and path information, of the specified page - and those beneath it, recursively.
 *
 * @param  array $wiki_seen A list of pages we've already seen (we don't repeat them in multiple list positions)
 * @param  ?AUTO_LINK $page_id The page being at the root of our recursion (null: true root page)
 * @param  ?string $breadcrumbs The breadcrumbs up to this point in the recursion (null: blank, as we are starting the recursion)
 * @param  ?array $page_details The details of the $page_id we are currently going through (null: look it up). This is here for efficiency reasons, as finding children IDs to recurse to also reveals the childs title
 * @param  boolean $do_stats Whether to collect post counts with our breadcrumbs information
 * @param  boolean $use_compound_list Whether to make a compound list (a pair of a comma-separated list of children, and the child array)
 * @param  ?integer $levels The number of recursive levels to search (null: all)
 * @return array A list of maps for all subcategories. Each map entry containins the fields 'id' (category ID) and 'breadcrumbs' (path to the category, including the categories own title). There is also an additional 'downloadcount' entry if stats were requested
 */
function get_wiki_page_tree(&$wiki_seen, $page_id = null, $breadcrumbs = null, $page_details = null, $do_stats = false, $use_compound_list = false, $levels = null)
{
    if (!$use_compound_list) {
        if ($levels == -1) {
            return array();
        }
    }

    if (is_null($page_id)) {
        $page_id = db_get_first_id();
    }
    if (is_null($breadcrumbs)) {
        $breadcrumbs = '';
    }

    $wiki_seen[] = $page_id;

    if (is_null($page_details)) {
        $_page_details = $GLOBALS['SITE_DB']->query_select('wiki_pages', array('title'), array('id' => $page_id), '', 1);
        if (!array_key_exists(0, $_page_details)) {
            warn_exit(do_lang_tempcode('MISSING_RESOURCE', 'wiki_page'));
        }
        $page_details = $_page_details[0];
    }

    $title = get_translated_text($page_details['title']);
    $breadcrumbs .= $title;

    // We'll be putting all children in this entire tree into a single list
    $children = array();
    $children[0] = array();
    $children[0]['id'] = $page_id;
    $children[0]['title'] = $title;
    $children[0]['breadcrumbs'] = $breadcrumbs;
    $children[0]['compound_list'] = strval($page_id) . ',';
    if ($do_stats) {
        $children[0]['post_count'] = $GLOBALS['SITE_DB']->query_select_value('wiki_posts', 'COUNT(*)', array('page_id' => $page_id));
    }

    // Children of this category
    $rows = $GLOBALS['SITE_DB']->query_select('wiki_children', array('*'), array('parent_id' => $page_id), 'ORDER BY title', intval(get_option('general_safety_listing_limit'))/*reasonable limit*/);
    $children[0]['child_count'] = count($rows);
    $child_breadcrumbs = ($breadcrumbs == '') ? '' : ($breadcrumbs . ' > ');
    if (($levels !== 0) || ($use_compound_list)) {
        foreach ($rows as $child) {
            if (!in_array($child['child_id'], $wiki_seen)) {
                if (!has_category_access(get_member(), 'wiki_page', strval($child['child_id']))) {
                    continue;
                }

                // Fix child title
                if (is_null($child['title'])) {
                    $temp_rows = $GLOBALS['SITE_DB']->query_select('wiki_pages', array('title'), array('id' => $child['child_id']), '', 1);
                    $child['title'] = get_translated_text($temp_rows[0]['title']);

                    $GLOBALS['SITE_DB']->query_update('wiki_children', array('title' => $child['title']), array('parent_id' => $page_id, 'child_id' => $child['child_id']));
                }

                $child_id = $child['child_id'];

                $child_children = get_wiki_page_tree($wiki_seen, $child_id, $breadcrumbs, $child, $do_stats, $use_compound_list, is_null($levels) ? null : ($levels - 1));
                if ($use_compound_list) {
                    list($child_children, $_compound_list) = $child_children;
                    $children[0]['compound_list'] .= $_compound_list;
                }

                if ($levels !== 0) {
                    $children = array_merge($children, $child_children);
                }
            }
        }
    }

    return $use_compound_list ? array($children, $children[0]['compound_list']) : $children;
}

/**
 * Dispatch a notification about a Wiki+ post
 *
 * @param  AUTO_LINK $post_id The post ID
 * @param  ID_TEXT $type The action type
 * @set ADD EDIT
 */
function dispatch_wiki_post_notification($post_id, $type)
{
    $page_id = $GLOBALS['SITE_DB']->query_select_value('wiki_posts', 'page_id', array('id' => $post_id));
    $the_message = $GLOBALS['SITE_DB']->query_select_value('wiki_posts', 'the_message', array('id' => $post_id));
    $page_name = get_translated_text($GLOBALS['SITE_DB']->query_select_value('wiki_pages', 'title', array('id' => $page_id)));
    $_the_message = get_translated_text($the_message);

    $_view_url = build_url(array('page' => 'wiki', 'type' => 'browse', 'id' => $page_id), get_page_zone('wiki'), null, false, false, true);
    $view_url = $_view_url->evaluate();
    $their_displayname = $GLOBALS['FORUM_DRIVER']->get_username(get_member(), true);
    $their_username = $GLOBALS['FORUM_DRIVER']->get_username(get_member());

    $subject = do_lang($type . '_WIKI_POST_SUBJECT', $page_name, $their_displayname, $their_username, get_site_default_lang());
    $message_raw = do_lang($type . '_WIKI_POST_BODY', comcode_escape($their_displayname), comcode_escape($page_name), array(comcode_escape($view_url), $_the_message, strval(get_member()), comcode_escape($their_username)), get_site_default_lang());

    require_code('notifications');
    dispatch_notification('wiki', strval($page_id), $subject, $message_raw);
}

/**
 * Dispatch a notification about a Wiki+ page
 *
 * @param  AUTO_LINK $page_id The page ID
 * @param  ID_TEXT $type The action type
 * @set ADD EDIT
 */
function dispatch_wiki_page_notification($page_id, $type)
{
    $page_name = get_translated_text($GLOBALS['SITE_DB']->query_select_value('wiki_pages', 'title', array('id' => $page_id)));
    $_the_message = get_translated_text($GLOBALS['SITE_DB']->query_select_value('wiki_pages', 'description', array('id' => $page_id)));

    $_view_url = build_url(array('page' => 'wiki', 'type' => 'browse', 'id' => $page_id), get_page_zone('wiki'), null, false, false, true);
    $view_url = $_view_url->evaluate();
    $their_displayname = $GLOBALS['FORUM_DRIVER']->get_username(get_member(), true);
    $their_username = $GLOBALS['FORUM_DRIVER']->get_username(get_member());

    $subject = do_lang($type . '_WIKI_PAGE_SUBJECT', $page_name, $their_displayname, $their_username, get_site_default_lang());
    $message_raw = do_lang($type . '_WIKI_PAGE_BODY', comcode_escape($their_displayname), comcode_escape($page_name), array(comcode_escape($view_url), $_the_message, comcode_escape($their_username)), get_site_default_lang());

    require_code('notifications');
    dispatch_notification('wiki', strval($page_id), $subject, $message_raw);
}
