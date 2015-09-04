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
 * @package    core_forum_drivers
 */

require_code('forum/shared/ipb');

/**
 * Standard code module initialisation function.
 * @ignore
 */
function init__forum__ipb1()
{
    global $IPB_STATS_CACHE;
    $IPB_STATS_CACHE = null;
}

/**
 * Forum driver class.
 *
 * @package    core_forum_drivers
 */
class Forum_driver_ipb1 extends forum_driver_ipb_shared
{
    /**
     * From a member row, get the member's name.
     *
     * @param  array $r The profile-row
     * @return string The member name
     */
    public function mrow_username($r)
    {
        return $this->ipb_unescape($r['name']);
    }

    /**
     * Get a member row for the member of the given name.
     *
     * @param  SHORT_TEXT $name The member name
     * @return ?array The profile-row (null: could not find)
     */
    public function get_mrow($name)
    {
        $rows = $this->connection->query_select('members', array('*'), array('name' => $this->ipb_escape($name)), '', 1);
        if (!array_key_exists(0, $rows)) {
            return null;
        }
        return $rows[0];
    }

    /**
     * Get the name relating to the specified member ID.
     * If this returns NULL, then the member has been deleted. Always take potential NULL output into account.
     *
     * @param  MEMBER $member The member ID
     * @return ?SHORT_TEXT The member name (null: member deleted)
     */
    protected function _get_username($member)
    {
        if ($member == $this->get_guest_id()) {
            return do_lang('GUEST');
        }
        return $this->ipb_unescape($this->get_member_row_field($member, 'name'));
    }

    /**
     * Find all members with a name matching the given SQL LIKE string.
     *
     * @param  string $pattern The pattern
     * @param  ?integer $limit Maximum number to return (limits to the most recent active) (null: no limit)
     * @return ?array The array of matched members (null: none found)
     */
    public function get_matching_members($pattern, $limit = null)
    {
        $query = 'SELECT * FROM ' . $this->connection->get_table_prefix() . 'members WHERE name LIKE \'' . db_encode_like($pattern) . '\' AND id<>' . strval($this->get_guest_id()) . ' ORDER BY last_post DESC';
        $rows = $this->connection->query($query, $limit);
        sort_maps_by($rows, 'name');
        return $rows;
    }

    /**
     * Get a member ID from the given member's username.
     *
     * @param  SHORT_TEXT $name The member name
     * @return MEMBER The member ID
     */
    public function get_member_from_username($name)
    {
        return $this->connection->query_select_value_if_there('members', 'id', array('name' => $name));
    }

    /**
     * Add the specified custom field to the forum (some forums implemented this using proper custom profile fields, others through adding a new field).
     *
     * @param  string $name The name of the new custom field
     * @param  integer $length The length of the new custom field
     * @return boolean Whether the custom field was created successfully
     */
    public function install_create_custom_field($name, $length)
    {
        $name = 'cms_' . $name;
        $id = $this->connection->query_select_value_if_there('pfields_data', 'fid', array('ftitle' => $name));
        if (is_null($id)) {
            $id = $this->connection->query_insert('pfields_data', array('ftitle' => $name, 'ftype' => 'text', 'fhide' => 1, 'fmaxinput' => $length, 'fedit' => 0, 'forder' => 0), true);
            $this->connection->query('ALTER TABLE ' . $this->connection->get_table_prefix() . 'pfields_content ADD field_' . strval($id) . ' TEXT');
        }
        return !is_null($id);
    }

    /**
     * Set a custom profile fields value. It should not be called directly.
     *
     * @param  MEMBER $member The member ID
     * @param  string $field The field name
     * @param  string $value The value
     */
    public function set_custom_field($member, $field, $value)
    {
        $id = $this->connection->query_select_value_if_there('pfields_data', 'fid', array('ftitle' => 'cms_' . $field));
        if (is_null($id)) {
            return;
        }
        $old = $this->connection->query_select_value_if_there('pfields_content', 'member_id', array('member_id' => $member));
        if (is_null($old)) {
            $this->connection->query_insert('pfields_content', array('member_id' => $member));
        }
        $this->connection->query_update('pfields_content', array('field_' . strval($id) => $value), array('member_id' => $member), '', 1);
    }

