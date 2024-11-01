<?php

/*********************************************************************/
/*  PROGRAM          FlexRC                                          */
/*  PROPERTY         604-1097 View St                                 */
/*  OF               Victoria BC   V8V 0G9                          */
/*  				 Voice 604 800-7879                              */
/*                                                                   */
/*  Any usage / copying / extension or modification without          */
/*  prior authorization is prohibited                                */
/*********************************************************************/

namespace OneTeamSoftware\WooCommerce\Shipping\Adapter;

defined('ABSPATH') || exit;

if (!class_exists(__NAMESPACE__ . '\\ShipEngine')):

require_once(__DIR__ . '/AbstractAdapter.php');

class ShipEngine extends AbstractAdapter
{
	protected $testApiKey;
	protected $productionApiKey;

	// we don't want these properties overwritten by settings
	protected $_carrierAccounts;

	const MAX_DESCRIPTION_LENGTH = 45;

	public function __construct($id, array $settings = array())
	{
		$this->testApiKey = null;
		$this->productionApiKey = null;

		parent::__construct($id, $settings);

		$this->currencies = array(
			'usd' => __('USD', $this->id),
			'cad' => __('CAD', $this->id),
			'aud' => __('AUD', $this->id),
			'gbp' => __('GBP', $this->id),
			'eur' => __('EUR', $this->id),
			'nzd' => __('NZD', $this->id),
		);

		$this->contentTypes = array(
			'merchandise' => __('Merchandise', $this->id),
			'documents' => __('Documents', $this->id),
			'gift' => __('Gift', $this->id),
			'returned_goods' => __('Returned Goods', $this->id),
			'sample' => __('Sample', $this->id),
		);

		$this->packageTypes = array();
		$this->packageTypes['package'] = __('Package', $this->id);
	}

	public function setSettings(array $settings)
	{
		parent::setSettings($settings);

		if (!empty($this->getApiKey())) {
			$this->initShipEngine();
		}
	}

	public function getName()
	{
		return 'ShipEngine';
	}

	public function hasCustomItemsFeature()
	{
		return true;
	}

	public function hasTariffFeature()
	{
		return true;
	}

	public function hasUseSellerAddressFeature()
	{
		return true;
	}

	public function hasAddressValidationFeature()
	{
		return true;
	}

	public function hasReturnLabelFeature()
	{
		return true;
	}

	public function hasOriginFeature()
	{
		return true;
	}

	public function hasInsuranceFeature()
	{
		return true;
	}

	public function hasSignatureFeature()
	{
		return true;
	}

	public function hasDisplayDeliveryTimeFeature()
	{
		return true;
	}

	public function hasCreateShipmentFeature()
	{
		return true;
	}

	public function hasUpdateShipmentsFeature()
	{
		return true;
	}

	public function hasImportShipmentsFeature()
	{
		return false;
	}

	public function validate(array $settings)
	{
		$errors = array();

		$apiTokenKey = 'productionApiKey';
		if (isset($settings['sandbox']) && $settings['sandbox'] == 'yes') {
			$apiTokenKey = 'testApiKey';
		}

		if (empty($settings[$apiTokenKey])) {
			$formFields = $this->getIntegrationFormFields();
			$errors[] = sprintf('<strong>%s:</strong> %s', $formFields[$apiTokenKey]['title'], __('is required for the integration to work', $this->id));
		} else {
			$this->setSettings($settings);
			
			update_option($this->getCarriersCacheKey(), false);
			$this->_carriers = array();
			
			$errorMessage = $this->initShipEngine();
			if (!empty($errorMessage)) {
				$errors[] = $errorMessage;
			} else if (empty($this->_carrierAccounts)) {
				$errors[] = $this->getNoCarrierAccountsErrorMessage();
			}
		}

		return $errors;
	}

	public function getIntegrationFormFields()
	{
		$formFields = array(
			'testApiKey' => array(
				'title' => __('Test API Key', $this->id),
				'type' => 'text',
				'description' => sprintf('%s <a href="https://app.shipengine.com/#/portal/sandbox" target="_blank">ShipEngine.com -> Sandbox</a>', __('You can find it at', $this->id)),
			),
			'productionApiKey' => array(
				'title' => __('Production API Key', $this->id),
				'type' => 'text',
				'description' => sprintf('%s <a href="https://app.shipengine.com/#/portal/apimanagement" target="_blank">ShipEngine.com -> API Management</a>', __('You can find it at', $this->id)),
			)
		);

		return $formFields;
	}

