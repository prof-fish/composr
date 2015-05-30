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
 * Find whether a member's field must be filled in.
 *
 * @param  ?MEMBER $member_id The member being edited (null: new member).
 * @param  string $field_class Special code representing what kind of field it is.
 * @set email_address dob required_cpfs
 * @param  ?string $current_value The value the field has now (null: lookup from member record; cannot do this for a CPF).
 * @param  ?MEMBER $editing_member The member doing the adding/editing operation (null: current member).
 * @return boolean Whether the field must be filled in.
 */
function member_field_is_required($member_id, $field_class, $current_value = null, $editing_member = null)
{
    if (($field_class == 'dob') && (get_option('dobs') == '0')) {
        return false;
    }

    if (is_null($editing_member)) {
        $editing_member = get_member();
    }

    if (has_privilege($editing_member, 'bypass_' . $field_class)) {
        return false;
    }

    // Existing member, allow blank to persist if such a privilege
    if (!is_null($member_id)) {
        if (is_null($current_value)) {
            $current_value = $GLOBALS['FORUM_DRIVER']->get_member_row_field($member_id, ($field_class == 'dob') ? ('m_' . $field_class . '_day') : ('m_' . $field_class));
        }

        if ((empty($current_value)) && (has_privilege($editing_member, 'bypass_' . $field_class . '_if_already_empty'))) {
            return false;
        }
    }

    return true;
}

/**
 * Add a member.
 *
 * @param  SHORT_TEXT $username The username.
 * @param  SHORT_TEXT $password The password.
 * @param  SHORT_TEXT $email_address The e-mail address.
 * @param  ?array $secondary_groups A list of secondary usergroups (null: default/current usergroups).
 * @param  ?integer $dob_day Day of date of birth (null: unknown).
 * @param  ?integer $dob_month Month of date of birth (null: unknown).
 * @param  ?integer $dob_year Year of date of birth (null: unknown).
 * @param  array $custom_fields A map of custom field values (fieldID=>value).
 * @param  ?ID_TEXT $timezone The member timezone (null: auto-detect).
 * @param  ?GROUP $primary_group The member's primary (null: default).
 * @param  BINARY $validated Whether the profile has been validated.
 * @param  ?TIME $join_time When the member joined (null: now).
 * @param  ?TIME $last_visit_time When the member last visited (null: now).
 * @param  ID_TEXT $theme The member's default theme.
 * @param  ?URLPATH $avatar_url The URL to the member's avatar (blank: none) (null: choose one automatically).
 * @param  LONG_TEXT $signature The member's signature (blank: none).
 * @param  BINARY $is_perm_banned Whether the member is permanently banned.
 * @param  ?BINARY $preview_posts Whether posts are previewed before they are made (null: calculate automatically).
 * @param  BINARY $reveal_age Whether the member's age may be shown.
 * @param  SHORT_TEXT $title The member's title (blank: get from primary).
 * @param  URLPATH $photo_url The URL to the member's photo (blank: none).
 * @param  URLPATH $photo_thumb_url The URL to the member's photo thumbnail (blank: none).
 * @param  BINARY $views_signatures Whether the member sees signatures in posts.
 * @param  ?BINARY $auto_monitor_contrib_content Whether the member automatically is enabled for notifications for content they contribute to (null: get default from config).
 * @param  ?LANGUAGE_NAME $language The member's language (null: auto detect).
 * @param  BINARY $allow_emails Whether the member allows e-mails via the site.
 * @param  BINARY $allow_emails_from_staff Whether the member allows e-mails from staff via the site.
 * @param  ?IP $ip_address The member's IP address (null: IP address of current user).
 * @param  SHORT_TEXT $validated_email_confirm_code The code required before the account becomes active (blank: already entered).
 * @param  boolean $check_correctness Whether to check details for correctness.
 * @param  ?ID_TEXT $password_compatibility_scheme The compatibility scheme that the password operates in (blank: none) (null: none [meaning normal Composr salted style] or plain, depending on whether passwords are encrypted).
 * @param  SHORT_TEXT $salt The password salt (blank: password compatibility scheme does not use a salt / auto-generate).
 * @param  ?TIME $last_submit_time The time the member last made a submission (null: set to now).
 * @param  ?AUTO_LINK $id Force an ID (null: don't force an ID)
 * @param  BINARY $highlighted_name Whether the member username will be highlighted.
 * @param  SHORT_TEXT $pt_allow Usergroups that may PT the member.
 * @param  LONG_TEXT $pt_rules_text Rules that other members must agree to before they may start a PT with the member.
 * @param  ?TIME $on_probation_until When the member is on probation until (null: not on probation)
 * @return AUTO_LINK The ID of the new member.
 */