    /**
     * Get custom profile fields values for all 'cms_' prefixed keys.
     *
     * @param  MEMBER $member The member ID
     * @return ?array A map of the custom profile fields, key_suffix=>value (null: no fields)
     */
    public function get_custom_fields($member)
    {
        $rows = $this->connection->query('SELECT fid,ftitle FROM ' . $this->connection->get_table_prefix() . 'pfields_data WHERE ftitle LIKE \'' . db_encode_like('cms_%') . '\'');
        $values = $this->connection->query_select('pfields_content', array('*'), array('member_id' => $member), '', 1);
        if (!array_key_exists(0, $values)) {
            return null;
        }

        $out = array();
        foreach ($rows as $row) {
            $title = substr($row['ftitle'], 4);
            $out[$title] = $values[0]['field_' . strval($row['fid'])];
        }
        return $out;
    }

    /**
     * Searches for forum auto-config at this path.
     *
     * @param  PATH $path The path in which to search
     * @return boolean Whether the forum auto-config could be found
     */
    public function install_test_load_from($path)
    {
        global $PROBED_FORUM_CONFIG;
        if (file_exists($path . '/conf_global.php')) {
            @include($path . '/conf_global.php');
            if (array_key_exists('cookie_id', $PROBED_FORUM_CONFIG)) {
                $PROBED_FORUM_CONFIG['cookie_member_id'] = $PROBED_FORUM_CONFIG['cookie_id'] . 'member_id';
                $PROBED_FORUM_CONFIG['cookie_member_hash'] = $PROBED_FORUM_CONFIG['cookie_id'] . 'pass_hash';
            }
            return true;
        }
        return false;
    }

    /**
     * Get an array of paths to search for config at.
     *
     * @return array The paths in which to search for the forum config
     */
    public function install_get_path_search_list()
    {
        return array(
            0 => 'forums',
            1 => 'forum',
            2 => 'boards',
            3 => 'board',
            4 => 'ipf',
            5 => 'ipb',
            6 => 'upload',
            7 => 'uploads',
            8 => 'ipboard',
            10 => '../forums',
            11 => '../forum',
            12 => '../boards',
            13 => '../board',
            14 => '../ipf',
            15 => '../ipb',
            16 => '../upload',
            17 => '../uploads',
            18 => '../ipboard');
    }

    /**
     * Get the avatar URL for the specified member ID.
     *
     * @param  MEMBER $member The member ID
     * @return URLPATH The URL (blank: none)
     */
    public function get_member_avatar_url($member)
    {
        $avatar = $this->get_member_row_field($member, 'avatar');
        if ($avatar == 'noavatar') {
            $avatar = '';
        } elseif (is_null($avatar)) {
            $avatar = '';
        } elseif (substr($avatar, 0, 7) == 'upload:') {
            $avatar = get_forum_base_url() . '/uploads/' . substr($avatar, 7);
        } elseif ((url_is_local($avatar)) && ($avatar != '')) {
            $avatar = get_forum_base_url() . '/html/avatars/' . $avatar;
        }

        return $avatar;
    }