	public function getRates(array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRates');

		if (empty($this->_carrierAccounts)) {
			$this->logger->debug(__FILE__, __LINE__, 'No carrier accounts have been found');

			$response = array();
			$response['error']['message'] = $this->getNoCarrierAccountsErrorMessage();

			return $response;
		}

		$cacheKey = $this->getRatesCacheKey($params);
		$response = $this->getCacheValue($cacheKey);
		if (empty($response)) {
			$params['function'] = __FUNCTION__;
			$response = $this->sendRequest('v1/rates', 'POST', $params);

			if (!empty($response['shipment'])) {
				$this->logger->debug(__FILE__, __LINE__, 'Cache shipment for the future');
				$this->setCacheValue($cacheKey, $response, $this->cacheExpirationInSecs);
			}
		} else {
			$this->logger->debug(__FILE__, __LINE__, 'Found previously returned rates with, so return them. Cache Key: ' . $cacheKey);
		}

		if (!empty($response['shipment']) && !empty($params['destination']) && $this->validateAddress) {
			$validationResponse = $this->validateAddress($params['destination']);
			if (!empty($validationResponse['errors'])) {
				$response['validation_errors']['destination'] = $validationResponse['errors'];
			}
		}

		return $response;
	}

	public function getCacheKey(array $params)
	{
		$cacheKey = parent::getCacheKey($params);
		$cacheKey .= '_' . $this->getApiKey();

		return md5($cacheKey);
	}

	protected function getRatesCacheKey(array $params)
	{
		$params['validateAddress'] = $this->validateAddress;

		if (isset($params['service'])) {
			unset($params['service']);
		}

		if (isset($params['carrier_id'])) {
			unset($params['carrier_id']);
		}

		if (isset($params['service_code'])) {
			unset($params['service_code']);
		}

		if (isset($params['function'])) {
			unset($params['function']);
		}

		return $this->getCacheKey($params) . '_rates';
	}

	protected function getRequestBody(&$headers, &$params)
	{
		$headers['Content-Type'] = 'application/json';

		return json_encode($params);
	}

	protected function getValidateAddressParams(array $address)
	{
		$params = array();
		$params[0] = $this->prepareAddress($address);

		return $params;
	}

	protected function getRatesParams(array $inParams)
	{
		if (empty($inParams['origin'])) {
			$inParams['origin'] = $this->origin;
		}

		if (empty($inParams['destination'])) {
			$inParams['destination'] = array();
		}

		$insurance = $this->insurance;
		if (isset($inParams['insurance'])) {
			$insurance = filter_var($inParams['insurance'], FILTER_VALIDATE_BOOLEAN);
		}
		$inParams['insurance'] = $insurance;

		$params = array();
		$params['rate_options']['carrier_ids'] = array_values(!empty($this->_carrierAccounts) ? $this->_carrierAccounts : array());
		$params['rate_options']['preferred_currency'] = $this->prepareCurrency($inParams);
		$params['shipment']['validate_address'] = 'no_validation';		
		$params['shipment']['ship_date'] = $this->getShipDate();
		$params['shipment']['ship_from'] = $this->prepareAddress($inParams['origin']);
		$params['shipment']['ship_to'] = $this->prepareAddress($inParams['destination']);
		$params['shipment']['confirmation'] = $this->getConfirmation($inParams);
		$params['shipment']['customs'] = $this->prepareCustomsInfo($inParams);
		$params['shipment']['packages'][0] = $this->preparePackage($inParams);

		if (!empty($inParams['insurance']) && !empty($inParams['value'])) {
			$params['shipment']['insurance_provider'] = 'carrier';
		}

		return $params;
	}
	
	protected function getShipDate()
	{
		$shipTime = strtotime('now');

		if (date('w', $shipTime) % 6 == 0 || intval(date('H', $shipTime)) > 16) {
			// if weekend or after 4pm then take next business day at noon
			$shipTime = strtotime('now + 1 Weekday noon');
		} else if (intval(date('H', $shipTime)) < 9) {
			// if before 9am then use today noon
			$shipTime = strtotime('today noon');
		}

		return date('Y-m-d', $shipTime);		
	}

