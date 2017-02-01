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

/*
A note about currencies...

The invoice module is only in the configured site currency. It is intentionally kept very simple.

If you need more sophisticated invoicing then you should override the invoicing code with your own.

The core eCommerce functionality does support multiple currencies.
*/

/**
 * Module page class.
 */
class Module_invoices
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
        $info['version'] = 4;
        $info['locked'] = false;
        $info['update_require_upgrade'] = true;
        return $info;
    }

    /**
     * Uninstall the module.
     */
    public function uninstall()
    {
        $GLOBALS['SITE_DB']->drop_table_if_exists('ecom_invoices');
    }

    /**
     * Install the module.
     *
     * @param  ?integer $upgrade_from What version we're upgrading from (null: new install)
     * @param  ?integer $upgrade_from_hack What hack version we're upgrading from (null: new-install/not-upgrading-from-a-hacked-version)
     */
    public function install($upgrade_from = null, $upgrade_from_hack = null)
    {
        if ($upgrade_from === null) {
            $GLOBALS['SITE_DB']->create_table('ecom_invoices', array(
                'id' => '*AUTO',
                'i_type_code' => 'ID_TEXT',
                'i_member_id' => 'MEMBER',
                'i_state' => 'ID_TEXT', // new|pending|paid|delivered (pending means payment has been requested)
                'i_amount' => 'SHORT_TEXT', // can't always find this from i_type_code
                'i_special' => 'SHORT_TEXT', // depending on i_type_code, would trigger something special such as a key upgrade
                'i_time' => 'TIME',
                'i_note' => 'LONG_TEXT'
            ));
        }

        if (($upgrade_from !== null) && ($upgrade_from < 3)) {
            $GLOBALS['SITE_DB']->create_index('ecom_invoices', 'i_member_id', array('i_member_id'));
        }

        if (($upgrade_from < 4) && ($upgrade_from !== null)) {
            $GLOBALS['SITE_DB']->rename_table('invoices', 'ecom_invoices');
        }
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
        if ((!$check_perms || !is_guest($member_id)) && ($GLOBALS['SITE_DB']->query_select_value('ecom_invoices', 'COUNT(*)', array('i_member_id' => get_member())) > 0)) {
            return array(
                'browse' => array('MY_INVOICES', 'menu/adminzone/audit/ecommerce/invoices'),
            );
        }
        return array();
    }

    public $title;

    /**
     * Module pre-run function. Allows us to know metadata for <head> before we start streaming output.
     *
     * @return ?Tempcode Tempcode indicating some kind of exceptional output (null: none).
     */
    public function pre_run()
    {
        $type = get_param_string('type', 'browse');

        require_lang('ecommerce');

        if ($type == 'browse') {
            $this->title = get_screen_title('MY_INVOICES');
        }

        if ($type == 'pay') {
            $this->title = get_screen_title('MAKE_PAYMENT');
        }

        return null;
    }

    /**
     * Execute the module.
     *
     * @return Tempcode The result of execution.
     */
    public function run()
    {
        require_code('ecommerce');
        require_css('ecommerce');

        // Kill switch
        if ((ecommerce_test_mode()) && (!$GLOBALS['IS_ACTUALLY_ADMIN']) && (!has_privilege(get_member(), 'access_ecommerce_in_test_mode'))) {
            warn_exit(do_lang_tempcode('PURCHASE_DISABLED'));
        }

        if (is_guest()) {
            access_denied('NOT_AS_GUEST');
        }

        $type = get_param_string('type', 'browse');

        if ($type == 'browse') {
            return $this->my();
        }
        if ($type == 'pay') {
            return $this->pay();
        }
        return new Tempcode();
    }

    /**
     * Show my invoices.
     *
     * @return Tempcode The interface.
     */
    public function my()
    {
        $member_id = get_member();
        if (has_privilege(get_member(), 'assume_any_member')) {
            $member_id = get_param_integer('id', $member_id);
        }

        $invoices = array();
        $rows = $GLOBALS['SITE_DB']->query_select('ecom_invoices', array('*'), array('i_member_id' => $member_id), 'ORDER BY i_time');
        foreach ($rows as $row) {
            $type_code = $row['i_type_code'];
            list($details) = find_product_details($type_code);
            if ($details === null) {
                continue;
            }

            $invoice_title = $details['item_name'];
            $time = get_timezoned_date($row['i_time'], true, false, false, true);
            $payable = ($row['i_state'] == 'new');
            $deliverable = ($row['i_state'] == 'paid');
            $state = do_lang('PAYMENT_STATE_' . $row['i_state']);
            $currency = get_option('currency');
            if (perform_local_payment()) {
                $transaction_button = hyperlink(build_url(array('page' => '_SELF', 'type' => 'pay', 'id' => $row['id']), '_SELF'), do_lang_tempcode('MAKE_PAYMENT'), false, false);
            } else {
                $transaction_button = make_transaction_button($type_code, $invoice_title, strval($row['id']), floatval($row['i_amount']), $currency);
            }
            $invoices[] = array(
                'TRANSACTION_BUTTON' => $transaction_button,
                'INVOICE_TITLE' => $invoice_title,
                'INVOICE_ID' => strval($row['id']),
                'AMOUNT' => $row['i_amount'],
                'TIME' => $time,
                'STATE' => $state,
                'DELIVERABLE' => $deliverable,
                'PAYABLE' => $payable,
                'NOTE' => $row['i_note'],
                'TYPE_CODE' => $row['i_type_code'],
            );
        }
        if (count($invoices) == 0) {
            inform_exit(do_lang_tempcode('NO_ENTRIES'));
        }

        return do_template('ECOM_INVOICES_SCREEN', array('_GUID' => '144a893d93090c105eecc48fa58921a7', 'TITLE' => $this->title, 'CURRENCY' => $currency, 'INVOICES' => $invoices));
    }

    /**
     * Pay invoice.
     *
     * @return Tempcode The interface.
     */
    public function pay()
    {
        $id = get_param_integer('id');

        $rows = $GLOBALS['SITE_DB']->query_select('ecom_invoices', array('*'), array('id' => $id), '', 1);
        if (!array_key_exists(0, $rows)) {
            warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
        }
        $row = $rows[0];
        $type_code = $row['i_type_code'];
        list($details) = find_product_details($type_code);
        $invoice_title = $details['item_name'];

        $post_url = build_url(array('page' => 'purchase', 'type' => 'finish', 'type_code' => $type_code), get_module_zone('purchase'));

        $needs_shipping_address = !empty($details['needs_shipping_address']);

        list($fields, $hidden) = get_transaction_form_fields(
            $type_code,
            $invoice_title,
            strval($id),
            floatval($row['i_amount']),
            get_option('currency'),
            0,
            null,
            '',
            get_option('payment_gateway'),
            $needs_shipping_address
        );

        $text = do_lang_tempcode('TRANSACT_INFO');

        return do_template('FORM_SCREEN', array('_GUID' => 'e90a4019b37c8bf5bcb64086416bcfb3', 'TITLE' => $this->title, 'SKIP_WEBSTANDARDS' => '1', 'FIELDS' => $fields, 'URL' => $post_url, 'TEXT' => $text, 'HIDDEN' => $hidden, 'SUBMIT_ICON' => 'menu__rich_content__ecommerce__purchase', 'SUBMIT_NAME' => do_lang_tempcode('MAKE_PAYMENT')));
    }
}