    /**
     * Makes a post in the specified forum, in the specified topic according to the given specifications. If the topic doesn't exist, it is created along with a spacer-post.
     * Spacer posts exist in order to allow staff to delete the first true post in a topic. Without spacers, this would not be possible with most forum systems. They also serve to provide meta information on the topic that cannot be encoded in the title (such as a link to the content being commented upon).
     *
     * @param  SHORT_TEXT $forum_name The forum name
     * @param  SHORT_TEXT $topic_identifier The topic identifier (usually <content-type>_<content-id>)
     * @param  MEMBER $member The member ID
     * @param  LONG_TEXT $post_title The post title
     * @param  LONG_TEXT $_post The post content in Comcode format
     * @param  string $content_title The topic title; must be same as content title if this is for a comment topic
     * @param  string $topic_identifier_encapsulation_prefix This is put together with the topic identifier to make a more-human-readable topic title or topic description (hopefully the latter and a $content_title title, but only if the forum supports descriptions)
     * @param  ?URLPATH $content_url URL to the content (null: do not make spacer post)
     * @param  ?TIME $time The post time (null: use current time)
     * @param  ?IP $ip The post IP address (null: use current members IP address)
     * @param  ?BINARY $validated Whether the post is validated (null: unknown, find whether it needs to be marked unvalidated initially). This only works with the Conversr driver.
     * @param  ?BINARY $topic_validated Whether the topic is validated (null: unknown, find whether it needs to be marked unvalidated initially). This only works with the Conversr driver.
     * @param  boolean $skip_post_checks Whether to skip post checks
     * @param  SHORT_TEXT $poster_name_if_guest The name of the poster
     * @param  ?AUTO_LINK $parent_id ID of post being replied to (null: N/A)
     * @param  boolean $staff_only Whether the reply is only visible to staff
     * @return array Topic ID (may be NULL), and whether a hidden post has been made
     */
    public function make_post_forum_topic($forum_name, $topic_identifier, $member, $post_title, $_post, $content_title, $topic_identifier_encapsulation_prefix, $content_url = null, $time = null, $ip = null, $validated = null, $topic_validated = 1, $skip_post_checks = false, $poster_name_if_guest = '', $parent_id = null, $staff_only = false)
    {
        $__post = comcode_to_tempcode($_post);
        $post = $__post->evaluate();

        if (is_null($time)) {
            $time = time();
        }
        if (is_null($ip)) {
            $ip = get_ip_address();
        }
        $forum_id = $this->forum_id_from_name($forum_name);
        if (is_null($forum_id)) {
            warn_exit(do_lang_tempcode('MISSING_FORUM', escape_html($forum_name)));
        }
        $username = $this->get_username($member);
        $topic_id = $this->find_topic_id_for_topic_identifier($forum_name, $topic_identifier);
        $is_new = is_null($topic_id);
        if ($is_new) {
            $topic_id = $this->connection->query_insert('topics', array('title' => $this->ipb_escape($content_title . ', ' . $topic_identifier_encapsulation_prefix . ': #' . $topic_identifier), 'state' => 'open', 'posts' => 1, 'starter_id' => $member, 'start_date' => $time, 'icon_id' => 0, 'starter_name' => $username, 'poll_state' => 0, 'last_vote' => 0, 'forum_id' => $forum_id, 'approved' => 1, 'author_mode' => 1), true);
            $home_link = hyperlink($content_url, $content_title, false, true);
            $this->connection->query_insert('posts', array('author_id' => 0, 'author_name' => do_lang('SYSTEM', '', '', '', get_site_default_lang()), 'ip_address' => '127.0.0.1', 'post_date' => $time, 'icon_id' => 0, 'post' => do_lang('SPACER_POST', $home_link->evaluate(), '', '', get_site_default_lang()), 'queued' => 0, 'topic_id' => $topic_id, 'forum_id' => $forum_id, 'attach_id' => '', 'attach_hits' => 0, 'attach_type' => '', 'attach_file' => '', 'post_title' => '', 'new_topic' => 1));
            $this->connection->query('UPDATE ' . $this->connection->get_table_prefix() . 'forums SET topics=(topics+1) WHERE id=' . strval($forum_id), 1);
        }

        $GLOBALS['LAST_TOPIC_ID'] = $topic_id;
        $GLOBALS['LAST_TOPIC_IS_NEW'] = $is_new;

        if ($post == '') {
            return array($topic_id, false);
        }

        $this->connection->query_insert('posts', array('author_id' => $member, 'author_name' => $this->ipb_escape($username), 'ip_address' => $ip, 'post_date' => $time, 'icon_id' => 0, 'post' => $post, 'queued' => 0, 'topic_id' => $topic_id, 'forum_id' => $forum_id, 'attach_id' => '', 'attach_hits' => 0, 'attach_type' => '', 'attach_file' => '', 'post_title' => $this->ipb_escape($post_title), 'new_topic' => 0));
        $this->connection->query('UPDATE ' . $this->connection->get_table_prefix() . 'forums SET posts=(posts+1), last_post=' . strval($time) . ', last_poster_id=' . strval($member) . ', last_poster_name=\'' . db_escape_string($this->ipb_escape($username)) . '\', last_id=' . strval($topic_id) . ', last_title=\'' . db_escape_string($this->ipb_escape($post_title)) . '\' WHERE id=' . strval($forum_id), 1);
        $this->connection->query('UPDATE ' . $this->connection->get_table_prefix() . 'topics SET posts=(posts+1), last_post=' . strval($time) . ', last_poster_id=' . strval($member) . ', last_poster_name=\'' . db_escape_string($this->ipb_escape($username)) . '\' WHERE tid=' . strval($topic_id), 1);

        return array($topic_id, false);
    }

