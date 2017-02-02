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
class Hook_payment_gateway_worldpay
{
    // This is the Hosted Payment Pages API http://support.worldpay.com/support/kb/gg/hpp/Content/Home.htm
    // Requires:
    //  the "Payment Response URL" set in control panel should be set to "http://<WPDISPLAY ITEM=MC_callback>"
    //  the "Payment Response enabled?" and "Enable Recurring Payment Response" and "Enable the Shopper Response" should all be ticked (checked)
    //  the "Payment Response password" is the Composr "Callback password" option; it may be blank
    //  the "Installation ID" (a number given to you) is the Composr "Gateway username" option and also "Testing mode gateway username" option (it's all the same installation ID)
    //  the "MD5 secret for transactions" is the Composr "Gateway digest code" option; it may be blank
    //  the account must be set as 'live' in control panel once testing is done
    //  the "Shopper Redirect URL" should be left blank - arbitrary URLs are not supported, and Composr automatically injects a redirect response into Payment Response URL
    //  Logos, refund policies, and contact details [e-mail, phone, postal], may need coding into the templates (Worldpay have policies and checks). ECOM_LOGOS_WORLDPAY.tpl is included into the payment process automatically and does much of this
    //  FuturePay must be enabled for subscriptions to work (contact WorldPay about it)

    /**
     * Get a standardised config map.
     *
     * @return array The config
     */
    public function get_config()
    {
        return array(
            'supports_remote_memo' => false,
        );
    }

    /**
     * Find a transaction fee from a transaction amount. Regular fees aren't taken into account.
     *
     * @param  REAL $amount A transaction amount.
     * @return REAL The fee.
     */
    public function get_transaction_fee($amount)
    {
        return 0.045 * $amount; // for credit card. Debit card is a flat 50p
    }

    /**
     * Get the gateway username.
     *
     * @return string The answer.
     */
    protected function _get_username()
    {
        return ecommerce_test_mode() ? get_option('payment_gateway_test_username') : get_option('payment_gateway_username');
    }

    /**
     * Get the remote form URL.
     *
     * @return URLPATH The remote form URL.
     */
    protected function _get_remote_form_url()
    {
        return 'https://' . (ecommerce_test_mode() ? 'select-test' : 'select') . '.worldpay.com/wcc/purchase';
    }

    /**
     * Get the card/gateway logos and other gateway-required details.
     *
     * @return Tempcode The stuff.
     */
    public function get_logos()
    {
        $inst_id = $this->_get_username();
        $address = str_replace("\n", '<br />', escape_html(get_option('pd_address')));
        $email = get_option('pd_email');
        $number = get_option('pd_number');
        return do_template('ECOM_LOGOS_WORLDPAY', array('_GUID' => '4b3254b330b3b1719d66d2b754c7a8c8', 'INST_ID' => $inst_id, 'PD_ADDRESS' => $address, 'PD_EMAIL' => $email, 'PD_NUMBER' => $number));
    }

    /**
     * Generate a transaction ID.
     *
     * @return string A transaction ID.
     */
    public function generate_trans_id()
    {
        require_code('crypt');
        return get_rand_password();
    }

    /**
     * Make a transaction (payment) button.
     *
     * @param  ID_TEXT $trans_expecting_id Our internal temporary transaction ID.
     * @param  ID_TEXT $type_code The product codename.
     * @param  SHORT_TEXT $item_name The human-readable product title.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @param  REAL $amount A transaction amount.
     * @param  ID_TEXT $currency The currency to use.
     * @return Tempcode The button.
     */
    public function make_transaction_button($trans_expecting_id, $type_code, $item_name, $purchase_id, $amount, $currency)
    {
        $username = $this->_get_username();
        $form_url = $this->_get_remote_form_url();
        $email_address = $GLOBALS['FORUM_DRIVER']->get_member_email_address(get_member());
        $digest_option = get_option('payment_gateway_digest');
        //$digest = md5((($digest_option == '') ? ($digest_option . ':') : '') . $trans_expecting_id . ':' . float_to_raw_string($amount) . ':' . $currency);  Deprecated
        $digest = md5((($digest_option == '') ? ($digest_option . ':') : '') . ';' . 'cartId:amount:currency;' . $trans_expecting_id . ';' . float_to_raw_string($amount) . ';' . $currency);

        return do_template('ECOM_TRANSACTION_BUTTON_VIA_WORLDPAY', array(
            '_GUID' => '56c78a4e16c0e7f36fcfbe57d37bc3d3',
            'TYPE_CODE' => $type_code,
            'ITEM_NAME' => $item_name,
            'PURCHASE_ID' => $purchase_id,
            'TRANS_EXPECTING_ID' => $trans_expecting_id,
            'DIGEST' => $digest,
            'TEST_MODE' => ecommerce_test_mode(),
            'AMOUNT' => float_to_raw_string($amount),
            'CURRENCY' => $currency,
            'USERNAME' => $username,
            'FORM_URL' => $form_url,
            'EMAIL_ADDRESS' => $email_address,
            'MEMBER_ADDRESS' => $this->_build_member_address(),
        ));
    }

