<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Moneris;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

/**
 * Unit tests for Moneris gateway.
 *
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 *
 */
class MonerisTest extends \AktiveMerchant\TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    /**
     * Setup
     */
    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('moneris');

        $this->gateway = new Moneris($login_info);

        $this->amount = 10;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4242424242424242",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Moneris Test Transaction',
            'address' => array(
                'name' => 'John Dows',
                'address1' => '1 Main St',
                'zip' => '95131',
                'state' => 'CA',
                'country' => 'United States',
                'city' => 'San Jose',
            ),
            'shipping_address' => array(
                'name' => 'John Dows',
                'address1' => '1 Main St',
                'zip' => '95131',
                'state' => 'CA',
                'country' => 'United States',
                'city' => 'San Jose',
            ),
            'street_number' => '1',
            'street_name' => 'Main St'
        );
    }

    public function testSuccessfulAuthorize()
    {
        $this->mock_request($this->successful_authorize_response());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_authorize_request($this->options['order_id']),
            $request_body
        );

        $params = $response->params();

        $this->assertEquals('10.00', $params->trans_amount);
    }

    private function successful_authorize_request($order_id)
    {
        return '<?xml version="1.0"?><request><store_id>store1</store_id><api_token>yesguy</api_token><preauth><order_id>'.$order_id.'</order_id><pan>4242424242424242</pan><expdate>1501</expdate><cvd_info><cvd_indicator>1</cvd_indicator><cvd_value>000</cvd_value></cvd_info><billing><first_name>John</first_name><last_name>Dows</last_name><address>1 Main St</address><city>San Jose</city><province>CA</province><country>United States</country><postal_code>95131</postal_code></billing><shipping><first_name>John</first_name><last_name>Dows</last_name><address>1 Main St</address><city>San Jose</city><province>CA</province><country>United States</country><postal_code>95131</postal_code></shipping><avs_info><avs_street_number>1</avs_street_number><avs_street_name>Main St</avs_street_name><avs_zipcode>95131</avs_zipcode></avs_info><amount>10.00</amount><crypt_type>7</crypt_type></preauth></request>';
    }

    private function successful_authorize_response()
    {
        return '<?xml version="1.0" standalone="yes"?><response><receipt><ReceiptId>5879131395</ReceiptId><ReferenceNum>660021630010010980</ReferenceNum><ResponseCode>027</ResponseCode><ISO>01</ISO><AuthCode>367709</AuthCode><TransTime>16:46:07</TransTime><TransDate>2013-01-07</TransDate><TransType>01</TransType><Complete>true</Complete><Message>APPROVED           *                    =</Message><TransAmount>10.00</TransAmount><CardType>V</CardType><TransID>330847-0_8</TransID><TimedOut>false</TimedOut><BankTotals>null</BankTotals><Ticket>null</Ticket><CorporateCard>false</CorporateCard><AvsResultCode>null</AvsResultCode><ITDResponse>null</ITDResponse><CvdResultCode>1P</CvdResultCode><CavvResultCode>2</CavvResultCode></receipt></response>';
    }

    public function testSuccessfulCapture()
    {
        $this->mock_request($this->successful_capture_response());

        $response = $this->gateway->capture(
            $this->amount,
            '358973-0_8',
            array(
                'order_id' => '8915641235'
            )
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_capture_request(),
            $request_body
        );

        $params = $response->params();

        $this->assertEquals('10.00', $params->trans_amount);
    }

    private function successful_capture_request()
    {
        return '<?xml version="1.0"?><request><store_id>store1</store_id><api_token>yesguy</api_token><completion><order_id>8915641235</order_id><comp_amount>10.00</comp_amount><txn_number>358973-0_8</txn_number><crypt_type>7</crypt_type></completion></request>';
    }

    private function successful_capture_response()
    {
        return '<?xml version="1.0" standalone="yes"?><response><receipt><ReceiptId>8915641235</ReceiptId><ReferenceNum>660021630010010380</ReferenceNum><ResponseCode>027</ResponseCode><ISO>01</ISO><AuthCode>356870</AuthCode><TransTime>06:16:02</TransTime><TransDate>2013-01-11</TransDate><TransType>02</TransType><Complete>true</Complete><Message>APPROVED           *                    =</Message><TransAmount>10.00</TransAmount><CardType>V</CardType><TransID>359029-1_8</TransID><TimedOut>false</TimedOut><BankTotals>null</BankTotals><Ticket>null</Ticket><CorporateCard>false</CorporateCard></receipt></response>';
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successful_purchase_response($this->options['order_id']));

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_purchase_request($this->options['order_id']),
            $request_body
        );

        $params = $response->params();

        $this->assertEquals('10.00', $params->trans_amount);
    }

    private function successful_purchase_request($order_id)
    {
        return '<?xml version="1.0"?><request><store_id>store1</store_id><api_token>yesguy</api_token><purchase><order_id>'.$order_id.'</order_id><pan>4242424242424242</pan><expdate>1501</expdate><cvd_info><cvd_indicator>1</cvd_indicator><cvd_value>000</cvd_value></cvd_info><billing><first_name>John</first_name><last_name>Dows</last_name><address>1 Main St</address><city>San Jose</city><province>CA</province><country>United States</country><postal_code>95131</postal_code></billing><shipping><first_name>John</first_name><last_name>Dows</last_name><address>1 Main St</address><city>San Jose</city><province>CA</province><country>United States</country><postal_code>95131</postal_code></shipping><avs_info><avs_street_number>1</avs_street_number><avs_street_name>Main St</avs_street_name><avs_zipcode>95131</avs_zipcode></avs_info><amount>10.00</amount><crypt_type>7</crypt_type></purchase></request>';
    }

    private function successful_purchase_response($order_id)
    {
        return '<?xml version="1.0" standalone="yes"?><response><receipt><ReceiptId>'.$order_id.'</ReceiptId><ReferenceNum>660021630010010540</ReferenceNum><ResponseCode>027</ResponseCode><ISO>01</ISO><AuthCode>503013</AuthCode><TransTime>06:16:27</TransTime><TransDate>2013-01-11</TransDate><TransType>00</TransType><Complete>true</Complete><Message>APPROVED           *                    =</Message><TransAmount>10.00</TransAmount><CardType>V</CardType><TransID>359045-0_8</TransID><TimedOut>false</TimedOut><BankTotals>null</BankTotals><Ticket>null</Ticket><CorporateCard>false</CorporateCard><AvsResultCode>null</AvsResultCode><ITDResponse>null</ITDResponse><CvdResultCode>1P</CvdResultCode><CavvResultCode>2</CavvResultCode></receipt></response>';
    }

    public function testSuccessfulCredit()
    {
        $this->mock_request($this->successful_credit_response());

        $response = $this->gateway->credit(
            $this->amount,
            '360246-0_8',
            array(
                'order_id' => '1419381921'
            )
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_credit_request(),
            $request_body
        );

        $params = $response->params();

        $this->assertEquals('10.00', $params->trans_amount);
    }

    private function successful_credit_request()
    {
        return '<?xml version="1.0"?><request><store_id>store1</store_id><api_token>yesguy</api_token><refund><order_id>1419381921</order_id><amount>10.00</amount><txn_number>360246-0_8</txn_number><crypt_type>7</crypt_type></refund></request>';
    }

    private function successful_credit_response()
    {
        return '<?xml version="1.0" standalone="yes"?><response><receipt><ReceiptId>1419381921</ReceiptId><ReferenceNum>660021630010013580</ReferenceNum><ResponseCode>027</ResponseCode><ISO>01</ISO><AuthCode>002144</AuthCode><TransTime>07:09:31</TransTime><TransDate>2013-01-11</TransDate><TransType>04</TransType><Complete>true</Complete><Message>APPROVED           *                    =</Message><TransAmount>10.00</TransAmount><CardType>V</CardType><TransID>360349-1_8</TransID><TimedOut>false</TimedOut><BankTotals>null</BankTotals><Ticket>null</Ticket><CorporateCard>false</CorporateCard></receipt></response>';
    }

    public function testSuccessfulVoid()
    {
        $this->mock_request($this->successful_void_response());

        $response = $this->gateway->void(
            '359029-1_8',
            array(
                'order_id' => '8915641235'
            )
        );

        $this->assert_success($response);
        $this->assertTrue($response->test());

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_void_request(),
            $request_body
        );

        $params = $response->params();
    }

    private function successful_void_request()
    {
        return '<?xml version="1.0"?><request><store_id>store1</store_id><api_token>yesguy</api_token><purchasecorrection><order_id>8915641235</order_id><txn_number>359029-1_8</txn_number><crypt_type>7</crypt_type></purchasecorrection></request>';
    }

    private function successful_void_response()
    {
        return '<?xml version="1.0" standalone="yes"?><response><receipt><ReceiptId>8915641235</ReceiptId><ReferenceNum>660021630010017210</ReferenceNum><ResponseCode>027</ResponseCode><ISO>01</ISO><AuthCode>356870</AuthCode><TransTime>07:29:16</TransTime><TransDate>2013-01-11</TransDate><TransType>11</TransType><Complete>true</Complete><Message>APPROVED           *                    =</Message><TransAmount>10.00</TransAmount><CardType>V</CardType><TransID>360712-2_8</TransID><TimedOut>false</TimedOut><BankTotals>null</BankTotals><Ticket>null</Ticket><CorporateCard>false</CorporateCard></receipt></response>';
    }
}