	protected function prepareCurrency(array $inParams)
	{
		$currency = 'usd';
		if (isset($inParams['currency'])) {
			$possibleCurrency = strtolower($inParams['currency']);

			if (isset($this->currencies[$possibleCurrency])) {
				$currency = $possibleCurrency;
			}	
		}

		return $currency;
	}

	protected function preparePackage(array $inParams)
	{
		$package = array();

		if (!empty($inParams['type']) && isset($this->packageTypes[$inParams['type']])) {
			$package['package_code'] = $inParams['type'];
		}

		$package['weight'] = $this->getWeightArray($inParams);
		$package['dimensions'] = $this->getDimensionsArray($inParams);

		if (!empty($inParams['insurance']) && !empty($inParams['value'])) {
			$currency = 'usd';
			if (isset($inParams['currency'])) {
				$currency = strtolower($inParams['currency']);
			}

			$package['insured_value']['currency'] = $this->prepareCurrency($inParams);
			$package['insured_value']['amount'] = $inParams['value'];
		}

		return $package;
	}

	protected function prepareAddress($options)
	{
		$addr = array();

		$addr['address_residential_indicator'] = 'yes';

		if (!empty($options['name'])) {
			$addr['name'] = $options['name'];
		} else {
			$addr['name'] = 'Resident';
		}

		if (!empty($options['company'])) {
			$addr['company_name'] = $options['company'];
			$addr['address_residential_indicator'] = 'no';

			if (empty($options['name'])) {
				$addr['name'] = $options['company'];
			}
		}

		if (!empty($options['phone'])) {
			if (is_array($options['phone'])) {
				$options['phone'] = current($options['phone']);
			}
			
			$addr['phone'] = $options['phone'];
		} else {
			$addr['phone'] = '10000000000';
		}

		if (isset($options['country'])) {
			$addr['country_code'] = strtoupper($options['country']);
		}

		if (isset($options['state'])) {
			$addr['state_province'] = $options['state'];
		}

		if (isset($options['postcode'])) {
			$addr['postal_code'] = $options['postcode'];
		}

		if (isset($options['city'])) {
			$addr['city_locality'] = $options['city'];
		}

		if (!empty($options['address'])) {
			$addr['address_line1'] = $options['address'];
		}

		if (isset($options['address_2'])) {
			$addr['address_line2'] = $options['address_2'];
		}

		return $addr;
	}

	protected function prepareCustomsInfo(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'prepareCustomsInfo');

		//if (isset($inParams['origin']['country']) 
		//	&& isset($inParams['destination']['country'])
		//	&& $inParams['origin']['country'] == $inParams['destination']['country']) {
		//	$this->logger->debug(__FILE__, __LINE__, 'Order is local, so no need for customs info');

		//	return null;
		//}

		$customsInfo = array(
			'non_delivery' => 'return_to_sender'
		);

		if (!empty($inParams['contents']) && !empty($this->contentTypes[$inParams['contents']])) {
			$customsInfo['contents'] = $inParams['contents'];
		} else {
			$customsInfo['contents'] = current($this->contentTypes);
		}

		if (!empty($inParams['items']) && is_array($inParams['items'])) {
			$customsInfo['customs_items'] = $this->prepareCustomsItems($inParams);
		}

		$this->logger->debug(__FILE__, __LINE__, 'Customs Info: ' . print_r($customsInfo, true));