    /**
     * Get an array of maps for the topic in the given forum.
     *
     * @param  integer $topic_id The topic ID
     * @param  integer $count The comment count will be returned here by reference
     * @param  integer $max Maximum comments to returned
     * @param  integer $start Comment to start at
     * @param  boolean $mark_read Whether to mark the topic read (ignored for this forum driver)
     * @param  boolean $reverse Whether to show in reverse
     * @return mixed The array of maps (Each map is: title, message, member, date) (-1 for no such forum, -2 for no such topic)
     */
    public function get_forum_topic_posts($topic_id, &$count, $max = 100, $start = 0, $mark_read = true, $reverse = false)
    {
        if (is_null($topic_id)) {
            return (-2);
        }
        $order = $reverse ? 'post_date DESC' : 'post_date';
        $rows = $this->connection->query('SELECT * FROM ' . $this->connection->get_table_prefix() . 'posts WHERE topic_id=' . strval($topic_id) . ' AND post NOT LIKE \'' . db_encode_like(substr(do_lang('SPACER_POST', '', '', '', get_site_default_lang()), 0, 20) . '%') . '\' ORDER BY ' . $order, $max, $start);
        $count = $this->connection->query_value_if_there('SELECT COUNT(*) ' . $this->connection->get_table_prefix() . 'posts WHERE topic_id=' . strval($topic_id) . ' AND post NOT LIKE \'' . db_encode_like(substr(do_lang('SPACER_POST', '', '', '', get_site_default_lang()), 0, 20) . '%') . '\'');
        $out = array();
        foreach ($rows as $myrow) {
            $temp = array();
            $temp['title'] = $myrow['post_title'];
            if (is_null($temp['title'])) {
                $temp['title'] = '';
            } else {
                $temp['title'] = $this->ipb_unescape($temp['title']);
            }
            $temp['message'] = $myrow['post'];
            $temp['member'] = $myrow['author_id'];
            $temp['date'] = $myrow['post_date'];

            $out[] = $temp;
        }

        return $out;
    }