function cns_make_member($username, $password, $email_address, $secondary_groups, $dob_day, $dob_month, $dob_year, $custom_fields, $timezone = null, $primary_group = null, $validated = 1, $join_time = null, $last_visit_time = null, $theme = '', $avatar_url = null, $signature = '', $is_perm_banned = 0, $preview_posts = null, $reveal_age = 0, $title = '', $photo_url = '', $photo_thumb_url = '', $views_signatures = 1, $auto_monitor_contrib_content = null, $language = null, $allow_emails = 1, $allow_emails_from_staff = 1, $ip_address = null, $validated_email_confirm_code = '', $check_correctness = true, $password_compatibility_scheme = null, $salt = '', $last_submit_time = null, $id = null, $highlighted_name = 0, $pt_allow = '*', $pt_rules_text = '', $on_probation_until = null)
{
    require_code('form_templates');

    $preview_posts = take_param_int_modeavg($preview_posts, 'm_preview_posts', 'f_members', 0);
    if (is_null($auto_monitor_contrib_content)) {
        $auto_monitor_contrib_content = (get_option('allow_auto_notifications') == '0') ? 0 : 1;
    }

    if (is_null($password_compatibility_scheme)) {
        if (get_value('no_password_hashing') === '1' || $password == ''/*Makes debugging easier or allows basic testing to work on PHP installs with broken OpenSSL*/) {
            $password_compatibility_scheme = 'plain';
        } else {
            $password_compatibility_scheme = '';
        }
    }
    if (is_null($language)) {
        $language = '';
    }
    if (is_null($signature)) {
        $signature = '';
    }
    if (is_null($title)) {
        $title = '';
    }
    if (is_null($timezone)) {
        $timezone = get_site_timezone();
    }
    if (is_null($allow_emails)) {
        $allow_emails = 1;
    }
    if (is_null($allow_emails_from_staff)) {
        $allow_emails_from_staff = 1;
    }
    if (is_null($avatar_url)) {
        if (($GLOBALS['IN_MINIKERNEL_VERSION']) || (!addon_installed('cns_member_avatars'))) {
            $avatar_url = '';
        } else {
            if ((get_option('random_avatars') == '1') && (!running_script('stress_test_loader'))) {
                require_code('themes2');
                $codes = get_all_image_ids_type('cns_default_avatars/default_set', false, $GLOBALS['FORUM_DB']);
                shuffle($codes);
                $results = array();
                foreach ($codes as $code) {
                    if ($code == 'system') {
                        continue;
                    }

                    $count = $GLOBALS['FORUM_DB']->query_select_value_if_there('f_members', 'SUM(m_cache_num_posts)', array('m_avatar_url' => find_theme_image($code, false, true)));
                    if (is_null($count)) {
                        $count = 0;
                    }
                    $results[$code] = $count;
                }
                @asort($results); // @'d as type checker fails for some odd reason
                $found_avatars = array_keys($results);
                $avatar_url = find_theme_image(array_shift($found_avatars), true, true);
            }

            if (is_null($avatar_url)) {
                $GLOBALS['SITE_DB']->query_delete('theme_images', array('id' => 'cns_default_avatars/default', 'path' => '')); // In case failure cached, gets very confusing
                $avatar_url = find_theme_image('cns_default_avatars/default', true, true);
                if (is_null($avatar_url)) {
                    $avatar_url = '';
                }
            }
        }
    }

    if ($check_correctness) {
        if (!in_array($password_compatibility_scheme, array('ldap', 'httpauth'))) {
            cns_check_name_valid($username, null, ($password_compatibility_scheme == '') ? $password : null);
        }
        if ((!function_exists('has_actual_page_access')) || (!has_actual_page_access(get_member(), 'admin_cns_members'))) {
            require_code('type_sanitisation');
            if ((!is_email_address($email_address)) && ($email_address != '')) {
                warn_exit(do_lang_tempcode('_INVALID_EMAIL_ADDRESS', escape_html($email_address)));
            }
        }
    }

    require_code('cns_members');
    require_code('cns_groups');
    if (is_null($last_submit_time)) {
        $last_submit_time = time();
    }
    if (is_null($join_time)) {
        $join_time = time();
    }
    if (is_null($last_visit_time)) {
        $last_visit_time = time();
    }
    if (is_null($primary_group)) {
        $primary_group = get_first_default_group(); // This is members
    }
    if (is_null($secondary_groups)) {
        $secondary_groups = cns_get_all_default_groups(false);
    }
    foreach ($secondary_groups as $_g_id => $g_id) {
        if ($g_id == $primary_group) {
            unset($secondary_groups[$_g_id]);
        }
    }
    if (is_null($ip_address)) {
        $ip_address = get_ip_address();
    }

    if ((($password_compatibility_scheme == '') || ($password_compatibility_scheme == 'temporary')) && (get_value('no_password_hashing') === '1')) {
        $password_compatibility_scheme = 'plain';
        $salt = '';
    }

    if (($salt == '') && (($password_compatibility_scheme == '') || ($password_compatibility_scheme == 'temporary'))) {
        require_code('crypt');
        $salt = produce_salt();
        $password_salted = ratchet_hash($password, $salt);
    } else {
        $password_salted = $password;
    }

    // Supplement custom field values given with defaults, and check constraints
    $all_fields = list_to_map('id', cns_get_all_custom_fields_match(array_merge(array($primary_group), $secondary_groups)));
    require_code('fields');
    foreach ($all_fields as $field) {
        $field_id = $field['id'];

        if (array_key_exists($field_id, $custom_fields)) {
            if (($check_correctness) && ($field[array_key_exists('cf_show_on_join_form', $field) ? 'cf_show_on_join_form' : 'cf_required'] == 0) && ($field['cf_owner_set'] == 0) && (!has_actual_page_access(get_member(), 'admin_cns_members'))) {
                access_denied('I_ERROR');
            }
        }
    }

    if (!addon_installed('unvalidated')) {
        $validated = 1;
    }
    $map = array(
        'm_username' => $username,
        'm_pass_hash_salted' => $password_salted,
        'm_pass_salt' => $salt,
        'm_theme' => $theme,
        'm_avatar_url' => $avatar_url,
        'm_validated' => $validated,
        'm_validated_email_confirm_code' => $validated_email_confirm_code,
        'm_cache_num_posts' => 0,
        'm_cache_warnings' => 0,
        'm_max_email_attach_size_mb' => 5,
        'm_join_time' => $join_time,
        'm_timezone_offset' => $timezone,
        'm_primary_group' => $primary_group,
        'm_last_visit_time' => $last_visit_time,
        'm_last_submit_time' => $last_submit_time,
        'm_is_perm_banned' => $is_perm_banned,
        'm_preview_posts' => $preview_posts,
        'm_dob_day' => $dob_day,
        'm_dob_month' => $dob_month,
        'm_dob_year' => $dob_year,
        'm_reveal_age' => $reveal_age,
        'm_email_address' => $email_address,
        'm_title' => $title,
        'm_photo_url' => $photo_url,
        'm_photo_thumb_url' => $photo_thumb_url,
        'm_views_signatures' => $views_signatures,
        'm_auto_monitor_contrib_content' => $auto_monitor_contrib_content,
        'm_highlighted_name' => $highlighted_name,
        'm_pt_allow' => $pt_allow,
        'm_language' => $language,
        'm_ip_address' => $ip_address,
        'm_allow_emails' => $allow_emails,
        'm_allow_emails_from_staff' => $allow_emails_from_staff,
        'm_password_change_code' => '',
        'm_password_compat_scheme' => $password_compatibility_scheme,
        'm_on_probation_until' => $on_probation_until,
        'm_profile_views' => 0,
        'm_total_sessions' => 0,
    );
    $map += insert_lang_comcode('m_signature', $signature, 4, $GLOBALS['FORUM_DB']);
    $map += insert_lang_comcode('m_pt_rules_text', $pt_rules_text, 4, $GLOBALS['FORUM_DB']);
    if (!is_null($id)) {
        $map['id'] = $id;
    }
    $member_id = $GLOBALS['FORUM_DB']->query_insert('f_members', $map, true);

    if (get_option('signup_fullname') == '1') {
        $GLOBALS['FORUM_DRIVER']->set_custom_field($id, 'fullname', preg_replace('# \(\d+\)$#', '', $username));
    }

    if ($check_correctness) {
        // If it was an invite/recommendation, award the referrer
        if (addon_installed('recommend')) {
            $inviter = $GLOBALS['FORUM_DB']->query_select_value_if_there('f_invites', 'i_inviter', array('i_email_address' => $email_address), 'ORDER BY i_time');
            if (!is_null($inviter)) {
                if (addon_installed('points')) {
                    require_code('points2');
                    require_lang('recommend');
                    system_gift_transfer(do_lang('RECOMMEND_SITE_TO', $username, get_site_name()), intval(get_option('points_RECOMMEND_SITE')), $inviter);
                }
                if (addon_installed('chat')) {
                    require_code('chat2');
                    friend_add($inviter, $member_id);
                    friend_add($member_id, $inviter);
                }
            }
        }
    }

    $value = mixed();

    // Store custom fields
    $row = array('mf_member_id' => $member_id);
    $all_fields_types = collapse_2d_complexity('id', 'cf_type', $all_fields);
    foreach ($custom_fields as $field_num => $value) {
        if (!array_key_exists($field_num, $all_fields_types)) {
            continue; // Trying to set a field we're not allowed to (doesn't apply to our group)
        }

        $row['field_' . strval($field_num)] = $value;
    }

    // Set custom field row
    $all_fields_regardless = $GLOBALS['FORUM_DB']->query_select('f_custom_fields', array('id', 'cf_type', 'cf_default'));
    foreach ($all_fields_regardless as $field) {
        $ob = get_fields_hook($field['cf_type']);
        list(, , $storage_type) = $ob->get_field_value_row_bits($field);

        if (array_key_exists('field_' . strval($field['id']), $row)) {
            $value = $row['field_' . strval($field['id'])];
        } else {
            $value = $field['cf_default'];
            if (strpos($value, '|') !== false) {
                $value = preg_replace('#\|.*$#', '', $value);
            }
        }

        $row['field_' . strval($field['id'])] = $value;
        if (is_string($value)) { // Should not normally be needed, but the grabbing from cf_default further up is not converted yet
            switch ($storage_type) {
                case 'short_trans':
                case 'long_trans':
                    $row = insert_lang_comcode('field_' . strval($field['id']), $value, 3, $GLOBALS['FORUM_DB']) + $row;
                    break;
                case 'integer':
                    $row['field_' . strval($field['id'])] = ($value == '') ? null : intval($value);
                    break;
                case 'float':
                    $row['field_' . strval($field['id'])] = ($value == '') ? null : floatval($value);
                    break;
            }
        }
    }
    $GLOBALS['FORUM_DB']->query_insert('f_member_custom_fields', $row);

    // Any secondary work...

    foreach ($secondary_groups as $g) {
        if ($g != $primary_group) {
            $GLOBALS['FORUM_DB']->query_delete('f_group_members', array('gm_member_id' => $member_id, 'gm_group_id' => $g), '', 1);
            $GLOBALS['FORUM_DB']->query_insert('f_group_members', array(
                'gm_group_id' => $g,
                'gm_member_id' => $member_id,
                'gm_validated' => 1
            ));
        }
    }

    $GLOBALS['FORUM_DB']->query_insert('f_group_join_log', array(
        'member_id' => $member_id,
        'usergroup_id' => $primary_group,
        'join_time' => time()
    ));

    if ($check_correctness) {
        if (function_exists('decache')) {
            decache('side_stats');
        }
        delete_value('cns_newest_member_id');
        delete_value('cns_newest_member_username');
    }

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resourcefs_moniker('member', strval($member_id), null, null, true);
    }

    $password_change_days = get_option('password_change_days');
    if (intval($password_change_days) > 0) {
        if ($password_compatibility_scheme == '') {
            require_code('password_rules');
            bump_password_change_date($member_id, $password, $password_salted, $salt);
        }
    }

    require_code('member_mentions');
    dispatch_member_mention_notifications('member', strval($member_id));

    if (function_exists('decache')) {
        decache('main_members');
    }

    return $member_id;
}