		return $customsInfo;
	}

	protected function prepareCustomsItems(array $inParams)
	{
		if (empty($inParams['items'])) {
			return array();
		}

		$this->logger->debug(__FILE__, __LINE__, 'prepareCustomsItems');

		$itemsInParcel = $inParams['items'];
		$defaultOriginCountry = strtoupper($inParams['origin']['country']);
		$currency = 'usd';
		if (!empty($inParams['currency'])) {
			$currency = strtolower($inParams['currency']);
		}

		$customsItems = array();

		foreach ($itemsInParcel as $itemInParcel) {
			if (empty($itemInParcel['country'])) {
				$itemInParcel['country'] = $defaultOriginCountry;
			}

			$itemInParcel['currency'] = $currency;

			$customsItem = $this->prepareCustomsItem($itemInParcel);
			if (!empty($customsItem)) {
				$customsItems[] = $customsItem;
			}
		}
		
		return $customsItems;
	}

	protected function prepareCustomsItem($itemInParcel)
	{
		if (empty($itemInParcel['name']) || 
			empty($itemInParcel['quantity']) || 
			!isset($itemInParcel['value'])) {
			$this->logger->debug(__FILE__, __LINE__, 'Item is invalid, so skip it ' . print_r($itemInParcel, true));

			return false;
		}

		$value = $itemInParcel['value'];

		$tariff = $this->defaultTariff;
		if (!empty($itemInParcel['tariff'])) {
			$tariff = $itemInParcel['tariff'];
		}

		$customsItem['description'] = substr($itemInParcel['name'], 0, min(self::MAX_DESCRIPTION_LENGTH, strlen($itemInParcel['name'])));
		$customsItem['quantity'] = $itemInParcel['quantity'];
		$customsItem['value']['amount'] = round($value, 3);
		$customsItem['value']['currency'] = $itemInParcel['currency'];
		$customsItem['country_of_origin'] = $itemInParcel['country'];
		$customsItem['harmonized_tariff_code'] = $tariff;

		return $customsItem;
	}

	protected function getConfirmation(array $inParams)
	{
		$confirmation = 'none';

		$signature = $this->signature;
		if (isset($inParams['signature'])) {
			$signature = filter_var($inParams['signature'], FILTER_VALIDATE_BOOLEAN);
		}

		if ($signature) {
			$confirmation = 'signature';
		}
		
		return $confirmation;
	}

	protected function getWeightArray(array $inParams)
	{
		$weightArray = array();

		if (!empty($inParams['weight'])) {
			$weightArray['value'] = round($inParams['weight'], 3);
		} else {
			$weightArray['value'] = 0;
		}

		$weightUnit = $this->weightUnit;
		if (isset($inParams['weight_unit'])) {
			$weightUnit = $inParams['weight_unit'];
		}

		if ($weightUnit == 'g') {
			$weightArray['unit'] = 'gram';
		} else if ($weightUnit == 'kg') {
			$weightArray['unit'] = 'kilogram';
		} else if ($weightUnit == 'lbs') {
			$weightArray['unit'] = 'pound';
		} else if ($weightUnit == 'oz') {
			$weightArray['unit'] = 'ounce';
		}

		return $weightArray;
	}
	
	protected function getDimensionsArray(array $inParams)
	{
		$dimensionsArray = array();

		if (isset($inParams['length'])) {
			$dimensionsArray['length'] = round($inParams['length'], 3);
		} else {
			$dimensionsArray['length'] = 0;
		}

		if (isset($inParams['width'])) {
			$dimensionsArray['width'] = round($inParams['width'], 3);
		} else {
			$dimensionsArray['width'] = 0;
		}

		if (isset($inParams['height'])) {
			$dimensionsArray['height'] = round($inParams['height'], 3);
		} else {
			$dimensionsArray['height'] = 0;
		}

		$dimensionUnit = $this->dimensionUnit;
		if (isset($inParams['dimension_unit'])) {
			$dimensionUnit = $inParams['dimension_unit'];
		}

		if ($dimensionUnit == 'cm') {
			$dimensionUnit = 'centimeter';
		} else if ($dimensionUnit == 'm') {
			$dimensionUnit = 'centimeter';
			$dimensionsArray['length'] *= 100;
			$dimensionsArray['width'] *= 100;
			$dimensionsArray['height'] *= 100;
		} else if ($dimensionUnit == 'mm') {
			$dimensionUnit = 'centimeter';
			$dimensionsArray['length'] /= 10;
			$dimensionsArray['width'] /= 10;
			$dimensionsArray['height'] /= 10;
		} else if ($dimensionUnit == 'in') {
			$dimensionUnit = 'inch';
		}

		$dimensionsArray['unit'] = $dimensionUnit;
		
		return $dimensionsArray;
	}

	protected function getRequestParams(array $inParams)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getRequestParams: ' . print_r($inParams, true));

		$function = null;
		if (!empty($inParams['function'])) {
			$function = $inParams['function'];
		}

		$params = array();
		if ($function == 'validateAddress') {
			$params = $this->getValidateAddressParams($inParams);
		} else if ($function == 'getRates') {
			$params = $this->getRatesParams($inParams);
		} else if (in_array($function, array('fetchCarriers'))) {
			$params = array();
		}

		return $params;
	}

	protected function getErrorMessages($errors)
	{
		$errorMessages = array();

		if (!empty($errors) && is_array($errors)) {
			foreach ($errors as $error) {
				$errorMessages[] = $error['message'];
			}
		}

		return $errorMessages;
	}

	protected function getErrorMessage($errors)
	{
		$messages = $this->getErrorMessages($errors);
		$message = implode("\n", $messages);

		return trim($message);
	}

	protected function getFetchCarriersResponse($response, array $params)
	{
		if (empty($response['carriers'])) {
			return array();
		}

		$newResponse = array();
		foreach ($response['carriers'] as $carrierInfo) {
			$carrierCode = $carrierInfo['carrier_code'];
			$carrierId = $carrierInfo['carrier_id'];
			$carrierAccountId = $this->getServiceId($carrierCode, $carrierId);

			$newResponse['carriers'][$carrierCode] = $carrierInfo['friendly_name'];
			$newResponse['carrierAccounts'][$carrierAccountId] = $carrierId;

			if (!empty($carrierInfo['services']) && is_array($carrierInfo['services'])) {
				foreach ($carrierInfo['services'] as $serviceInfo) {
					$serviceCode = $serviceInfo['service_code'];
					$serviceId = $this->getServiceId($carrierId, $serviceCode);
					
					$newResponse['services'][$serviceId] = $serviceInfo['name'];
				}	
			}
			
			if (!empty($carrierInfo['packages']) && is_array($carrierInfo['packages'])) {
				foreach ($carrierInfo['packages'] as $packageInfo) {
					$packageCode = $packageInfo['package_code'];
					$newResponse['packageTypes'][$packageCode] = $packageInfo['name'];
				}	
			}
		}

		return $newResponse;
	}

	protected function getValidateAddressResponse($response, array $params)
	{
		if (empty($response) || !is_array($response) || empty($response[0]) || !is_array($response[0])) {
			return array();
		}

		$newResponse = array();

		if (isset($response[0]['status']) && $response[0]['status'] != 'verified') {
			$newResponse['errors'] = $this->getErrorMessages($response[0]['messages']);
		}

		return $newResponse;
	}

	protected function getRatesResponse($response, array $params)
	{
		if (empty($response) || !is_array($response)) {
			return array();
		}

		$rates = array();
		$theirRates = array();
		if (!empty($response['rate_response']['rates'])) {
			$theirRates = $response['rate_response']['rates'];
		} else if (!empty($response['rate_response']['invalid_rates'])) {
			$theirRates = $response['rate_response']['invalid_rates'];
		}

		foreach ($theirRates as $rate) {
			$serviceId = $this->getServiceId($rate['carrier_id'], $rate['service_code']);
			$serviceName = $rate['service_type'];

			if (!empty($this->_services[$serviceId])) {
				$serviceName = $this->_services[$serviceId];
			}

			$rate['service'] = $serviceId;
			$rate['postage_description'] = apply_filters($this->id . '_service_name', $serviceName, $serviceId);
			$rate['cost'] = 0;

			if (!empty($rate['shipping_amount']['amount'])) {
				$rate['cost'] += floatval($rate['shipping_amount']['amount']);
			}

			if (!empty($rate['insurance_amount']['amount'])) {
				$rate['cost'] += floatval($rate['insurance_amount']['amount']);
			}

			if (!empty($rate['confirmation_amount']['amount'])) {
				$rate['cost'] += floatval($rate['confirmation_amount']['amount']);
			}

			if (!empty($rate['other_amount']['amount'])) {
				$rate['cost'] += floatval($rate['other_amount']['amount']);
			}

			$rate['tracking_type_description'] = '';
			$rate['delivery_time_description'] = '';

			if (!empty($rate['delivery_days']) && is_numeric($rate['delivery_days'])) {
				$rate['delivery_time_description'] = sprintf(__('Estimated delivery in %d days', $this->id), $rate['delivery_days']);
			}

			$rates[$serviceId] = $rate;
		}
		
		$newResponse['shipment']['rates'] = $this->sortRates($rates);

		return $newResponse;
	}

	protected function getResponse($response, array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'getResponse');
		
		$newResponse = array('response' => $response, 'params' => $params);

		if (!empty($response['errors'])) {
			$errorMessage = $this->getErrorMessage($response['errors']);
			if (!empty($errorMessage)) {
				$newResponse['error']['message'] = $errorMessage;

				$this->logger->debug(__FILE__, __LINE__, 'Error Message: ' . $errorMessage);
			}
		}

		$function = null;
		if (!empty($params['function'])) {
			$function = $params['function'];
		}

		if ($function == 'validateAddress') {
			$newResponse = array_replace_recursive($newResponse, $this->getValidateAddressResponse($response, $params));
		}

		if ($function == 'getRates') {
			$newResponse = array_replace_recursive($newResponse, $this->getRatesResponse($response, $params));
		}

		if (in_array($function, array('fetchCarriers'))) {
			$newResponse = array_replace_recursive($newResponse, $this->getFetchCarriersResponse($response, $params));
		}

		return $newResponse;
	}

	protected function getRouteUrl($route)
	{
		$routeUrl = sprintf('https://api.shipengine.com/%s', $route);

		return $routeUrl;
	}

	protected function getApiKey()
	{
		return $this->sandbox ? $this->testApiKey : $this->productionApiKey;
	}

	protected function addHeadersAndParams(&$headers, &$params)
	{
		$headers['API-Key'] = $this->getApiKey();
	}

	public function getServices()
	{
		return $this->_services;
	}

	protected function validateAddress(array $params)
	{
		$this->logger->debug(__FILE__, __LINE__, 'validateAddress');

		$cacheKey = $this->getCacheKey($params);
		$response = $this->getCacheValue($cacheKey);
		if (empty($response)) {
			$params['function'] = __FUNCTION__;
			$response = $this->sendRequest('v1/addresses/validate', 'POST', $params);

			$this->setCacheValue($cacheKey, $response);
		}

		return $response;
	}
	
	protected function getCarriersCacheKey()
	{
		return $this->id . '_' . $this->getApiKey() . '_carriers';
	}

	protected function fetchCarriers()
	{
		$params = array('function' => __FUNCTION__);
		return $this->sendRequest('v1/carriers', 'GET', $params);
	}

	protected function initShipEngine()
	{
		if (!empty($this->_carriers)) {
			return '';
		}

		$errorMessage = '';

		$response = get_option($this->getCarriersCacheKey(), false);
		if (!is_array($response)) {
			// prevent other instances from fetching
			update_option($this->getCarriersCacheKey(), array());

			// backward compatibility
			$cacheKey = $this->id . '_' . __FUNCTION__;
			$response = $this->getCacheValue($cacheKey, true);
			if (empty($response)) {
				$response = $this->fetchCarriers();
				if (!empty($response['error']['message'])) {
					$errorMessage = $response['error']['message'];
					$response = array();
				}
			} else {
				$this->deleteCacheValue($cacheKey);
			}
			update_option($this->getCarriersCacheKey(), $response);				
		}
		
		if (!empty($response['carriers'])) {
			$this->_carriers = $response['carriers'];
		}

		if (!empty($response['carrierAccounts'])) {
			$this->_carrierAccounts = $response['carrierAccounts'];
		}

		if (!empty($response['services'])) {
			$this->_services = $response['services'];
		}

		if (!empty($response['packageTypes'])) {
			$this->packageTypes = array_merge($this->packageTypes, $response['packageTypes']);
		}

		return $errorMessage;
	}

	protected function getServiceId($carrierCode, $serviceCode)
	{
		$serviceId = $carrierCode . '|' . $serviceCode;

		return $serviceId;
	}

	protected function getNoCarrierAccountsErrorMessage()
	{
		return __('No carrier accounts have been found.', $this->id);
	}
}

endif;
