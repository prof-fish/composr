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
 * @package    core
 */

/**
 * Hook class.
 */
class Hook_check_base_url
{
    /**
     * Check various input var restrictions.
     *
     * @return array List of warnings
     */
    public function run()
    {
        $warning = array();

        global $HTTP_MESSAGE;

        if (file_exists(get_file_base() . '/uploads/index.html')) {
            $test_url = get_base_url() . '/uploads/index.html'; // Should normally exist, simple static URL call
        } else {
            $test_url = static_evaluate_tempcode(build_url(array('page' => ''), '', null, false, false, true)); // But this definitely must exist
        }
        $test = http_download_file($test_url, 0, false, true); // Should return a 200 blank, not an HTTP error or a redirect; actual data would be a Composr error

        if (
            (is_null($test)) &&
            ($HTTP_MESSAGE !== '200') && ($HTTP_MESSAGE !== '401') && ((!is_file(get_file_base() . '/install.php')) || ($HTTP_MESSAGE !== '500'))
        ) {
            if ((($GLOBALS['HTTP_MESSAGE'] == 'no-data') || (strpos($GLOBALS['HTTP_MESSAGE'], 'Connection refused') !== false)) && ((running_script('install')) || (get_option('ip_forwarding') == '0') || (get_option('ip_forwarding') == ''))) {
                $warning[] = do_lang_tempcode('config:ENABLE_IP_FORWARDING', do_lang('config:IP_FORWARDING'));
            } else {
                $warning[] = do_lang_tempcode((strpos(get_base_url(), '://www.') !== false) ? 'HTTP_REDIRECT_PROBLEM_WITHWWW' : 'HTTP_REDIRECT_PROBLEM_WITHOUTWWW', escape_html(get_base_url() . '/config_editor.php'));
            }
        }

        return $warning;
    }
}
