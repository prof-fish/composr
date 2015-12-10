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
 * @package    core
 */

/**
 * Hook class.
 */
class Hook_commandr_fs_extended_member__group_timeouts
{
    /**
     * Read a virtual property for a member file.
     *
     * @param  MEMBER $member_id The member ID
     * @return mixed The data
     */
    public function read_property($member_id)
    {
        return table_to_portable_rows('f_group_member_timeouts', array(), array('member_id' => $member_id));
    }

    /**
     * Read a virtual property for a member file.
     *
     * @param  MEMBER $member_id The member ID
     * @param  mixed $data The data
     */
    public function write_property($member_id, $data)
    {
        return table_from_portable_rows('f_group_member_timeouts', $data, array('member_id' => $member_id), TABLE_REPLACE_MODE_BY_EXTRA_FIELD_DATA);
    }
}
