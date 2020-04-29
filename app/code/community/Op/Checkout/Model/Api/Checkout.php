<?php
include __DIR__.'/guzzle.phar';

class Op_Checkout_Model_Api_Checkout extends Mage_Core_Model_Abstract
{
    const API_ENDPOINT = 'https://api.checkout.fi';

    /**
     * @var string MODULE_NAME
     */
    const MODULE_NAME = 'Op_Checkout'; // in config.xml config -> modules -> Op_Checkout

    const DEFAULT_PAYMENT_PROVIDER_QUERY_AMOUNT = 2500;

    protected $checkoutApi;
    protected $merchantId;
    protected $merchantSecret;
    protected $quote;

    public function __construct()
    {
        parent::__construct();
        $this->checkoutApi = Mage::Helper('opcheckout');
        $this->merchantId = Mage::getStoreConfig('payment/opcheckout/merchant_id');
        $this->merchantSecret =  Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/opcheckout/auth_code'));
    }

    public function getMerchantSecret()
    {
        return $this->merchantSecret;
    }

    public function getMerchantId()
    {
        return $this->merchantId;
    }

    public function getResponse($uri, $order, $method, $refundId = null, $refundBody = null)
    {
        $method = strtoupper($method);
        $headers = $this->getResponseHeaders($method);
        $body = '';

        if ($method == 'POST' && !empty($order)) {
            $body = $this->getResponseBody($order);
        }
        if ($refundId) {
            $headers['checkout-transaction-id'] = $refundId;
            $body = $refundBody;
        }

        $headers['signature'] = $this->calculateHmac($headers, $body, $this->merchantSecret);

        $client = new \GuzzleHttp\Client(['headers' => $headers]);

        $response = null;


        try {
            if ($method == 'POST') {
                $response = $client->post(self::API_ENDPOINT . $uri, ['body' => $body]);
            } else {
                $response = $client->get(self::API_ENDPOINT . $uri, ['body' => '']);
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if ($e->hasResponse()) {
                Mage::log('Connection error to Checkout API: ' . $e->getMessage(), null, 'op_checkout.log', true);
                $response["data"] = $e->getMessage();
                $response["status"] = $e->getCode();
            }
            return $response;
        }

        $responseBody = $response->getBody()->getContents();

        $responseHeaders = array_column(
            array_map(function ($key, $value) {
                return [$key, $value[0]];
            },
                array_keys($response->getHeaders()),
                array_values($response->getHeaders())),
            1,
            0
        );

        $responseHmac = $this->calculateHmac($responseHeaders, $responseBody, $this->merchantSecret);
        $responseSignature = $response->getHeader('signature')[0];

        if ($responseHmac == $responseSignature) {
            $data = array(
                'status' => $response->getStatusCode(),
                'data' => json_decode($responseBody)
            );

            return $data;
        }
    }

    public function getEnabledPaymentMethodGroups()
    {
        $responseData = $this->getAllPaymentMethods();

        $groupData = $this->getEnabledPaymentGroups($responseData);
        $groups = [];

        foreach ($groupData as $group) {
            $groups[] = [
                'id' => $group,
                'title' => Mage::helper('opcheckout')->__($group)
            ];
        }

        // Add methods to groups
        foreach ($groups as $key => $group) {
            $groups[$key]['methods'] = $this->getEnabledPaymentMethodsByGroup($responseData, $group['id']);

            // Remove empty groups
            if (empty($groups[$key]['methods'])) {
                unset($groups[$key]);
            }
        }

        return array_values($groups);
    }

    protected function getAllPaymentMethods()
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote =  Mage::getModel('checkout/session')->getQuote();
        $quoteData = $quote->getData();
        $grandTotal = $quoteData['grand_total'];

        $uri = '/merchants/payment-providers?amount=' . $grandTotal * 100;
        $method = 'get';
        $response = $this->getResponse($uri, '', $method);

        return $response['data'];
    }

