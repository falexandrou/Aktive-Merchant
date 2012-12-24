<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Response;
/**
 * Description of PaypalExpressResponse
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class PaypalExpressResponse extends Response
{

    public function email()
    {
        return $this->params['EMAIL'];
    }

    public function correlation_id()
    {
        return $this->params['CORRELATIONID'];
    }

    public function transaction_id()
    {
        return isset($this->params['PAYMENTINFO_0_TRANSACTIONID']) ? $this->params['PAYMENTINFO_0_TRANSACTIONID'] : null;
    }

    public function profile_id()
    {
        return isset($this->params['PROFILEID']) ? $this->params['PROFILEID'] : null;
    }

    public function phone()
    {
        return isset($this->params['PHONENUM']) ? $this->params['PHONENUM'] : null;
    }

    public function status()
    {
        return $this->params['STATUS'];
    }

    public function active()
    {
        return $this->params['STATUS'] == 'Active';
    }

    public function first_name()
    {
        return $this->params['FIRSTNAME'];
    }

    public function last_name()
    {
        return $this->params['LASTNAME'];
    }

    public function name()
    {
        $first_name = $this->params['FIRSTNAME'];
        $middle_name = isset($this->params['MIDDLENAME']) ? $this->params['MIDDLENAME'] : null;
        $last_name = $this->params['LASTNAME'];
        return implode(' ', array_filter(array($first_name, $middle_name, $last_name)));
    }

    public function token()
    {
        return $this->params['TOKEN'];
    }

    public function payer_id()
    {
        return $this->params['PAYERID'];
    }

    public function payer_country()
    {
        return $this->params['SHIPTOCOUNTRYNAME'];
    }

    public function amount()
    {
        return $this->params['AMT'];
    }

    public function address()
    {
        return array(
            'name' => $this->params['SHIPTONAME'],
            'address1' => $this->params['SHIPTOSTREET'],
            'address2' => isset($this->params['SHIPTOSTREET2']) ? $this->params['SHIPTOSTREET2'] : null,
            'city' => $this->params['SHIPTOCITY'],
            'state' => $this->params['SHIPTOSTATE'],
            'zip' => $this->params['SHIPTOZIP'],
            'country_code' => $this->params['SHIPTOCOUNTRYCODE'],
            'country' => $this->params['SHIPTOCOUNTRYNAME'],
            'address_status' => $this->params['ADDRESSSTATUS']
        );
    }

    public function note()
    {
        return isset($this->params['NOTE']) ? $this->params['NOTE'] : null;
    }

}

?>