    /**
     * Make a subscription (payment) button.
     *
     * @param  ID_TEXT $trans_expecting_id Our internal temporary transaction ID.
     * @param  ID_TEXT $type_code The product codename.
     * @param  SHORT_TEXT $item_name The human-readable product title.
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @param  REAL $amount A transaction amount.
     * @param  ID_TEXT $currency The currency to use.
     * @param  integer $length The subscription length in the units.
     * @param  ID_TEXT $length_units The length units.
     * @set    d w m y
     * @return Tempcode The button.
     */
    public function make_subscription_button($trans_expecting_id, $type_code, $item_name, $purchase_id, $amount, $currency, $length, $length_units)
    {
        // https://support.worldpay.com/support/kb/bg/recurringpayments/rpfp.html

        $username = $this->_get_username();
        $form_url = $this->_get_remote_form_url();
        $length_units_2 = '1';
        $first_repeat = time();
        switch ($length_units) {
            case 'd':
                $length_units_2 = '1';
                $first_repeat = 60 * 60 * 24 * $length;
                break;
            case 'w':
                $length_units_2 = '2';
                $first_repeat = 60 * 60 * 24 * 7 * $length;
                break;
            case 'm':
                $length_units_2 = '3';
                $first_repeat = 60 * 60 * 24 * 31 * $length;
                break;
            case 'y':
                $length_units_2 = '4';
                $first_repeat = 60 * 60 * 24 * 365 * $length;
                break;
        }
        $digest_option = get_option('payment_gateway_digest');
        //$digest = md5((($digest_option == '') ? ($digest_option . ':') : '') . $trans_expecting_id . ':' . float_to_raw_string($amount) . ':' . $currency . $length_units_2 . strval($length));   Deprecated
        $digest = md5((($digest_option == '') ? ($digest_option . ':') : '') . ';' . 'cartId:amount:currency:intervalUnit:intervalMult;' . $trans_expecting_id . ';' . float_to_raw_string($amount) . ';' . $currency . $length_units_2 . strval($length));

        return do_template('ECOM_SUBSCRIPTION_BUTTON_VIA_WORLDPAY', array(
            '_GUID' => '1f88716137762a467edbf5fbb980c6fe',
            'TYPE_CODE' => $type_code,
            'ITEM_NAME' => $item_name,
            'PURCHASE_ID' => $purchase_id,
            'TRANS_EXPECTING_ID' => $trans_expecting_id,
            'DIGEST' => $digest,
            'TEST' => ecommerce_test_mode(),
            'LENGTH' => strval($length),
            'LENGTH_UNITS_2' => $length_units_2,
            'AMOUNT' => float_to_raw_string($amount),
            'FIRST_REPEAT' => date('Y-m-d', $first_repeat),
            'CURRENCY' => $currency,
            'USERNAME' => $username,
            'FORM_URL' => $form_url,
            'MEMBER_ADDRESS' => $this->_build_member_address(),
        ));
    }

    /**
     * Get a member address/etc for use in payment buttons.
     *
     * @return array A map of member address details (form field name => address value).
     */
    protected function _build_member_address()
    {
        $member_address = array();
        if (!is_guest()) {
            $member_address['name'] = trim(get_cms_cpf('firstname') . ' ' . get_cms_cpf('lastname'));
            $address_lines = explode("\n", get_cms_cpf('street_address'));
            $member_address['address1'] = $address_lines[0];
            $member_address['address2'] = $address_lines[1];
            unset($address_lines[1]);
            unset($address_lines[0]);
            $member_address['address3'] = implode(', ', $address_lines);
            $member_address['town'] = get_cms_cpf('city');
            $member_address['region'] = get_cms_cpf('state');
            $member_address['postcode'] = get_cms_cpf('post_code');
            $member_address['country'] = get_cms_cpf('country');
            $member_address['tel'] = get_cms_cpf('mobile_phone_number');
            $member_address['email'] = $GLOBALS['FORUM_DRIVER']->get_member_email_address(get_member());
        }
        return $member_address;
    }

    /**
     * Make a subscription cancellation button.
     *
     * @param  ID_TEXT $purchase_id The purchase ID.
     * @return Tempcode The button.
     */
    public function make_cancel_button($purchase_id)
    {
        return do_template('ECOM_SUBSCRIPTION_CANCEL_BUTTON_VIA_WORLDPAY', array('_GUID' => '187fba57424e7850b9e21fc147de48eb', 'PURCHASE_ID' => $purchase_id));
    }

