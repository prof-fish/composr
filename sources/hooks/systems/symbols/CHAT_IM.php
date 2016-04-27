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
 * @package    chat
 */

/**
 * Hook class.
 */
class Hook_symbol_CHAT_IM
{
    /**
     * Run function for symbol hooks. Searches for tasks to perform.
     *
     * @param  array $param Symbol parameters
     * @return string Result
     */
    public function run($param)
    {
        $value = '';

        if ($GLOBALS['STATIC_TEMPLATE_TEST_MODE']) {
            return $value;
        }

        if ((get_option('sitewide_im') == '1') && (!is_guest()) && ((!array_key_exists(get_session_id(), $GLOBALS['SESSION_CACHE'])) || ($GLOBALS['SESSION_CACHE'][get_session_id()]['session_invisible'] == 0))) {
            require_lang('chat');
            require_css('chat');

            $is_su = $GLOBALS['IS_ACTUALLY_ADMIN'];

            $may_cache = !$is_su;

            require_code('chat_sounds');
            get_effect_settings(true, null, true); // Force pre-load
            global $EFFECT_SETTINGS_ROWS;
            if (count($EFFECT_SETTINGS_ROWS) > 0) {
                $may_cache = false; // Custom sounds for this member
            }

            $_value = null;
            if ($may_cache) {
                $_value = persistent_cache_get('CHAT_IM');
            }

            if ($_value === null) {
                require_code('chat');

                $messages_php = find_script('messages');

                $title = $is_su ? strip_tags(do_lang('SU_CHATTING_AS', escape_html($GLOBALS['FORUM_DRIVER']->get_username(get_member())))) : '__room_name__';

                $chat_sound = static_evaluate_tempcode(get_chat_sound_tpl());

                $im_area_template_a = do_template('CHAT_LOBBY_IM_AREA', array(
                    '_GUID' => '38de4f030d5980790d6d1db1a7e2ff39',
                    'MESSAGES_PHP' => $messages_php,
                    'CHATROOM_ID' => '__room_id__',
                ));
                $im_area_template_b = do_template('CHAT_SITEWIDE_IM_POPUP', array(
                    '_GUID' => 'e520e557f86d0dd4e32d25a208d8f154',
                    'CONTENT' => $im_area_template_a,
                    'CHAT_SOUND' => $chat_sound,
                ));
                $im_area_template_c = do_template('STANDALONE_HTML_WRAP', array(
                    '_GUID' => '5032bfa802af3fe14e610d09078ef849',
                    'CSS' => 'sitewide_im_popup_body',
                    'TITLE' => $title,
                    'TARGET' => '_site_opener',
                    'CONTENT' => $im_area_template_b,
                    'POPUP' => true,
                ));

                $make_friend_url = build_url(array('page' => '_SELF', 'type' => 'friend_add', 'member_id' => '__id__'), '_SELF', null, false, false, true);

                $block_member_url = build_url(array('page' => '_SELF', 'type' => 'blocking_add', 'member_id' => '__id__'), '_SELF', null, false, false, true);

                $profile_url = $GLOBALS['FORUM_DRIVER']->member_profile_url(-100, true);
                $profile_url = str_replace('-100', '__id__', $profile_url);

                $im_participant_template = do_template('CHAT_LOBBY_IM_PARTICIPANT', array(
                    '_GUID' => '0c5e080d0afb29814a6e3059f0204ad1',
                    'PROFILE_URL' => $profile_url,
                    'ID' => '__id__',
                    'CHATROOM_ID' => '__room_id__',
                    'USERNAME' => '__username__',
                    'ONLINE' => '__online__',
                    'AVATAR_URL' => '__avatar_url__',
                    'MAKE_FRIEND_URL' => $make_friend_url,
                    'BLOCK_MEMBER_URL' => $block_member_url,
                ));

                if ($may_cache) {
                    $im_area_template_c = apply_quick_caching($im_area_template_c);
                    $im_participant_template = apply_quick_caching($im_participant_template);
                }

                $_value = do_template('CHAT_SITEWIDE_IM', array(
                    '_GUID' => '5ab0404b3dac4578e8b4be699bd43c95',
                    'IM_AREA_TEMPLATE' => $im_area_template_c,
                    'IM_PARTICIPANT_TEMPLATE' => $im_participant_template,
                    'CHAT_SOUND' => $chat_sound,
                ));

                if ($may_cache) {
                    persistent_cache_set('CHAT_IM', $_value);
                }
            }

            $value = $_value->evaluate();
        }

        return $value;
    }
}
