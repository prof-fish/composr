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
 * @package    ecommerce
 */

/**
 * Hook class.
 */
class Hook_ecommerce_giftr
{
    /**
     * Get the overall categorisation for the products handled by this eCommerce hook.
     *
     * @return ?array A map of product categorisation details (null: disabled).
     */
    public function get_product_category()
    {
        if (!$GLOBALS['SITE_DB']->table_exists('giftr')) {
            return null;
        }

        require_lang('giftr');

        return array(
            'category_name' => do_lang('GIFTR_TITLE'),
            'category_description' => do_lang_tempcode('GIFTS_DESCRIPTION'),
            'category_image_url' => find_theme_image('icons/48x48/menu/giftr'),
        );
    }

    /**
     * Get the products handled by this eCommerce hook.
     *
     * IMPORTANT NOTE TO PROGRAMMERS: This function may depend only on the database, and not on get_member() or any GET/POST values.
     *  Such dependencies will break IPN, which works via a Guest and no dependable environment variables. It would also break manual transactions from the Admin Zone.
     *
     * @param  boolean $site_lang Whether to make sure the language for item_name is the site default language (crucial for when we read/go to third-party sales systems and use the item_name as a key).
     * @param  ?ID_TEXT $search Product being searched for (null: none).
     * @param  boolean $search_item_names Whether $search refers to the item name rather than the product codename.
     * @return array A map of product name to list of product details.
     */
    public function get_products($site_lang = false, $search = null, $search_item_names = false)
    {
        require_lang('giftr');

        $products = array();

        $map = array('enabled' => 1);

        $max_rows = $GLOBALS['SITE_DB']->query_select_value('giftr', 'COUNT(*)', $map);

        $rows = $GLOBALS['SITE_DB']->query_select('giftr g', array('*', '(SELECT COUNT(*) FROM ' . $GLOBALS['SITE_DB']->get_table_prefix() . 'members_gifts m WHERE m.gift_id=g.id) AS popularity'), $map, 'ORDER BY popularity DESC');
        $gifts = array();
        foreach ($rows as $gift) {
            $image_url = $gift['image'];
            if ($image_url != '') {
                if (url_is_local($image_url)) {
                    $image_url = get_custom_base_url() . '/' . $image_url;
                }
            }

            $products['GIFTR_' . strval($gift['id'])] = array(
                'item_name' => do_lang('_GIFT', $gift['name'], null, $site_lang ? get_site_default_lang() : user_lang()),
                'item_description' => do_lang_tempcode('GIFT_DESCRIPTION', escape_html($gift['category']), escape_html(integer_format($gift['popularity'])), escape_html($gift['name'])),
                'item_image_url' => $image_url,

                'type' => PRODUCT_PURCHASE,
                'type_special_details' => array(),

                'price' => null,
                'currency' => get_option('currency'),
                'price_points' => $gift['price'],
                'discount_points__num_points' => null,
                'discount_points__price_reduction' => null,

                'needs_shipping_address' => false,
            );
        }

        return $products;
    }

    /**
     * Check whether the product codename is available for purchase by the member.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  MEMBER $member_id The member we are checking against.
     * @param  integer $req_quantity The number required.
     * @param  boolean $must_be_listed Whether the product must be available for public listing.
     * @return integer The availability code (a ECOMMERCE_PRODUCT_* constant).
     */
    public function is_available($type_code, $member_id, $req_quantity = 1, $must_be_listed = false)
    {
        $gift_id = intval(preg_replace('#^GIFTR\_#', '', $type_code));
        $rows = $GLOBALS['SITE_DB']->query_select('giftr', array('*'), array('id' => $gift_id), '', 1);
        if (!array_key_exists(0, $rows)) {
            return ECOMMERCE_PRODUCT_MISSING;
        }

        return ECOMMERCE_PRODUCT_AVAILABLE;
    }

    /**
     * Get fields that need to be filled in in the purchasing module.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @return ?array A triple: The fields (null: none), The text (null: none), The JavaScript (null: none).
     */
    public function get_needed_fields($type_code)
    {
        require_lang('giftr');

        $fields = new Tempcode();
        $fields->attach(form_input_username(do_lang_tempcode('TO_USERNAME'), do_lang_tempcode('DESCRIPTION_MEMBER_TO_GIVE'), 'username', get_param_string('username', ''), true));
        $fields->attach(form_input_text(do_lang_tempcode('MESSAGE'), do_lang_tempcode('DESCRIPTION_GIFT_MESSAGE'), 'gift_message', '', true));
        $fields->attach(form_input_tick(do_lang_tempcode('ANON'), do_lang_tempcode('DESCRIPTION_ANONYMOUS'), 'anonymous', false));

        return array($fields, null, null);
    }