    /**
     * Handle IPN's. The function may produce output, which would be returned to the Payment Gateway. The function may do transaction verification.
     *
     * @return ?array A long tuple of collected data (null: no transaction; will only return null when not running the 'ecommerce' script).
     */
    public function handle_ipn_transaction()
    {
        // http://support.worldpay.com/support/kb/bg/paymentresponse/pr0000.html

        $trans_expecting_id = post_param_string('cartId');

        $transaction_rows = $GLOBALS['SITE_DB']->query_select('ecom_trans_expecting', array('*'), array('id' => $trans_expecting_id), '', 1);
        if (!array_key_exists(0, $transaction_rows)) {
            if (!running_script('ecommerce')) {
                return null;
            }
            warn_exit(do_lang_tempcode('MISSING_RESOURCE'));
        }
        $transaction_row = $transaction_rows[0];

        $member_id = $transaction_row['e_member_id'];
        $type_code = $transaction_row['e_type_code'];
        $item_name = $transaction_row['e_item_name'];
        $purchase_id = $transaction_row['e_purchase_id'];

        $code = post_param_string('transStatus');
        if ($code == 'C') {
            if (!running_script('ecommerce')) {
                return null;
            }
            exit(); // Cancellation signal, won't process
        }
        $success = ($code == 'Y');

        $txn_id = post_param_string('transId');
        if (post_param_string('futurePayType', '') == 'regular') {
            $is_subscription = true;
        } else {
            $is_subscription = false;
        }
        $message = post_param_string('rawAuthMessage');
        $status = $success ? 'Completed' : 'Failed';
        $reason = '';
        $pending_reason = '';
        $memo = $transaction_row['e_memo'];
        $_amount = post_param_string('authAmount');
        $amount = ($_amount == '') ? null : floatval($_amount);
        $currency = post_param_string('authCurrency');
        $parent_txn_id = '';
        $period = '';

        // SECURITY
        if (post_param_string('callbackPW') != get_option('payment_gateway_callback_password')) {
            if (!running_script('ecommerce')) {
                return null;
            }
            fatal_ipn_exit(do_lang('IPN_UNVERIFIED'));
        }

        // Shopping cart
        if (addon_installed('shopping')) {
            if (preg_match('#^CART_ORDER_#', $type_code) != 0) {
                $this->store_shipping_address(intval($purchase_id));
            }
        }

        return array($trans_expecting_id, $txn_id, $type_code, $item_name, $purchase_id, $is_subscription, $status, $reason, $amount, $currency, $parent_txn_id, $pending_reason, $memo, $period, $member_id);
    }

    /**
     * Show a payment response after IPN runs (for hooks that handle redirects in this way).
     *
     * @param  ID_TEXT $type_code The product codename.
     * @param  ID_TEXT $purchase_id Purchase ID.
     * @return string The response.
     */
    public function show_payment_response($type_code, $purchase_id)
    {
        $txn_id = post_param_string('transId');
        $message = do_lang('TRANSACTION_ID_WRITTEN', $txn_id);
        $url = build_url(array('page' => 'purchase', 'type' => 'finish', 'type_code' => $type_code, 'message' => $message, 'from' => 'worldpay'), get_module_zone('purchase'));
        return '<meta http-equiv="refresh" content="0;url=' . escape_html($url->evaluate()) . '" />';
    }

    /**
     * Store shipping address for orders.
     *
     * @param  AUTO_LINK $order_id Order ID
     * @return ?mixed Address ID (null: No address record found).
     */
    public function store_shipping_address($order_id)
    {
        if (is_null($GLOBALS['SITE_DB']->query_select_value_if_there('shopping_order_addresses', 'id', array('a_order_id' => $order_id)))) {
            $_name = explode(' ', post_param_string('delvName', ''));
            $name = array();
            if (count($_name) > 0) {
                $name[1] = $_name[count($_name) - 1];
                unset($_name[count($_name) - 1]);
            }
            $name[0] = implode(' ', $_name);

            $shipping_address = array(
                'a_order_id' => $order_id,
                'a_firstname' => $name[0],
                'a_lastname' => $name[1],
                'a_street_address' => trim(post_param_string('delvAddress1', '') . ' ' . post_param_string('delvAddress2', '') . ' ' . post_param_string('delvAddress3', '')),
                'a_city' => post_param_string('city', ''),
                'a_county' => '',
                'a_state' => '',
                'a_post_code' => post_param_string('delvPostcode', ''),
                'a_country' => post_param_string('delvCountryString', ''),
                'a_email' => post_param_string('email', ''),
                'a_phone' => post_param_string('tel', ''),
            );
            return $GLOBALS['SITE_DB']->query_insert('shopping_order_addresses', $shipping_address, true);
        }

        return null;
    }

    /**
     * Find whether the hook auto-cancels (if it does, auto cancel the given subscription).
     *
     * @param  AUTO_LINK $subscription_id ID of the subscription to cancel.
     * @return ?boolean True: yes. False: no. (null: cancels via a user-URL-directioning)
     */
    public function auto_cancel($subscription_id)
    {
        // They created a username and password initially. They need to login using this at https://futurepay.worldpay.com/fp/jsp/common/login_shopper.jsp

        return false;
    }
}