/**
 * Make a custom profile field from one of the predefined templates (this is often used by importers).
 *
 * @param  ID_TEXT $type The identifier of the boiler custom profile field.
 * @return AUTO_LINK The ID of the new custom profile field.
 */
function cns_make_boiler_custom_field($type)
{
    $_type = 'long_trans';

    if (substr($type, 0, 3) == 'im_' || substr($type, 0, 3) == 'sn_') {
        $_type = 'short_text';
    } elseif ($type == 'location') {
        $_type = 'short_text';
    } elseif ($type == 'occupation') {
        $_type = 'short_text';
    } elseif ($type == 'website') {
        $_type = 'short_trans';
    }

    $public_view = 1;
    $owner_view = 1;
    $owner_set = 1;
    $required = 0;
    $show_in_posts = 0;
    $show_in_post_previews = 0;

    if ($type == 'staff_notes') {
        $public_view = 0;
        $owner_view = 0;
        $owner_set = 0;
    }

    if ($type == 'interests' || $type == 'location') {
        $show_in_posts = 1;
        $show_in_post_previews = 1;
    }

    global $CUSTOM_FIELD_CACHE;
    $CUSTOM_FIELD_CACHE = array();

    if (substr($type, 0, 4) == 'cms_') {
        $title = do_lang('SPECIAL_CPF__' . $type);
        $description = '';
    } else {
        $title = do_lang('DEFAULT_CPF_' . $type . '_NAME');
        $description = do_lang('DEFAULT_CPF_' . $type . '_DESCRIPTION');
    }

    return cns_make_custom_field($title, 0, $description, '', $public_view, $owner_view, $owner_set, 0, $_type, $required, $show_in_posts, $show_in_post_previews, null, '', 0, '', true);
}