    protected function getEnabledPaymentMethodsByGroup($responseData, $groupId)
    {
        $allMethods = [];

        foreach ($responseData as $provider) {
            $allMethods[] = [
                'value' => $provider->id,
                'label' => $provider->id,
                'group' => $provider->group,
                'icon' => $provider->svg
            ];
        }

        $i = 1;

        foreach ($allMethods as $key => $method) {
            if ($method['group'] == $groupId) {
                $methods[] = [
                    'checkoutId' => $method['value'],
                    'id' => $method['value'] . $i++,
                    'title' => $method['label'],
                    'group' => $method['group'],
                    'icon'  => $method['icon']
                ];
            }
        }

        return $methods;
    }

    protected function getEnabledPaymentGroups($responseData)
    {
        $allGroups = [];

        foreach ($responseData as $provider) {
            $allGroups[] = $provider->group;
        }

        return array_unique($allGroups);
    }

    public function getResponseHeaders($method)
    {
        return $headers = [
            'cof-plugin-version' => 'op-payment-service-for-magento-1-'. $this->getExtensionVersion(),
            'checkout-account' => $this->merchantId,
            'checkout-algorithm' => 'sha256',
            'checkout-method' => strtoupper($method),
            'checkout-nonce' => uniqid(true),
            'checkout-timestamp' => date('Y-m-d\TH:i:s.000\Z', time()),
            'content-type' => 'application/json; charset=utf-8',
        ];
    }

    public function calculateHmac(array $params = [], $body = null, $secretKey = null)
    {
        // Keep only checkout- params, more relevant for response validation.
        $includedKeys = array_filter(array_keys($params), function ($key) {
            return preg_match('/^checkout-/', $key);
        });
        // Keys must be sorted alphabetically
        sort($includedKeys, SORT_STRING);
        $hmacPayload = array_map(
            function ($key) use ($params) {
                // Responses have headers in an array.
                $param = is_array($params[ $key ]) ? $params[ $key ][0] : $params[ $key ];
                return join(':', [ $key, $param ]);
            },
            $includedKeys
        );
        array_push($hmacPayload, $body);
        return hash_hmac('sha256', join("\n", $hmacPayload), $secretKey);
    }

    public function validateHmac(
        array $params = [],
        $body = null,
        $signature = null,
        $secretKey = null
    ) {
        $hmac = static::calculateHmac($params, $body, $secretKey);
        if ($hmac !== $signature) {
            $this->log->critical('Response HMAC signature mismatch!');
        }
    }


    public function getResponseBody($order)
    {

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        // using json_encode for option.
        $body = json_encode(
            [
                'stamp' => hash('sha256', time() . $order->getIncrementId()),
                'reference' => $order->getIncrementId(),
                'amount' => $order->getGrandTotal() * 100,
                'currency' => $order->getOrderCurrencyCode(),
                'language' => 'FI',
                'items' => $this->getOrderItems($order),
                'customer' => [
                    'firstName' => $billingAddress->getFirstname(),
                    'lastName' => $billingAddress->getLastname(),
                    'phone' => $billingAddress->getTelephone(),
                    'email' => $billingAddress->getEmail(),
                ],
                'invoicingAddress' => $this->formatAddress($billingAddress),
                'deliveryAddress' => $this->formatAddress($shippingAddress),
                'redirectUrls' => [
                    'success' => $this->getReceiptUrl(),
                    'cancel' => $this->getReceiptUrl(),
                ],
                'callbackUrls' => [
                    'success' => $this->getReceiptUrl(),
                    'cancel' => $this->getReceiptUrl(),
                ],
            ],
            JSON_UNESCAPED_SLASHES
        );
        /*echo '<pre>';
        print_r(json_decode($body,true)); exit;*/
        return $body;
    }


    protected function formatAddress($address)
    {
        $country = Mage::getModel('directory/country')->loadByCode($address->getCountryId())->getName();

        $streetAddressRows = $address->getStreet();
        $streetAddress = $streetAddressRows[0];
        if (mb_strlen($streetAddress, 'utf-8') > 50) {
            $streetAddress = mb_substr($streetAddress, 0, 50, 'utf-8');
        }

        $result = [
            'streetAddress' => $streetAddress,
            'postalCode' => $address->getPostcode(),
            'city' => $address->getCity(),
            'country' => $country
        ];

        if (!empty($address->getRegion())) {
            $result["county"] = $address->getRegion();
        }

        return $result;
    }