    /**
     * Get an array of topics in the given forum. Each topic is an array with the following attributes:
     * - id, the topic ID
     * - title, the topic title
     * - lastusername, the username of the last poster
     * - lasttime, the timestamp of the last reply
     * - closed, a Boolean for whether the topic is currently closed or not
     * - firsttitle, the title of the first post
     * - firstpost, the first post (only set if $show_first_posts was true)
     *
     * @param  mixed $name The forum name or an array of forum IDs
     * @param  integer $limit The limit
     * @param  integer $start The start position
     * @param  integer $max_rows The total rows (not a parameter: returns by reference)
     * @param  SHORT_TEXT $filter_topic_title The topic title filter
     * @param  boolean $show_first_posts Whether to show the first posts
     * @param  string $date_key The date key to sort by
     * @set    lasttime firsttime
     * @param  boolean $hot Whether to limit to hot topics
     * @param  SHORT_TEXT $filter_topic_description The topic description filter
     * @return ?array The array of topics (null: error)
     */
    public function show_forum_topics($name, $limit, $start, &$max_rows, $filter_topic_title = '', $show_first_posts = false, $date_key = 'lasttime', $hot = false, $filter_topic_description = '')
    {
        if (is_integer($name)) {
            $id_list = 'forum_id=' . strval($name);
        } elseif (!is_array($name)) {
            $id = $this->forum_id_from_name($name);
            if (is_null($id)) {
                return null;
            }
            $id_list = 'forum_id=' . strval($id);
        } else {
            $id_list = '';
            foreach (array_keys($name) as $id) {
                if ($id_list != '') {
                    $id_list .= ' OR ';
                }
                $id_list .= 'forum_id=' . strval($id);
            }
            if ($id_list == '') {
                return null;
            }
        }

        $topic_filter = ($filter_topic_title != '') ? 'AND title LIKE \'' . db_encode_like($this->ipb_escape($filter_topic_title)) . '\'' : '';
        $rows = $this->connection->query('SELECT * FROM ' . $this->connection->get_table_prefix() . 'topics WHERE (' . $id_list . ') ' . $topic_filter . ' ORDER BY ' . (($date_key == 'lasttime') ? 'last_post' : 'start_date') . ' DESC', $limit, $start);
        $max_rows = $this->connection->query_value_if_there('SELECT COUNT(*) FROM ' . $this->connection->get_table_prefix() . 'topics WHERE (' . $id_list . ') ' . $topic_filter);
        $out = array();
        foreach ($rows as $i => $r) {
            $out[$i] = array();
            $out[$i]['id'] = $r['tid'];
            $out[$i]['num'] = $r['posts'];
            $out[$i]['title'] = $this->ipb_unescape($r['title']);
            $out[$i]['description'] = $this->ipb_unescape($r['title']);
            $out[$i]['firstusername'] = $this->ipb_unescape($r['starter_name']);
            $out[$i]['lastusername'] = $this->ipb_unescape($r['last_poster_name']);
            $out[$i]['firstmemberid'] = $r['starter_id'];
            $out[$i]['lastmemberid'] = $r['last_poster_id'];
            $out[$i]['firsttime'] = $r['start_date'];
            $out[$i]['lasttime'] = $r['last_post'];
            $out[$i]['closed'] = ($r['state'] == 'closed');
            $fp_rows = $this->connection->query('SELECT post_title,post FROM ' . $this->connection->get_table_prefix() . 'posts WHERE post NOT LIKE \'' . db_encode_like(do_lang('SPACER_POST', '', '', '', get_site_default_lang()) . '%') . '\' AND topic_id=' . strval($out[$i]['id']) . ' ORDER BY post_date', 1);
            if (!array_key_exists(0, $fp_rows)) {
                unset($out[$i]);
                continue;
            }
            $out[$i]['firsttitle'] = $this->ipb_unescape($fp_rows[0]['post_title']);
            if ($show_first_posts) {
                $out[$i]['firstpost'] = $fp_rows[0]['post']; // Assumes HTML for posts
            }
        }
        if (count($out) != 0) {
            return $out;
        }
        return null;
    }

    /**
     * Find the base URL to the emoticons.
     *
     * @return URLPATH The base URL
     */
    public function get_emo_dir()
    {
        return get_forum_base_url() . '/html/emoticons/';
    }