/**
 * Find how to store a field in the database.
 *
 * @param  ID_TEXT $type The field type.
 * @return array A pair: the DB field type, whether to index.
 */
function get_cpf_storage_for($type)
{
    require_code('fields');
    $ob = get_fields_hook($type);
    list(, , $storage_type) = $ob->get_field_value_row_bits(array('id' => null, 'cf_type' => $type, 'cf_default' => ''));
    $_type = 'SHORT_TEXT';
    switch ($storage_type) {
        case 'short_trans':
            $_type = 'SHORT_TRANS__COMCODE';
            break;
        case 'long_trans':
            $_type = 'LONG_TRANS__COMCODE';
            break;
        case 'long':
            $_type = 'LONG_TEXT';
            break;
        case 'integer':
            $_type = '?INTEGER';
            break;
        case 'float':
            $_type = '?REAL';
            break;
    }

    $index = true;
    switch ($type) {
        case 'short_trans':
        case 'short_trans_multi':
        case 'long_trans':
        case 'posting_field':
        case 'tick':
        case 'float':
        case 'color':
        case 'content_link':
        case 'date':
        case 'just_date':
        case 'just_time':
        case 'picture':
        case 'password':
        case 'page_link':
        case 'upload':
            $index = false;
            break;
    }

    return array($_type, $index);
}

