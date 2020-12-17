<?php /* Prestashop Payment module for Plural XT by Pine Labs */ ?>
<?php 	
error_reporting(E_ALL);	

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
	exit;
}

class pluralxtpinelabs extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();

	private $_title;
	
	function __construct()
	{		
		$this->name = 'pluralxtpinelabs';		
		$this->tab = 'payments_gateways';		
		$this->version = '1.0';
		$this->author = 'Pine Labs';
				
		$this->bootstrap = true;			
		parent::__construct();		
			
		$this->displayName = $this->trans('Plural XT by Pine Labs', array(), 'Modules.pluralxtpinelabs.Admin');
		$this->description = $this->trans('Accept payments via Plural XT by Pine Labs', array(), 'Modules.pluralxtpinelabs.Admin');
		$this->confirmUninstall = $this->trans('Are you sure you want to delete these details?', array(), 'Modules.pluralxtpinelabs.Admin');
		$this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);
								
		$this->_title = 'Plural XT by Pine Labs';
		
		$this->page = basename(__FILE__, '.php');		
					
	}	
	
	public function install()
	{
		Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'order_state` ( `invoice`, `send_email`, `color`, `unremovable`, `logable`, `delivery`, `module_name`)	VALUES	(0, 0, \'#33FF99\', 0, 1, 0, \'pluralxtpinelabs\');');
		$id_order_state = (int) Db::getInstance()->Insert_ID();
		Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'order_state_lang` (`id_order_state`, `id_lang`, `name`, `template`) VALUES ('.$id_order_state.', 1, \'Payment Successful\', \'payment\')');
		Configuration::updateValue('PLURALXTPINELABS_ID_ORDER_SUCCESS', $id_order_state);			
		unset($id_order_state);
				
		Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'order_state`( `invoice`, `send_email`, `color`, `unremovable`, `logable`, `delivery`, `module_name`) VALUES (0, 0, \'#EBA3A3\', 0, 1, 0, \'pluralxtpinelabs\');');
		$id_order_state = (int) Db::getInstance()->Insert_ID();
		Db::getInstance()->Execute('INSERT INTO `' . _DB_PREFIX_ . 'order_state_lang` (`id_order_state`, `id_lang`, `name`, `template`) VALUES ('.$id_order_state.', 1, \'Payment Failed\', \'payment\')');
		Configuration::updateValue('PLURALXTPINELABS_ID_ORDER_FAILED', $id_order_state);		
		unset($id_order_state);
		
		return parent::install()
			&& $this->registerHook('paymentOptions')
			&& $this->registerHook('displayPaymentByBinaries');	
	}

	public function uninstall()
	{		
		Db::getInstance()->Execute('DELETE FROM `' . _DB_PREFIX_ . 'order_state_lang` WHERE id_order_state = '.Configuration::get('PLURALXTPINELABS_ID_ORDER_SUCCESS').' and id_lang = 1' );
		Db::getInstance()->Execute('DELETE FROM `' . _DB_PREFIX_ . 'order_state_lang`  WHERE id_order_state = '.Configuration::get('PLURALXTPINELABS_ID_ORDER_FAILED').' and id_lang = 1');

		return 
			Configuration::deleteByName('PLURALXTPINELABS_GATEWAYMODE')
			&& Configuration::deleteByName('PLURALXTPINELABS_ACCESSCODE')
			&& Configuration::deleteByName('PLURALXTPINELABS_SECRETKEY')
			&& Configuration::deleteByName('PLURALXTPINELABS_MERCHANTID')
			&& Configuration::deleteByName('PLURALXTPINELABS_PAYMENTMODES')
			&& Configuration::deleteByName('PLURALXTPINELABS_PREFERRED_GATEWAY')
			&& parent::uninstall();		
	}

	public function hookdisplayPaymentByBinaries($params)
	{
		if (!$this->active) {
            return;
        }

		$btn = '<section class="js-payment-binary js-payment-pluralxtpinelabs disabled">';
		
		$btn = $btn.'<button type="button" onclick="launchICP(); return false;" class="btn btn-primary center-block">';
        $btn = $btn.'Make Payment via Plural XT';
		$btn = $btn.'</button>';		
		$btn = $btn.'</section>';
		
		return $btn;
	}

	public function hookPaymentOptions($params)
	{		
		if (!$this->active) {
			return;
		}
	
		$newOption = new PaymentOption();
		
		$gateway_mode = Configuration::get('PLURALXTPINELABS_GATEWAYMODE');

		$action = $this->context->link->getModuleLink($this->name, 'processpayment', array(), true);

		$inputs = $this->pluralxtInput();
		
		$newOption->setCallToActionText($this->l($this->_title))			
			->setAction($action)
			->setInputs($inputs)
			->setAdditionalInformation($this->context->smarty->fetch('module:pluralxtpinelabs/pluralxtpinelabs.tpl'))
			->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png'));				
		
		$newOption->setModuleName('pluralxtpinelabs');
		
		return [$newOption];
	}
	
	private function _postValidation()
	{
		if (Tools::isSubmit('btnSubmit')) {
			if (!Tools::getValue('PLURALXTPINELABS_GATEWAYMODE')) {
				$this->_postErrors[] = $this->trans('Gateway Mode is required.', array(), 'Modules.pluralxtpinelabs.Admin');
			}

			if (!Tools::getValue('PLURALXTPINELABS_MERCHANTID')) {
				$this->_postErrors[] = $this->trans('Merchant Id is required.', array(), 'Modules.pluralxtpinelabs.Admin');
			}

			if (!Tools::getValue('PLURALXTPINELABS_ACCESSCODE')) {
				$this->_postErrors[] = $this->trans('Merchant Access Code is required.', array(), 'Modules.pluralxtpinelabs.Admin');
			}

			if (!Tools::getValue('PLURALXTPINELABS_SECRETKEY')) {
				$this->_postErrors[] = $this->trans('Secret Key is required.', array(), 'Modules.pluralxtpinelabs.Admin');
			}

			if (!Tools::getValue('PLURALXTPINELABS_PAYMENTMODES')) {
				$this->_postErrors[] = $this->trans('Payment Modes are required.', array(), 'Modules.pluralxtpinelabs.Admin');
			}
		}
	}

	private function _postProcess()
	{
		if (Tools::isSubmit('btnSubmit')) {
			Configuration::updateValue('PLURALXTPINELABS_GATEWAYMODE', Tools::getValue('PLURALXTPINELABS_GATEWAYMODE'));
			Configuration::updateValue('PLURALXTPINELABS_ACCESSCODE', Tools::getValue('PLURALXTPINELABS_ACCESSCODE'));
			Configuration::updateValue('PLURALXTPINELABS_SECRETKEY', Tools::getValue('PLURALXTPINELABS_SECRETKEY'));
			Configuration::updateValue('PLURALXTPINELABS_MERCHANTID', Tools::getValue('PLURALXTPINELABS_MERCHANTID'));
			Configuration::updateValue('PLURALXTPINELABS_PAYMENTMODES', Tools::getValue('PLURALXTPINELABS_PAYMENTMODES'));
			Configuration::updateValue('PLURALXTPINELABS_PREFERRED_GATEWAY', Tools::getValue('PLURALXTPINELABS_PREFERRED_GATEWAY'));
		}

		$this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
	}
	
	public function getContent()
	{
		 $this->_html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } 
            else 
            {
                foreach ($this->_postErrors as $err) 
                {
                    $this->_html .= $this->displayError($err);
                }
            }
        }

        $this->_html .= $this->_displayCheck();
        $this->_html .= $this->renderForm();

		return $this->_html;
	}
	
	public function renderForm()
	{		
		$gateway_mode_options = array(
				array(
					'id_option' => 'sandbox',
					'name' => 'sandbox'
					),
				array(
					'id_option' => 'production', 
					'name' => 'production' 
					)
				);
		
		$preferred_gateway_options = array(
				array(
					'id_option' => 'NONE',
					'name' => 'Select'
					),
				array(
					'id_option' => 'AMEX', 
					'name' => 'AMEX' 
					),
				array(
					'id_option' => 'AMEX_ENHANCED',
					'name' => 'AMEX_ENHANCED'
					),
				array(
					'id_option' => 'AXIS',
					'name' => 'AXIS'
					),
				array(
					'id_option' => 'AXISB24',
					'name' => 'AXISB24'
					),
				array(
					'id_option' => 'BANKTEK',
					'name' => 'BANKTEK'
					),
				array(
					'id_option' => 'BFL',
					'name' => 'BFL'
					),
				array(
					'id_option' => 'BHARATQR_HDFC',
					'name' => 'BHARATQR_HDFC'
					),
				array(
					'id_option' => 'BILLDESK',
					'name' => 'BILLDESK'
					),
				array(
					'id_option' => 'BOB',
					'name' => 'BOB'
					),
				array(
					'id_option' => 'CCAVENUE_NET_BANKING',
					'name' => 'CCAVENUE_NET_BANKING'
					),
				array(
					'id_option' => 'CITI',
					'name' => 'CITI'
					),
				array(
					'id_option' => 'CITRUS_NET_BANKING',
					'name' => 'CITRUS_NET_BANKING'
					),
				array(
					'id_option' => 'CORP',
					'name' => 'CORP'
					),
				array(
					'id_option' => 'DEBIT_PIN_FSS',
					'name' => 'DEBIT_PIN_FSS'
					),
				array(
					'id_option' => 'EBS_NETBANKING',
					'name' => 'EBS_NETBANKING'
					),
				array(
					'id_option' => 'EDGE',
					'name' => 'EDGE'
					),
				array(
					'id_option' => 'FEDERAL',
					'name' => 'FEDERAL'
					),
				array(
					'id_option' => 'FSS_NETBANKING',
					'name' => 'FSS_NETBANKING'
					),
				array(
					'id_option' => 'HDFC',
					'name' => 'HDFC'
					),
				array(
					'id_option' => 'HDFC_DEBIT_EMI',
					'name' => 'HDFC_DEBIT_EMI'
					),
				array(
					'id_option' => 'HDFC_PRIZM',
					'name' => 'HDFC_PRIZM'
					),
				array(
					'id_option' => 'HSBC',
					'name' => 'HSBC'
					),
				array(
					'id_option' => 'ICICI',
					'name' => 'ICICI'
					),
				array(
					'id_option' => 'ICICI_SHAKTI',
					'name' => 'ICICI_SHAKTI'
					),
				array(
					'id_option' => 'IDBI',
					'name' => 'IDBI'
					),
				array(
					'id_option' => 'LVB',
					'name' => 'LVB'
					),
				array(
					'id_option' => 'MASHREQ',
					'name' => 'MASHREQ'
					),
				array(
					'id_option' => 'OPUS',
					'name' => 'OPUS'
					),
				array(
					'id_option' => 'PAYTM',
					'name' => 'PAYTM'
					),
				array(
					'id_option' => 'PayU',
					'name' => 'PayU'
					),
				array(
					'id_option' => 'RAZOR_PAY',
					'name' => 'RAZOR_PAY'
					),
				array(
					'id_option' => 'SBI',
					'name' => 'SBI'
					),
				array(
					'id_option' => 'SBI87',
					'name' => 'SBI87'
					),
				array(
					'id_option' => 'SI_HDFC',
					'name' => 'SI_HDFC'
					),
				array(
					'id_option' => 'SI_PAYNIMO',
					'name' => 'SI_PAYNIMO'
					),
				array(
					'id_option' => 'UBI',
					'name' => 'UBI'
					),
				array(
					'id_option' => 'UPI_AXIS',
					'name' => 'UPI_AXIS'
					),
				array(
					'id_option' => 'UPI_HDFC',
					'name' => 'UPI_HDFC'
					),
				array(
					'id_option' => 'WALLET_PAYZAPP',
					'name' => 'WALLET_PAYZAPP'
					),
				array(
					'id_option' => 'WALLET_PHONEPE',
					'name' => 'WALLET_PHONEPE'
					),
				array(
					'id_option' => 'YES',
					'name' => 'YES'
					),
				array(
					'id_option' => 'ZEST_MONEY',
					'name' => 'ZEST_MONEY'
					)				
				);

		$fields_form = array(
			'form' => array(
					'legend' => array(
						'title' => $this->trans('Pine Labs Merchant Account', array(), 'Modules.pluralxtpinelabs.Admin'),
						'icon' => 'icon-envelope'
						),
					'input' => array(
						array(
							'type' => 'select',
							'label' => $this->trans('Gateway Mode', array(), 'Modules.pluralxtpinelabs.Admin'),
							'name' => 'PLURALXTPINELABS_GATEWAYMODE',
							'required' => true,
							'options' => array(
								'query' => $gateway_mode_options,
								'id' => 'id_option', 
								'name' => 'name'
								)
							),
						array(
								'type' => 'text',
								'label' => $this->trans('Merchant Id', array(), 'Modules.pluralxtpinelabs.Admin'),
								'name' => 'PLURALXTPINELABS_MERCHANTID',
								'required' => true
						),
						array(
								'type' => 'text',
								'label' => $this->trans('Merchant Access Code', array(), 'Modules.pluralxtpinelabs.Admin'),
								'name' => 'PLURALXTPINELABS_ACCESSCODE',
								'required' => true
						),
						array(
								'type' => 'text',
								'label' => $this->trans('Secret Key', array(), 'Modules.pluralxtpinelabs.Admin'),
								'name' => 'PLURALXTPINELABS_SECRETKEY',
								'required' => true
						),
						array(
								'type' => 'text',
								'label' => $this->trans('Payment Modes', array(), 'Modules.pluralxtpinelabs.Admin'),
								'name' => 'PLURALXTPINELABS_PAYMENTMODES',
								'required' => true
						),
						array(
							'type' => 'select',
							'label' => $this->trans('Preferred Payment Gateway', array(), 'Modules.pluralxtpinelabs.Admin'),
							'name' => 'PLURALXTPINELABS_PREFERRED_GATEWAY',
							'options' => array(
								'query' => $preferred_gateway_options,
								'id' => 'id_option', 
								'name' => 'name'
							)
						)
					),
					'submit' => array(
						'title' => $this->trans('Save', array(), 'Admin.Actions'),
						)
					)
				);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnSubmit';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			);

		$this->fields_form = array();

		return $helper->generateForm(array($fields_form));
	}

	public function getConfigFieldsValues()
	{
		return array(
			'PLURALXTPINELABS_MERCHANTID' => Tools::getValue('PLURALXTPINELABS_MERCHANTID', Configuration::get('PLURALXTPINELABS_MERCHANTID')),
			'PLURALXTPINELABS_GATEWAYMODE' => Tools::getValue('PLURALXTPINELABS_GATEWAYMODE', Configuration::get('PLURALXTPINELABS_GATEWAYMODE')),
			'PLURALXTPINELABS_ACCESSCODE' => Tools::getValue('PLURALXTPINELABS_ACCESSCODE', Configuration::get('PLURALXTPINELABS_ACCESSCODE')),
			'PLURALXTPINELABS_SECRETKEY' => Tools::getValue('PLURALXTPINELABS_SECRETKEY', Configuration::get('PLURALXTPINELABS_SECRETKEY')),
			'PLURALXTPINELABS_PAYMENTMODES' => Tools::getValue('PLURALXTPINELABS_PAYMENTMODES', Configuration::get('PLURALXTPINELABS_PAYMENTMODES')),
			'PLURALXTPINELABS_PREFERRED_GATEWAY' => Tools::getValue('PLURALXTPINELABS_PREFERRED_GATEWAY', Configuration::get('PLURALXTPINELABS_PREFERRED_GATEWAY')),
			);
	}

	public function hookDisplayBackOfficeHeader()
	{
		$this->context->controller->addJquery();
		$this->context->controller->addJS(($this->_path) . 'views/js/admin.js');	
	}
	
	private function _displayCheck()
	{
		return $this->display(__FILE__, './views/templates/hook/infos.tpl');
	}

	protected function pluralxtInput()
	{
		global $smarty, $cart;
	
		$gateway_mode = Configuration::get('PLURALXTPINELABS_GATEWAYMODE');		
		$ppc_MerchantID = Configuration::get('PLURALXTPINELABS_MERCHANTID');
		$ppc_PayModeOnLandingPage = Configuration::get('PLURALXTPINELABS_PAYMENTMODES');
		$ppc_MerchantAccessCode = Configuration::get('PLURALXTPINELABS_ACCESSCODE');
		$secret_key = Configuration::get('PLURALXTPINELABS_SECRETKEY');
		$preferred_gateway = Configuration::get('PLURALXTPINELABS_PREFERRED_GATEWAY');

		$ppc_MerchantReturnURL = '';
		$ppc_MerchantProductInfo = '';

		$ppc_UniqueMerchantTxnID = $cart->id . '_' . date("ymdHis");
		$customer = new Customer($cart->id_customer);
			
		$id_currency = intval(Configuration::get('PS_CURRENCY_DEFAULT'));
		$currency = new Currency(intval($id_currency));
		// $currency_code =$currency->iso_code;
		$ppc_Amount =intval(floatval(number_format(Tools::convertPrice($cart->getOrderTotal(),$currency), 2, '.', '')) * 100);
			
		$ppc_MerchantReturnURL = $this->context->link->getModuleLink($this->name, 'validation', array(), true);

		$ppc_MerchantProductInfo = '';

		$product_info_data = new \stdClass();

		//get the unique products in cart
		$products = $cart->getProducts();

		$k = 0;

		for ($i = 0; $i < count($products); $i++)
		{
			$product = $products[$i];

			//iterate the cart_quantity of a particular product 
			for ($j = 0; $j < $product['cart_quantity']; $j++)
			{
				$ppc_MerchantProductInfo .= $product['name'] . '|';

				$product_details = new \stdClass();
				$product_details->product_code = $product['reference'];
				$product_details->product_amount = intval(floatval($product['price_with_reduction']) * 100);
				
				$product_info_data->product_details[$k++] = $product_details;
			}
		}

		$ppc_MerchantProductInfo = substr($ppc_MerchantProductInfo, 0, -1);

		$ppc_CustomerFirstName 	= '';
		$ppc_CustomerState 		= '';
		$ppc_CustomerCountry 	= '';
		$ppc_CustomerCity 		= '';
		$ppc_CustomerLastName 	= '';
		$ppc_CustomerAddress1 	= '';
		$ppc_CustomerAddress2 	= '';
		$ppc_CustomerAddressPIN = '';
		$ppc_CustomerEmail 		= '';
		$ppc_CustomerMobile 	= '';

		if (isset($cart->id_address_invoice))
		{
			$billing_address = new Address($cart->id_address_invoice);

			$ppc_CustomerEmail 		= $customer->email;
			$ppc_CustomerFirstName 	= $billing_address->firstname;
			$ppc_CustomerLastName 	= $billing_address->lastname;
			$ppc_CustomerMobile 	= !empty($billing_address->phone_mobile) ? $billing_address->phone_mobile : $billing_address->phone;
			$ppc_CustomerAddress1 	= $billing_address->address1;
			$ppc_CustomerAddress2 	= !empty($billing_address->address2) ? $billing_address->address2 : '';
			$ppc_CustomerCity 		= $billing_address->city;
        	$ppc_CustomerState 		= (new State($billing_address->id_state))->getNameById($billing_address->id_state);
        	$ppc_CustomerCountry 	= $billing_address->country;
			$ppc_CustomerAddressPIN = preg_replace('/\s+/', '', $billing_address->postcode);	        	
		}

		$ppc_ShippingFirstName 	 = '';
		$ppc_ShippingLastName 	 = '';
		$ppc_ShippingAddress1 	 = '';
		$ppc_ShippingAddress2 	 = '';
		$ppc_ShippingCity 		 = '';
		$ppc_ShippingState 		 = '';
		$ppc_ShippingCountry 	 = '';
		$ppc_ShippingZipCode 	 = '';
		$ppc_ShippingPhoneNumber = '';

		if (isset($cart->id_address_delivery))
		{
			$shipping_address = new Address($cart->id_address_delivery);
			
			$ppc_ShippingFirstName 	 = $shipping_address->firstname;
			$ppc_ShippingLastName 	 = $shipping_address->lastname;
			$ppc_ShippingAddress1 	 = $shipping_address->address1;
			$ppc_ShippingAddress2 	 = !empty($shipping_address->address2) ? $shipping_address->address2 : '';			
        	$ppc_ShippingCity 		 = $shipping_address->city;
        	$ppc_ShippingState 		 = (new State($shipping_address->id_state))->getNameById($shipping_address->id_state);
        	$ppc_ShippingCountry 	 = $shipping_address->country;
        	$ppc_ShippingZipCode 	 = preg_replace('/\s+/', '', $shipping_address->postcode);
        	$ppc_ShippingPhoneNumber = !empty($shipping_address->phone_mobile) ? $shipping_address->phone_mobile : $shipping_address->phone;
		}

        $ppc_UdfField1 = 'PrestaShop_v_1.7.6';

		$merchant_data = new \stdClass();		
		$merchant_data->merchant_return_url = $ppc_MerchantReturnURL;
		$merchant_data->merchant_access_code = $ppc_MerchantAccessCode;
		$merchant_data->order_id = $ppc_UniqueMerchantTxnID;
		$merchant_data->merchant_id = $ppc_MerchantID;

		$payment_info_data = new \stdClass();
		$payment_info_data->amount = $ppc_Amount;
		$payment_info_data->currency_code = "INR";
		$payment_info_data->preferred_gateway = $preferred_gateway;
		$payment_info_data->order_desc = $ppc_MerchantProductInfo;

		$customer_data = new \stdClass();
		// $customer_data->customer_id = $cart->id_customer;
		$customer_data->customer_ref_no = $cart->id_customer;
		// $customer_data->mobile_no = $ppc_CustomerMobile;
		$customer_data->mobile_number = $ppc_CustomerMobile;
		$customer_data->email_id = $ppc_CustomerEmail;
		$customer_data->first_name = $ppc_CustomerFirstName;
		$customer_data->last_name = $ppc_CustomerLastName;
		$customer_data->country_code = "91";

		$billing_address_data = new \stdClass();
		$billing_address_data->first_name = $ppc_CustomerFirstName;
		$billing_address_data->last_name = $ppc_CustomerLastName;
		$billing_address_data->address1 = $ppc_CustomerAddress1;
		$billing_address_data->address2 = $ppc_CustomerAddress2;
		$billing_address_data->address3 = "";
		$billing_address_data->pincode = $ppc_CustomerAddressPIN;
		$billing_address_data->city = $ppc_CustomerCity;
		$billing_address_data->state = $ppc_CustomerState;
		$billing_address_data->country = $ppc_CustomerCountry;

		$shipping_address_data = new \stdClass();
		$shipping_address_data->first_name = $ppc_ShippingFirstName;
		$shipping_address_data->last_name = $ppc_ShippingLastName;
		$shipping_address_data->address1 = $ppc_ShippingAddress1;
		$shipping_address_data->address2 = $ppc_ShippingAddress2;
		$shipping_address_data->address3 = "";
		$shipping_address_data->pincode = $ppc_ShippingZipCode;
		$shipping_address_data->city = $ppc_ShippingCity;
		$shipping_address_data->state = $ppc_ShippingState;
		$shipping_address_data->country = $ppc_ShippingCountry;

		$additional_info_data = new \stdClass();
		$additional_info_data->rfu1 = '';//$ppc_UdfField1;      

		$orderData = new \stdClass();

		$orderData->merchant_data = $merchant_data;
		$orderData->payment_info_data = $payment_info_data;
		$orderData->customer_data = $customer_data;
		$orderData->billing_address_data = $billing_address_data;
		$orderData->shipping_address_data = $shipping_address_data;
		$orderData->product_info_data = $product_info_data;
		$orderData->additional_info_data = $additional_info_data;

		$orderData = json_encode($orderData);

		$requestData = new \stdClass();
		$requestData->request = base64_encode($orderData);

		$x_verify = strtoupper(hash_hmac("sha256", $requestData->request, $this->Hex2Str($secret_key)));

		$requestData = json_encode($requestData);

		$formdata = array();

		$formdata['order_request'] = $requestData;
		$formdata['x_verify'] = $x_verify;
		$formdata['gateway_mode'] = $gateway_mode;
		$formdata['ppc_PayModeOnLandingPage'] = $ppc_PayModeOnLandingPage;
		$formdata['amount_in_paisa'] = $ppc_Amount;
		
		$inputs = array();
		
		foreach ($formdata as $k => $v)
		{
			$inputs[$k] = array(
								'name' => $k,
								'type' => 'hidden',
								'value' => $v,						
							);	
		}

		return $inputs;
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


?>