    /**
     * Get a map between emoticon codes and templates representing the HTML-image-code for this emoticon. The emoticons presented of course depend on the forum involved.
     *
     * @return array The map
     */
    public function find_emoticons()
    {
        if (!is_null($this->EMOTICON_CACHE)) {
            return $this->EMOTICON_CACHE;
        }
        $rows = $this->connection->query_select('emoticons', array('*'));
        $this->EMOTICON_CACHE = array();
        foreach ($rows as $myrow) {
            $src = $myrow['image'];
            if (url_is_local($src)) {
                $src = $this->get_emo_dir() . $src;
            }
            $this->EMOTICON_CACHE[$this->ipb_unescape($myrow['typed'])] = array('EMOTICON_IMG_CODE_DIR', $src, $myrow['typed']);
        }
        uksort($this->EMOTICON_CACHE, 'strlen_sort');
        $this->EMOTICON_CACHE = array_reverse($this->EMOTICON_CACHE);
        return $this->EMOTICON_CACHE;
    }

    /**
     * Find a list of all forum skins (aka themes).
     *
     * @return array The list of skins
     */
    public function get_skin_list()
    {
        $table = 'skins';
        $codename = 'img_dir';

        $rows = $this->connection->query_select($table, array($codename));
        return collapse_1d_complexity($codename, $rows);
    }

    /**
     * Try to find the theme that the logged-in/guest member is using, and map it to a Composr theme.
     * The themes/map.ini file functions to provide this mapping between forum themes, and Composr themes, and has a slightly different meaning for different forum drivers. For example, some drivers map the forum themes theme directory to the Composr theme name, whilst others made the humanly readeable name.
     *
     * @param  boolean $skip_member_specific Whether to avoid member-specific lookup
     * @return ID_TEXT The theme
     */
    public function _get_theme($skip_member_specific = false)
    {
        $def = '';

        // Load in remapper
        require_code('files');
        $map = file_exists(get_file_base() . '/themes/map.ini') ? better_parse_ini_file(get_file_base() . '/themes/map.ini') : array();

        if (!$skip_member_specific) {
            // Work out
            $member = get_member();
            if ($member > 0) {
                $skin = $this->get_member_row_field($member, 'skin');
            } else {
                $skin = 0;
            }
            if ($skin > 0) { // User has a custom theme
                $ipb = $this->connection->query_select_value('skins', 'img_dir', array('sid' => $skin));
                $def = array_key_exists($ipb, $map) ? $map[$ipb] : $ipb;
            }
        }

        // Look for a skin according to our site name (we bother with this instead of 'default' because Composr itself likes to never choose a theme when forum-theme integration is on: all forum [via map] or all Composr seems cleaner, although it is complex)
        if ((!(strlen($def) > 0)) || (!file_exists(get_custom_file_base() . '/themes/' . $def))) {
            $ipb = $this->connection->query_select_value_if_there('skins', 'img_dir', array('img_dir' => get_site_name()));
            if (!is_null($ipb)) {
                $def = array_key_exists($ipb, $map) ? $map[$ipb] : $ipb;
            }
        }

        // Hmm, just the very-default then
        if ((!(strlen($def) > 0)) || (!file_exists(get_custom_file_base() . '/themes/' . $def))) {
            $ipb = $this->connection->query_select_value('skins', 'img_dir', array('default_set' => 1));
            $def = array_key_exists($ipb, $map) ? $map[$ipb] : $ipb;
        }

        // Default then!
        if ((!(strlen($def) > 0)) || (!file_exists(get_custom_file_base() . '/themes/' . $def))) {
            $def = array_key_exists('default', $map) ? $map['default'] : 'default';
        }

        return $def;
    }

