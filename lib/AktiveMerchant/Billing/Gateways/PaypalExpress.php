<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Gateway;
/**
 * Description of PaypalExpress
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
require_once dirname(__FILE__) . "/paypal/PaypalCommon.php";
require_once dirname(__FILE__) . "/paypal/PaypalExpressResponse.php";

class PaypalExpress extends PaypalCommon
{
    const TEST_REDIRECT_URL = 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';
    const LIVE_REDIRECT_URL = 'https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=';

    /**
     * SOLUTIONTYPE Param values
     * @see https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_SetExpressCheckout
     *
     *  - Sole: Buyer does not need to create a PayPal account to check out. This is referred to as PayPal Account Optional.
     *  - Mark: Buyer must have a PayPal account to check out.
     */
    const SOLUTIONTYPE_SOLE         = 'Sole';
    const SOLUTIONTYPE_MARK         = 'Mark';

    /**
     * LANDINGPAGE Param values
     * @see https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_SetExpressCheckout
     *
     *  - Billing: Non-PayPal account
     *  - Login: PayPal account login
     */
    const LANDINGPAGE_BILLING       = 'Billing';
    const LANDINGPAGE_LOGIN         = 'Login';

    // Recurring billing cycle
    const RECURRING_DAY             = 'Day';
    const RECURRING_WEEK            = 'Week';
    const RECURRING_SEMIMONTH       = 'SemiMonth';
    const RECURRING_MONTH           = 'Month';
    const RECURRING_YEAR            = 'Year';

    /**
     * @var boolean whether billing is recurring
     */
    public $recurring = false;

    /**
     * @var string version
     */
    private $version = '65.0';

    /**
     * @var array options
     */
    private $options = array();
    private $post = array();
    private $token;
    private $payer_id;
    public static $default_currency = 'USD';
    public static $supported_countries = array('US');
    public static $homepage_url = 'https://www.paypal.com/cgi-bin/webscr?cmd=xpt/merchant/ExpressCheckoutIntro-outside';
    public static $display_name = 'PayPal Express Checkout';

    /**
     * @var string the value for SOLUTIONTYPE parameter
     */
    public static $solution_type = self::SOLUTIONTYPE_SOLE;

    /**
     * @var string the value for LANDINGPAGE parameter
     */
    public static $landing_page = self::LANDINGPAGE_BILLING;

    public function __construct($options = array())
    {
        $this->required_options('login, password, signature', $options);

        $this->options = $options;
        if (isset($options['recurring']))
            $this->recurring = (boolean)$options['recurring'];

        if (isset($options['version']))
            $this->version = $options['version'];
        if (isset($options['currency']))
            self::$default_currency = $options['currency'];
    }

    /**
     * Method from Gateway overridden to allow negative values
     * @param double
     * @return string
     */
    public function amount($money)
    {
        if (null === $money)
            return null;

        $cents = $money * 100;
        if (!is_numeric($money)) {
            throw new Merchant_Billing_Exception('money amount must be an integer in cents.');
        }

        return ($this->money_format() == 'cents') 
            ? number_format($cents, 0, '', '') 
            : number_format($money, 2);
    }

    /**
     * Authorize and Purchase actions
     *
     * @param number $amount  Total order amount
     * @param Array  $options
     *               token    token param from setup action
     *               payer_id payer_id param from setup action
     *
     * @return Response
     */
    public function authorize($amount, $options = array())
    {
        return $this->do_action($amount, "Authorization", $options);
    }

    /**
     *
     * @param number $amount
     * @param array $options
     *
     * @return Response
     */
    public function purchase($amount, $options = array())
    {
        return $this->do_action($amount, "Sale", $options);
    }

    /**
     * Setup a recurring payment profile
     * 
     * @param number $amount Total billing amount
     * @param Array $options
     *
     * @return Response
     */
    public function recurring($amount, $options = array())
    {
        $this->recurring = true;
        return $this->do_action($amount, 'Authorization', $options);
    }

    /**
     * Get the recurring billing status
     * @param string $profileId
     */
    public function recurringStatus($profileId)
    {
        if (empty($profileId))
            throw new Exception("Profile ID parameter is required");
        
        $this->post = array(
            'PROFILEID' => $profileId,
            'METHOD'    => 'GetRecurringPaymentsProfileDetails',
        );

        return $this->commit('GetRecurringPaymentsProfileDetails');
    }

    /**
     * Setup Authorize and Purchase actions
     *
     * @param number $money  Total order amount
     * @param array  $options
     *               currency           Valid currency code ex. 'EUR', 'USD'. See http://www.xe.com/iso4217.php for more
     *               return_url         Success url (url from  your site )
     *               cancel_return_url  Cancel url ( url from your site )
     *
     * @return Response
     */
    public function setupAuthorize($money, $options = array())
    {
        return $this->setup($money, 'Authorization', $options);
    }

    public function setupRecurring($money, $options = array())
    {
        return $this->setup($money, 'Sale', $options);
    }

    /**
     *
     * @param number $money
     * @param array $options
     *
     * @return Response
     */
    public function setupPurchase($money, $options = array())
    {
        return $this->setup($money, 'Sale', $options);
    }

    private function setup($money, $action, $options = array())
    {

        $this->required_options('return_url, cancel_return_url', $options);

        $this->post = array();

        $params = array(
            'METHOD'               => 'SetExpressCheckout',
            'PAYMENTREQUEST_0_AMT' => $this->amount($money),
            'RETURNURL'            => $options['return_url'],
            'CANCELURL'            => $options['cancel_return_url'],
            'SOLUTIONTYPE'         => static::$solution_type,
            'LANDINGPAGE'          => static::$landing_page,
        );

        if ($this->recurring) {
            $params['BILLINGTYPE'] = 'RecurringPayments';
            $params['AMT'] = $money;
        }

        if(isset($options['header_image']))
            $params['HDRIMG'] = $options['header_image'];

        $this->post = array_merge(
            $this->post, 
            $params, 
            $this->get_optional_params($options)
        );

        return $this->commit($action);
    }

    private function do_action($money, $action, $options = array())
    {
        if (!isset($options['token'])) {
            $options['token'] = $this->token;
        }

        if (!isset($options['payer_id'])) {
            $options['payer_id'] = $this->payer_id;
        }

        $this->required_options('token, payer_id', $options);

        $this->post = array();
        
        $params = array(
            'METHOD'               => 'DoExpressCheckoutPayment',
            'PAYMENTREQUEST_0_AMT' => $this->amount($money),
            'TOKEN'                => $options['token'],
            'PAYERID'              => $options['payer_id']
        );

        if ($this->recurring) {
            $params['METHOD'] = 'CreateRecurringPaymentsProfile';
            $params['BILLINGTYPE'] = 'RecurringPayments';
            $params['AMT'] = $money;
        }

        $this->post = array_merge(
            $this->post, 
            $params, 
            $this->get_optional_params($options)
        );

        return $this->commit($action);
    }

    private function get_optional_params($options)
    {
        $params = array();

        if (isset($options['payment_breakdown'])) {
            $breakdown = $options['payment_breakdown'];
            $params['PAYMENTREQUEST_0_ITEMAMT'] = $this->amount($breakdown['item_total']);
            $params['PAYMENTREQUEST_0_SHIPPINGAMT'] = $this->amount($breakdown['shipping']);
            $params['PAYMENTREQUEST_0_HANDLINGAMT'] = $this->amount($breakdown['handling']);
        }

        if (isset($options['items'])) {
            foreach ($options['items'] as $key => $item) {
                $params["L_PAYMENTREQUEST_0_NAME$key"]   = $item['name'];
                $params["L_PAYMENTREQUEST_0_DESC$key"]   = $item['description'];
                $params["L_PAYMENTREQUEST_0_AMT$key"]    = $this->amount($item['unit_price']);
                $params["L_PAYMENTREQUEST_0_QTY$key"]    = $item['quantity'];
                $params["L_PAYMENTREQUEST_0_NUMBER$key"] = $item['id'];
            }
        }

        if (isset($options['email'])) {
            $params['EMAIL'] = $options['email'];
        }

        if (isset($options['extra_options'])) {
            $params = array_merge($params, $options['extra_options']);
        }

        if ($this->recurring) {
            $params['L_BILLINGTYPE0'] = 'RecurringPayments';
            $params['BILLINGAGREEMENTDESCRIPTION'] = $params['DESC'] = isset($options['description']) ? $options['description'] : 'Recurring Billing';
            $params['PROFILESTARTDATE']     = isset($options['start']) ? $options['start'] : date('Y-m-dTH:i:s');
            $params['BILLINGPERIOD']        = isset($options['period']) ? $options['period'] : static::RECURRING_MONTH;
            $params['BILLINGFREQUENCY']     = isset($options['frequency']) ? $options['frequency'] : 12;
        }

        return $params;
    }

    /**
     *
     * @param string $token
     *
     * @return string url address to redirect
     */
    public function urlForToken($token)
    {
        $redirect_url = $this->isTest() 
            ? self::TEST_REDIRECT_URL 
            : self::LIVE_REDIRECT_URL;
        return $redirect_url . $token;
    }

    /**
     *
     * @param string $token
     * @param string $payer_id
     *
     * @return Response
     */
    public function get_details_for($token, $payer_id)
    {

        $this->payer_id = urldecode($payer_id);
        $this->token = urldecode($token);

        $params = array(
            'METHOD' => 'GetExpressCheckoutDetails',
            'TOKEN' => $token
        );
        $this->post = array_merge($this->post, $params);

        return $this->commit($this->urlize($this->post));
    }

    /**
     *
     * Add final parameters to post data and
     * build $this->post to the format that your payment gateway understands
     *
     * @param string $action
     * @param array $parameters
     */
    protected function post_data($action)
    {
        $params = array(
            'PAYMENTREQUEST_0_PAYMENTACTION' => $action,
            'USER'                           => $this->options['login'],
            'PWD'                            => $this->options['password'],
            'VERSION'                        => $this->version,
            'SIGNATURE'                      => $this->options['signature'],
            'PAYMENTREQUEST_0_CURRENCYCODE'  => self::$default_currency
        );

        $this->post = array_merge($this->post, $params);
        return $this->urlize($this->post);
    }

    /**
     *
     * @param boolean $success
     * @param string  $message
     * @param array   $response
     * @param array   $options
     *
     * @return PaypalExpressResponse
     */
    protected function build_response($success, $message, $response, $options = array())
    {
        return new PaypalExpressResponse($success, $message, $response, $options);
    }

}

?>
