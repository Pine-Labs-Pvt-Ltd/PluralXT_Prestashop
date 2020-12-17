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
class PluralxtpinelabsValidationModuleFrontController extends ModuleFrontController
{
	// public $warning = '';
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
		
		$postdata = $_REQUEST;			
	
		$amount = '';

		$cart_id = $cart->id;
		
		// if (isset($postdata['unique_merchant_txn_id']) && isset($postdata['dia_secret']))
		if (isset($postdata['order_id']) && isset($postdata['dia_secret']))
		{
			$message = 'Transaction Cancelled';
			
			ksort($postdata);		

			$strPostData = "";

			foreach ($postdata as $key => $value)
			{	
				//LOGGING RESPONSE
				error_log($key . " => " . $value);

				if ($key != "dia_secret" && $key!= "dia_secret_type")
				{
					$strPostData .= $key . "=" . $value . "&";
				}
			}

			$strPostData = substr($strPostData, 0, -1);

			$secret_key = Configuration::get('PLURALXTPINELABS_SECRETKEY');
	
			$responseHash = strtoupper(hash_hmac("sha256", $strPostData, $this->Hex2Str($secret_key)));

			if ($responseHash == $postdata['dia_secret'])
			{
				if (isset($postdata['payment_status']) && $postdata['payment_status'] == 'CAPTURED'
					&& isset($postdata['payment_response_code']) && $postdata['payment_response_code'] == '1')
				{
					$amount = floatval($postdata['amount_in_paisa']) / 100.0;
					$txnId = $postdata['order_id'];

					$status = Configuration::get('PLURALXTPINELABS_ID_ORDER_SUCCESS');

					$this->isSuccess = true;
					$this->message = "Thank you for shopping with us. Your account has been charged and your transaction is successful with the following order details:<br /> Transaction Id: " . $txnId . "<br /> Amount: " . $amount . " <br />We will process your order soon.";
				}
				elseif ($postdata['payment_response_code'] == 'CANCELLED') 
				{					
					$msg = "Thank you for shopping with us. However, the transaction has been cancelled.";
				}
				else
				{				
					$msg = "Thank you for shopping with us. However, the payment failed.";
				}
			}
			else
			{
				//tampered
				$msg = "Thank you for shopping with us. However, the payment failed.";
			}
			
		}
		else
		{			
			$msg = "Thank you for shopping with us. However, the payment failed. Response received from Payment Gateway is not in proper format.";
		}

		if (!$this->isSuccess)
		{
			$status = Configuration::get('PLURALXTPINELABS_ID_ORDER_FAILED');
			$this->message = $msg;
		}

		PrestaShopLogger::addLog("Plural XT by Pine Labs: Created Order for CartId-" . $cart_id, 1, null, 'Plural XT by Pine Labs', (int)$cart_id, true);
		$this->module->validateOrder((int)$cart_id,  $status, (float)$amount, "Plural XT by Pine Labs", null, null, null, false, $customer->secure_key);
		
		if ($status == Configuration::get('PLURALXTPINELABS_ID_ORDER_SUCCESS'))
		{
			Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int)$cart->id . '&id_module=' . (int)$this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
		}
		// else
		// {
		// 	$this->warning = $msg;
		// }
    }

	function Hex2Str($hex)
	{
	    $string = '';

	    for ($i = 0; $i < strlen($hex) - 1; $i += 2) 
	    {
	        $string .= chr(hexdec($hex[$i] . $hex[$i + 1]));
	    }
	    
	    return $string;
	}
}