    /**
     * Get an IPB statistic.
     *
     * @param  string $stat The name of the statistic
     * @return mixed The value of the statistic
     */
    protected function _get_stat($stat)
    {
        global $IPB_STATS_CACHE;
        if (!is_null($IPB_STATS_CACHE)) {
            return $IPB_STATS_CACHE[$stat];
        }

        $rows = $this->connection->query_select('stats', array('*'), null, '', 1);
        $IPB_STATS_CACHE = $rows[0];

        return $IPB_STATS_CACHE[$stat];
    }

    /**
     * Get the number of members registered on the forum.
     *
     * @return integer The number of members
     */
    public function get_members()
    {
        return $this->_get_stat('MEM_COUNT');
    }

    /**
     * Get the total topics ever made on the forum.
     *
     * @return integer The number of topics
     */
    public function get_topics()
    {
        return $this->_get_stat('TOTAL_TOPICS');
    }

    /**
     * Get the total posts ever made on the forum.
     *
     * @return integer The number of posts
     */
    public function get_num_forum_posts()
    {
        return $this->_get_stat('TOTAL_REPLIES');
    }

    /**
     * Get the forum usergroup relating to the specified member ID.
     *
     * @param  MEMBER $member The member ID
     * @return array The array of forum usergroups
     */
    protected function _get_members_groups($member)
    {
        $group = $this->get_member_row_field($member, 'mgroup');
        return array($group);
    }

    /**
     * Find if the given member ID and password is valid. If username is NULL, then the member ID is used instead.
     * All authorisation, cookies, and form-logins, are passed through this function.
     * Some forums do cookie logins differently, so a Boolean is passed in to indicate whether it is a cookie login.
     *
     * @param  ?SHORT_TEXT $username The member username (null: don't use this in the authentication - but look it up using the ID if needed)
     * @param  MEMBER $userid The member ID
     * @param  SHORT_TEXT $password_hashed The md5-hashed password
     * @param  string $password_raw The raw password
     * @param  boolean $cookie_login Whether this is a cookie login
     * @return array A map of 'id' and 'error'. If 'id' is NULL, an error occurred and 'error' is set
     */
    public function forum_authorise_login($username, $userid, $password_hashed, $password_raw, $cookie_login = false)
    {
        $out = array();
        $out['id'] = null;

        if (is_null($userid)) {
            $rows = $this->connection->query_select('members', array('*'), array('name' => $this->ipb_escape($username)), '', 1);
            if (array_key_exists(0, $rows)) {
                $this->MEMBER_ROWS_CACHED[$rows[0]['id']] = $rows[0];
            }
        } else {
            $rows = array();
            $rows[0] = $this->get_member_row($userid);
        }

        if (!array_key_exists(0, $rows) || $rows[0] === null) { // All hands to lifeboats
            $out['error'] = do_lang_tempcode('_MEMBER_NO_EXIST', $username);
            return $out;
        }
        $row = $rows[0];
        if ($this->is_banned($row['id'])) { // All hands to the guns
            $out['error'] = do_lang_tempcode('MEMBER_BANNED');
            return $out;
        }
        if ($row['password'] != $password_hashed) {
            $out['error'] = do_lang_tempcode('MEMBER_BAD_PASSWORD');
            return $out;
        }

        $pos = strpos(get_member_cookie(), 'member_id');
        require_code('users_active_actions');
        cms_eatcookie(substr(get_member_cookie(), 0, $pos) . 'session_id');

        $out['id'] = $row['id'];
        return $out;
    }

    /**
     * Gets a whole member row from the database.
     *
     * @param  MEMBER $member The member ID
     * @return ?array The member row (null: no such member)
     */
    public function get_member_row($member)
    {
        if (array_key_exists($member, $this->MEMBER_ROWS_CACHED)) {
            return $this->MEMBER_ROWS_CACHED[$member];
        }

        $rows = $this->connection->query_select('members', array('*'), array('id' => $member), '', 1);

        $this->MEMBER_ROWS_CACHED[$member] = array_key_exists(0, $rows) ? $rows[0] : null;
        return $this->MEMBER_ROWS_CACHED[$member];
    }
}