    public function getOrderItems($order)
    {
        $items = [];

        foreach ($this->_itemArgs($order) as $i => $item) {
            $items[] = array(
                'unitPrice' => $item['price'] * 100,
                'units' => $item['amount'],
                'vatPercentage' => $item['vat'],
                'description' => $item['title'],
                'productCode' => $item['code'],
                'deliveryDate' => date('Y-m-d'),
            );
        }

        return $items;
    }

    public function _itemArgs($order)
    {
        $items = array();

        foreach ($order->getAllItems() as $key => $item) {

            if ($item->getChildrenItems() && !$item->getProductOptions()['product_calculations']) {
                $items[] = array(
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => 0,
                    'vat' => 0,
                    'discount' => 0,
                    'type' => 1,
                );
            } else {
                $items[] = array(
                    'title' => $item->getName(),
                    'code' => $item->getSku(),
                    'amount' => floatval($item->getQtyOrdered()),
                    'price' => floatval($item->getPriceInclTax()),
                    'vat' => round(floatval($item->getTaxPercent())),
                    'discount' => 0,
                    'type' => 1,
                );
            }
        }

        if (!$order->getIsVirtual()) {
            $shippingExclTax = $order->getShippingAmount();
            $shippingInclTax = $shippingExclTax + $order->getShippingTaxAmount();
            $shippingTaxPct = 0;
            if ($shippingExclTax > 0) {
                $shippingTaxPct = ($shippingInclTax - $shippingExclTax) / $shippingExclTax * 100;
            }

            if ($order->getShippingDescription()) {
                $shippingLabel = $order->getShippingDescription();
            } else {
                $shippingLabel = Mage::helper('opcheckout')->__('Shipping');
            }

            $items[] = array(
                'title' => $shippingLabel,
                'code' => '',
                'amount' => 1,
                'price' => $shippingInclTax,
                'vat' => round(floatval($shippingTaxPct)),
                'discount' => 0,
                'type' => 2,
            );
        }
        if (abs($order->getDiscountAmount()) > 0) {
            $discountData = $this->_getDiscountData($order);
            $discountInclTax = $discountData->getDiscountInclTax();
            $discountExclTax = $discountData->getDiscountExclTax();
            $discountTaxPct = 0;
            if ($discountExclTax > 0) {
                $discountTaxPct = ($discountInclTax - $discountExclTax) / $discountExclTax * 100;
            }

            if ($order->getDiscountDescription()) {
                $discountLabel = $order->getDiscountDescription();
            } else {
                $discountLabel = Mage::helper('opcheckout')->__('Discount');
            }

            $items[] = array(
                'title' => (string) $discountLabel,
                'code' => '',
                'amount' => -1,
                'price' => floatval($discountData->getDiscountInclTax()),
                'vat' => round(floatval($discountTaxPct)),
                'discount' => 0,
                'type' => 3
            );
        }
        return $items;
    }

    private function _getDiscountData($order)
    {
        $discountIncl = 0;
        $discountExcl = 0;

        // Get product discount amounts
        foreach ($order->getAllItems() as $item) {
            if (!Mage::helper('tax')->priceIncludesTax()) {
                $discountExcl += $item->getDiscountAmount();
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
                $discountIncl += $item->getDiscountAmount();
            }
        }

        // Get shipping tax rate
        if ((float) $order->getShippingInclTax() && (float) $order->getShippingAmount()) {
            $shippingTaxRate = $order->getShippingInclTax() / $order->getShippingAmount();
        } else {
            $shippingTaxRate = 1;
        }

        // Add / exclude shipping tax
        $shippingDiscount = (float) $order->getShippingDiscountAmount();
        if (!Mage::helper('tax')->priceIncludesTax()) {
            $discountIncl += $shippingDiscount * $shippingTaxRate;
            $discountExcl += $shippingDiscount;
        } else {
            $discountIncl += $shippingDiscount;
            $discountExcl += $shippingDiscount / $shippingTaxRate;
        }

        $return = new Varien_Object();
        return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
    }

    public function getReceiptUrl()
    {
        return Mage::getUrl("opcheckout/receipt", array("_secure" => true));
        return $receiptUrl;
    }

    /**
     * @return string
     */
    protected function getExtensionVersion() {
        /** @var string $version */
        $version = (string)Mage::getConfig()->getNode('modules/' . self::MODULE_NAME . '/version');
        return trim($version);
    }
}
