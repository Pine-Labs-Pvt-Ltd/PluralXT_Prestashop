<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.7.0
 */
class PluralxtpinelabsProcessPaymentModuleFrontController extends ModuleFrontController
{
	public $message = '';
	public $isSuccess = false;

	public function initContent()
  	{  
		parent::initContent();
	
		$this->context->smarty->assign(array(
		  	// 'warning' => $this->warning,
			'message' => $this->message,
			'isSuccess' => $this->isSuccess
        	));        	
	    
		$this->setTemplate('module:pluralxtpinelabs/views/templates/front/validation.tpl');
  	}
  
    public function postProcess()
    {
    	$cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'pluralxtpinelabs') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
           // $this->warning='This payment method is not available.';
		   $this->message='This payment method is not available. Contact Administrator for available payment methods.';
		   
		   return;
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

		$cart_id = $cart->id;
    	$postdata = $_REQUEST;

		$amount = floatval($postdata['amount_in_paisa']) / 100.0;
		$requestData = $postdata['order_request'];
		$x_verify = $postdata['x_verify'];
		$ppc_PayModeOnLandingPage = $postdata['ppc_PayModeOnLandingPage'];		
		
		$pluralxtHostUrl = 'https://paymentoptimizer.pinepg.in';
		
		if ($postdata['gateway_mode'] == 'sandbox')
		{
			$pluralxtHostUrl = 'https://paymentoptimizertest.pinepg.in';
		}

		$orderCreationUrl = $pluralxtHostUrl . '/api/v2/order/create';

		$order_creation = $this->callOrderCreationAPI($orderCreationUrl, $requestData, $x_verify);

		$response = json_decode($order_creation, true);

		$response_code = null;
		$token = null;

		if (!empty($response))
		{	
			if (array_key_exists('response_code', $response))
			{	
				$response_code = $response['response_code'];
			}

			if (array_key_exists('token', $response))
			{
				$token = $response['token'];
			}	
		}

		if ($response_code != 1 || empty($token))
		{
			$status = Configuration::get('PLURALXTPINELABS_ID_ORDER_FAILED');
			$this->message = "Thank you for shopping with us. However, the payment failed.";

			PrestaShopLogger::addLog("Plural XT by Pine Labs: Created Order for CartId-" . $cart_id, 1, null, 'Plural XT by Pine Labs', (int)$cart_id, true);
			$this->module->validateOrder((int)$cart_id,  $status, (float)$amount, "Plural XT by Pine Labs", null, null, null, false, $customer->secure_key);
		}
		else
		{
			$payment_redirect_url = $pluralxtHostUrl . '/pinepg/v2/process/payment/redirect?orderToken=' . $token . '&paymentmodecsv=' . $ppc_PayModeOnLandingPage;

			Tools::redirect($payment_redirect_url);
		}
    }

	function callOrderCreationAPI($url, $data, $x_verify)
	{
	   	$curl = curl_init();
		
		curl_setopt($curl, CURLOPT_POST, 1);
		
		if ($data)
		{
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		// OPTIONS:
		curl_setopt($curl, CURLOPT_URL, $url);

		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
		  'X-VERIFY: ' . $x_verify,
		  'Content-Type: application/json',
		));

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		// EXECUTE:
		$result = curl_exec($curl);

		if (!$result) {
			die("Connection Failure");
		}

		curl_close($curl);

		return $result;
	}	
}