/**
 * Make a custom profile field.
 *
 * @param  SHORT_TEXT $name Name of the field.
 * @param  BINARY $locked Whether the field is locked (i.e. cannot be deleted from the system).
 * @param  SHORT_TEXT $description Description of the field.
 * @param  LONG_TEXT $default The default value for the field.
 * @param  BINARY $public_view Whether the field is publicly viewable.
 * @param  BINARY $owner_view Whether the field is viewable by the owner.
 * @param  BINARY $owner_set Whether the field may be set by the owner.
 * @param  BINARY $encrypted Whether the field is encrypted.
 * @param  ID_TEXT $type The type of the field.
 * @set    short_text long_text short_trans long_trans integer upload picture url list tick float
 * @param  BINARY $required Whether it is required that every member have this field filled in.
 * @param  BINARY $show_in_posts Whether this field is shown in posts and places where member details are highlighted (such as an image in a member gallery).
 * @param  BINARY $show_in_post_previews Whether this field is shown in preview places, such as in the forum member tooltip.
 * @param  ?integer $order The order of this field relative to other fields (null: next).
 * @param  LONG_TEXT $only_group The usergroups that this field is confined to (comma-separated list).
 * @param  BINARY $show_on_join_form Whether the field is to be shown on the join form
 * @param  SHORT_TEXT $options Field options
 * @param  boolean $no_name_dupe Whether to check that no field has this name already.
 * @return AUTO_LINK The ID of the new custom profile field.
 */