    /**
     * Get the filled in fields and do something with them.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @return array A pair: The purchase ID, a confirmation box to show (null for no specific confirmation).
     */
    public function handle_needed_fields($type_code)
    {
        $to_member = post_param_string('username', '');
        $gift_message = post_param_string('gift_message', '');
        $anonymous = post_param_integer('anonymous', 0);

        $e_details = json_encode(array(get_member(), $to_member, $gift_message, $anonymous));
        $purchase_id = strval($GLOBALS['SITE_DB']->query_insert('ecom_sales_expecting', array('e_details' => $e_details, 'e_time' => time()), true));

        return array($purchase_id, null);
    }

    /**
     * Handling of a product purchase change state.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @param  array $details Details of the product, with added keys: TXN_ID, PAYMENT_STATUS, ORDER_STATUS.
     */
    public function actualiser($type_code, $purchase_id, $details)
    {
        if ($details['PAYMENT_STATUS'] != 'Completed') {
            return;
        }

        require_lang('giftr');

        $gift_id = intval(preg_replace('#^GIFTR\_#', '', $type_code));

        $e_details = $GLOBALS['SITE_DB']->query_select_value('ecom_sales_expecting', 'e_details', array('id' => intval($purchase_id)));
        list($from_member_id, $to_member, $gift_message, $anonymous) = json_decode($e_details);

        $member_rows = $GLOBALS['FORUM_DB']->query_select('f_members', array('*'), array('m_username' => $to_member), '', 1);
        if (array_key_exists(0, $member_rows)) {
            $member_row = $member_rows[0];
            $to_member_id = $member_row['id'];

            $gift_rows = $GLOBALS['SITE_DB']->query_select('giftr', array('*'), array('id' => $gift_id), '', 1);
            if (array_key_exists(0, $gift_rows)) {
                $gift_row = $gift_rows[0];
                $gift_name = $gift_row['name'];
                $gift_image_url = get_custom_base_url() . '/' . $gift_row['image'];
                $gift_row_id = $GLOBALS['SITE_DB']->query_insert('members_gifts', array('to_member_id' => $to_member_id, 'from_member_id' => $from_member_id, 'gift_id' => $gift_id, 'add_time' => time(), 'is_anonymous' => $anonymous, 'gift_message' => $gift_message), true);

                $GLOBALS['SITE_DB']->query_insert('ecom_sales', array('date_and_time' => time(), 'member_id' => $from_member_id, 'details' => $gift_name, 'details2' => $GLOBALS['FORUM_DRIVER']->get_username($to_member_id), 'transaction_id' => $details['TXN_ID']));

                // Send notification to recipient
                require_code('notifications');
                $subject = do_lang('GOT_GIFT', null, null, null, get_lang($to_member_id));
                if ($anonymous == 0) {
                    $sender_url = $GLOBALS['FORUM_DRIVER']->member_profile_url($from_member_id);
                    $sender_displayname = $GLOBALS['FORUM_DRIVER']->get_username($from_member_id, true);
                    $sender_username = $GLOBALS['FORUM_DRIVER']->get_username($from_member_id);
                    $private_topic_url = $GLOBALS['FORUM_DRIVER']->member_pm_url($from_member_id);

                    $message = do_notification_lang('GIFT_EXPLANATION_MAIL', comcode_escape($sender_displayname), comcode_escape($gift_name), array($sender_url, $gift_image_url, $gift_message, $private_topic_url, comcode_escape($sender_username)), get_lang($to_member_id));

                    dispatch_notification('gift', null, $subject, $message, array($to_member_id), $from_member_id, 3, false, false, null, null, '', '', '', '', null, true);
                } else {
                    $message = do_notification_lang('GIFT_EXPLANATION_ANONYMOUS_MAIL', comcode_escape($gift_name), $gift_image_url, $gift_message, get_lang($to_member_id));

                    dispatch_notification('gift', null, $subject, $message, array($to_member_id), A_FROM_SYSTEM_UNPRIVILEGED);
                }
            } else {
                warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
            }
        } else {
            warn_exit(do_lang_tempcode('NO_MEMBER_SELECTED'));
        }
    }

    /**
     * Get the member who made the purchase.
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @return ?MEMBER The member ID (null: none).
     */
    public function member_for($type_code, $purchase_id)
    {
        $e_details = $GLOBALS['SITE_DB']->query_select_value('ecom_sales_expecting', 'e_details', array('id' => intval($purchase_id)));
        list($from_member_id) = json_decode($e_details);
        return $from_member_id;
    }
}