function cns_make_custom_field($name, $locked = 0, $description = '', $default = '', $public_view = 0, $owner_view = 0, $owner_set = 0, $encrypted = 0, $type = 'long_text', $required = 0, $show_in_posts = 0, $show_in_post_previews = 0, $order = null, $only_group = '', $show_on_join_form = 0, $options = '', $no_name_dupe = false)
{
    require_code('global4');
    prevent_double_submit('ADD_CUSTOM_PROFILE_FIELD', null, $name);

    $dbs_back = $GLOBALS['NO_DB_SCOPE_CHECK'];
    $GLOBALS['NO_DB_SCOPE_CHECK'] = true;

    if ($only_group == '-1') {
        $only_group = '';
    }

    // Can't have publicly-viewable encrypted fields
    require_code('encryption');
    if ($encrypted == 1) {
        $public_view = 0;
    }

    if ($no_name_dupe) {
        $test = $GLOBALS['FORUM_DB']->query_select_value_if_there('f_custom_fields', 'id', array($GLOBALS['FORUM_DB']->translate_field_ref('cf_name') => $name));
        if (!is_null($test)) {
            $GLOBALS['NO_DB_SCOPE_CHECK'] = $dbs_back;
            return $test;
        }
    }

    if (is_null($order)) {
        $order = $GLOBALS['FORUM_DB']->query_select_value('f_custom_fields', 'MAX(cf_order)');
        if (is_null($order)) {
            $order = 0;
        } else {
            $order++;
        }
    }

    $map = array(
        'cf_locked' => $locked,
        'cf_default' => $default,
        'cf_public_view' => $public_view,
        'cf_owner_view' => $owner_view,
        'cf_owner_set' => $owner_set,
        'cf_type' => $type,
        'cf_required' => $required,
        'cf_show_in_posts' => $show_in_posts,
        'cf_show_in_post_previews' => $show_in_post_previews,
        'cf_order' => $order,
        'cf_only_group' => $only_group,
        'cf_show_on_join_form' => $show_on_join_form,
        'cf_options' => $options,
    );
    $map += insert_lang('cf_name', $name, 2, $GLOBALS['FORUM_DB']);
    $map += insert_lang('cf_description', $description, 2, $GLOBALS['FORUM_DB']);
    $id = $GLOBALS['FORUM_DB']->query_insert('f_custom_fields', $map + array('cf_encrypted' => $encrypted), true, true);
    if (is_null($id)) {
        $id = $GLOBALS['FORUM_DB']->query_insert('f_custom_fields', $map, true); // Still upgrading, cf_encrypted does not exist yet
    }

    list($_type, $index) = get_cpf_storage_for($type);

    require_code('database_action');
    $GLOBALS['FORUM_DB']->add_table_field('f_member_custom_fields', 'field_' . strval($id), $_type); // Default will be made explicit when we insert rows
    $indices_count = $GLOBALS['FORUM_DB']->query_select_value('db_meta_indices', 'COUNT(*)', array('i_table' => 'f_member_custom_fields'));
    if ($indices_count < 60) { // Could be 64 but trying to be careful here...
        if ($index) {
            if ($_type != 'LONG_TEXT') {
                $GLOBALS['FORUM_DB']->create_index('f_member_custom_fields', 'mcf' . strval($id), array('field_' . strval($id)), 'mf_member_id');
            }
            if (strpos($_type, '_TEXT') !== false) {
                $GLOBALS['FORUM_DB']->create_index('f_member_custom_fields', '#mcf_ft_' . strval($id), array('field_' . strval($id)), 'mf_member_id');
            }
        } elseif ((strpos($type, 'trans') !== false) || ($type == 'posting_field')) { // for efficient joins
            $GLOBALS['FORUM_DB']->create_index('f_member_custom_fields', 'mcf' . strval($id), array('field_' . strval($id)), 'mf_member_id');
        }
    }

    log_it('ADD_CUSTOM_PROFILE_FIELD', strval($id), $name);

    if ((addon_installed('commandr')) && (!running_script('install'))) {
        require_code('resource_fs');
        generate_resourcefs_moniker('cpf', strval($id), null, null, true);
    }

    if (function_exists('decache')) {
        decache('main_members');
    }

    if (function_exists('persistent_cache_delete')) {
        persistent_cache_delete('CUSTOM_FIELD_CACHE');
        persistent_cache_delete('LIST_CPFS');
    }

    $GLOBALS['NO_DB_SCOPE_CHECK'] = $dbs_back;
    return $id;
}
