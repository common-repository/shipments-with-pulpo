<?php
include_once  dirname(dirname(__FILE__)) . '/PulpoThirdParty/pulpo_restclient.php';

use App\PulpoThirdParty\PulpoRestClient;


class PulpoService {
	private $DEBUG = true;
	const PULPO_TOKEN_TRANSIENT = 'pulpo_auth_token';
	private $BASE_URL = 'https://show.pulpo.co/api/v1/';
	private $GRANT_TYPE = 'password';
	private $SCOPE = 'profile';
	private $username;
	private $password;
	private $tenant_id;
	private $carrier_id;
	private $merchant_id;
	private $channel_id;
	private $mode3pl;
	private $env;

	private $access_token;
	private $connection;

	public function __construct( $force_env = null) {
		$env = get_option('pulpo_test_mode', 'yes') === 'yes'?'test':'prod';
		if (null !== $force_env) {
			$env = $force_env;
		}

		$this->env = $env;

		if (!in_array($env, ['test', 'prod'])) {
			throw new \Exception('Unknown Pulpo env configuration');
		}

		$this->BASE_URL = get_option("pulpo_{$env}_url");
		$this->username = get_option("pulpo_{$env}_username");
		$this->password = get_option("pulpo_{$env}_password");
		$this->tenant_id = intval(get_option("pulpo_{$env}_tenant_id"));
		$this->mode3pl = get_option("pulpo_{$env}_3plmode") === 'yes';
		$this->merchant_id = intval(get_option("pulpo_{$env}_merchant_id"));
		$this->channel_id = intval(get_option("pulpo_{$env}_channel_id"));
		$this->carrier_id = null;//get_option("pulpo_{$env}_shipping_method_id");

		if (!$this->BASE_URL || '' === $this->BASE_URL) {
			return null;
		}

		$this->connection = new PulpoRestClient([
			'base_url'  => $this->BASE_URL,
			'format'    => 'json'
		]);

		$this->connection->register_decoder('json', function( $data) {
			return json_decode($data, true);
		});

		$this->authenticate();
	}

	public function log( $type, $message) {
		$this->DEBUG && plugin_log("[{$type}] {$message}", 'a', PULPO_SHIPPING_LOG_FILENAME);
	}

	public function authenticate() {
		$this->log('info', __CLASS__ . '::' . __FUNCTION__);
		try {
			$this->access_token = $this->get_token();
		} catch (\Exception $e) {
			$this->access_token = $this->generate_access_token();
		}
	}

	protected function get_transient_token_id() {
		return self::PULPO_TOKEN_TRANSIENT . '_' . $this->env;
	}

	protected function get_token() {
		$val = get_transient($this->get_transient_token_id());

		if (false === $val) {
			throw new \Exception('No access token');
		}

		return $val;
	}

	protected function generate_access_token() {
		$grant_type = $this->GRANT_TYPE;
		$scope      = $this->SCOPE;

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> url:post: {$this->BASE_URL}auth");
		$response = $this->connection->post('auth',
			[
				'grant_type'    => $grant_type,
				'password'      => $this->password,
				'scope'         => $scope,
				'username'      => $this->username
			]);

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> http_code: {$response->info->http_code}");

		if (200 == $response->info->http_code) {
			$decode_response = $response->decode_response();
			if ($decode_response && isset($decode_response['access_token'])) {
				$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> token: {$decode_response['access_token']}");

				set_transient($this->get_transient_token_id(), $decode_response['access_token'], DAY_IN_SECONDS);

				return $decode_response['access_token'];
			}
		} else {
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . " -> error: {$response->response}");

			throw new \Exception('Error authenticating Pulpo');
		}
	}

	public static function clean_token_file() {
		$env = get_option('pulpo_test_mode', 'yes') === 'yes'?'test':'prod';
		$transient_id = self::PULPO_TOKEN_TRANSIENT . '_' . $env;
		delete_transient($transient_id);
	}

	public function _get( $path, array $params = [], $autologin = true) {
		if (!$this->connection) {
			return null;
		}

		if (count($params)) {
			$endpoint = $path . '?' . http_build_query($params);
		} else {
			$endpoint = $path;
		}

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> url:get: {$this->BASE_URL}{$endpoint}");

		if (!$this->access_token) {
			throw new \Exception('Unknown Acces Token');
		}

		$response = $this->connection->get($endpoint, [
		],
		[
			'Authorization' => 'Bearer ' . $this->access_token
		]);

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> http_code: {$response->info->http_code}");

		if (200 == $response->info->http_code) {
			return $response->decode_response();
		} else if (401 == $response->info->http_code && $autologin) {
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> error: do autologin');

			$this->access_token = $this->generate_access_token();

			return $this->_get($path, $params, false);
		}

		return null;
	}

	protected function _post( $path, array $data, $autologin = true) {
		if (!$this->connection) {
			return null;
		}

		$endpoint = $path;

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> url:get: {$this->BASE_URL}{$endpoint}");

		if (!$this->access_token) {
			throw new \Exception('Unknown Acces Token');
		}

		$response = $this->connection->post($endpoint,
			$data,
		[
			'Authorization' => 'Bearer ' . $this->access_token
		]);

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> http_code: {$response->info->http_code}");

		if (200 ==  $response->info->http_code) {
			return $response->decode_response();
		} else if (201 == $response->info->http_code) {
			return $response->decode_response();
		} else if (400 == $response->info->http_code) {
			$response = $response->decode_response();
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> error: ' . json_encode($response['errors']));
			throw new \Exception(json_encode($response['errors']));
		} else if (401 == $response->info->http_code && $autologin) {
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> error: do autologin');

			$this->access_token = $this->generate_access_token();

			return $this->_post($path, $data, false);
		}

		return null;
	}

	protected function _put( $path, array $data = null, $autologin = true) {
		$endpoint = $path;

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> url: {$this->BASE_URL}{$endpoint}");

		if (!$this->access_token) {
			throw new \Exception('Unknown Acces Token');
		}

		$response = $this->connection->put($endpoint,
			$data,
		[
			'Authorization' => 'Bearer ' . $this->access_token
		]);

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> http_code: {$response->info->http_code}");

		if (200 == $response->info->http_code) {
			return $response->decode_response();
		} else if (201 == $response->info->http_code) {
			return $response->decode_response();
		} else if (401 == $response->info->http_code && $autologin) {
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> error: do autologin');

			$this->access_token = $this->generate_access_token();

			return $this->_put($path, $data, false);
		} else {
			$response = $response->decode_response();
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> error: ' . json_encode($response['errors']));
			throw new \Exception(json_encode($response['errors']));
		}

		return null;
	}

	protected function woocommerce_order_to_pulpo_order( array $order) {
		//Buscar al cliente
		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> woocommerce order: ' . json_encode($order));

		$customer_data = null;
		if ($order['customer_id']) {
			$identifier_number = sprintf('woo_%s', $order['customer_id']);

			$q = ['identifier_number' => $identifier_number];

			if($this->mode3pl) {
				$q['merchant_id'] = $this->merchant_id;
			}

			$customer_data = $this->_get('iam/third_parties', $q);
			if (null === $customer_data) {
				throw new \Exception('Error obtaining customer data');
			}
		} else {
			$identifier_number = sprintf('woo_%s_%s', $order['id'], rand(10000, 99999));
		}

		if (!$customer_data || !count($customer_data['third_parties'])) {
			$countries = $this->_get('countries/', ['alpha_2' => $order['shipping']['country']]);
			$country_code = '724';
			if (count($countries['countries'])) {
				$country_code = $countries['countries'][0]['country_code'];
			}

			$data = [
				'name'              =>  "{$order['billing']['first_name']} {$order['billing']['last_name']}",
				'identifier_type'   =>  'ID',
				'email'             =>  $order['billing']['email'],
				'identifier_number' =>  $identifier_number,
				'third_type'        =>  'C',
				'tenant_id'         =>  $this->tenant_id,
				'attributes'    =>  [
					'addresses' =>  [
						[
							'name'  =>  "{$order['shipping']['first_name']} {$order['shipping']['last_name']}",
							'address'   =>  [
								'street'        =>  "{$order['shipping']['address_1']} {$order['shipping']['address_2']}",
								'house_nr'      =>  '1',
								'zip'           =>  $order['shipping']['postcode'],
								'state'         =>  $order['shipping']['state'],
								'country'       =>  $order['shipping']['country'],
								'country_code'  =>  $country_code,
								'city'          =>  $order['shipping']['city'],
								'email'         =>  $order['billing']['email']
							],
							'company_name'  =>  '',
							'phone_number'  =>  isset($order['shipping']['phone'])?$order['shipping']['phone']:$order['billing']['phone']
						]
					]
				]
			];

			if($this->mode3pl) {
				$data['merchant_id'] = $this->merchant_id;
				$data['merchant_channel_id'] = $this->channel_id;
			}

			$response = $this->_post('iam/third_parties', $data);

			if (null === $response) {
				throw new \Exception('Error creating customer data');
			} else {
				$customer_id = $response['id'];
			}
		} else {
			$customer_id = $customer_data['third_parties'][0]['id'];
		}

		$items = [];
		$selected_warehouse_code = null;
		foreach ($order['line_items'] as $order_item) {
			if (null === $order_item['sku'] || '' === trim($order_item['sku'])) {
				$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' Ignore: item SKU empty. Item id: ' . $order_item['id']);
				continue;
			}

			$product_data = $this->_get('inventory/products', ['sku' => $order_item['sku']]);
			if (count($product_data['products'])) {
				$product_id = $product_data['products'][0]['id'];
			} else {
				throw new \Exception("Error product with sku '{$order_item['sku']}' not found");
			}

			$items[] = [
				'product_id'    => $product_id,
				'quantity'      => $order_item['quantity'],
				'batches'       => []
			];

			if (null === $selected_warehouse_code && isset($order_item['warehouse_code'])) {
				$selected_warehouse_code = $order_item['warehouse_code'];

				$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' Selected warehouse code: ' .
					$selected_warehouse_code);
			}
		}

		$source = strtolower(isset($order['source'])?$order['source']:'woo');
		$env = 'prod'===$this->env?'':$this->env;
		$order_num = sprintf('%s%s_%s', $env, $source, $order['id']);

		$warehouse_id = null;
		$warehouses_list = $this->_get('warehouses/?sort_by=priority');
		if (count($warehouses_list['warehouses'])) {
			if (null !== $selected_warehouse_code) {
				foreach ($warehouses_list['warehouses'] as $warehouse) {
					if (strtolower($warehouse['site']) === strtolower($selected_warehouse_code)) {
						$warehouse_id = $warehouse['id'];
						$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' Use Warehouse id: ' .
							$warehouse_id);
						break;
					}
				}
			} else {
				foreach ($warehouses_list['warehouses'] as $warehouse) {
					if ($warehouse['active']) {
						$warehouse_id = $warehouse['id'];
						$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' Use Active Warehouse id: ' .
							$warehouse_id);
						break;
					}
				}
			}

			if (null === $warehouse_id && count($warehouses_list['warehouses'])) {
				$warehouse_id = $warehouses_list['warehouses'][0]['id'];
				$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' Use First Warehouse id: ' .
					$warehouse_id);
			}
		}

		$pulpo_order = [
			'attributes'    => [
				'api_created'               => true,
				'woocommerce_order_id'      => $order['id'],
				'order_id'                  => $order['id'],
				'source'                    => strtolower(isset($order['source'])?$order['source']:'woocommerce'),
				'entity_type'               => strtolower(isset($order['entity_type'])?$order['entity_type']:'order'),
			],
			'order_num'                 => $order_num,
			'warehouse_id'              => $warehouse_id,
			'type'                      => 'sales_order',
			'destination_warehouse_id'  => '',
			'shipping_method_id'        => $this->carrier_id,
			'third_party_id'            => $customer_id,
			'ship_to'   => [
				'address'   => [
					'city'      => $order['shipping']['city'],
					'country'   => $order['shipping']['country'],
					'email'     => $order['billing']['email'],
					'house_nr'  => '',
					'state'     => $order['shipping']['state'],
					'street'    => "{$order['shipping']['address_1']} {$order['shipping']['address_2']}",
					'zip'       => $order['shipping']['postcode']
				],
				'company_name'  => $order['shipping']['company'],
				'name'          => "{$order['shipping']['first_name']} {$order['shipping']['last_name']}",
				'phone_number'  => isset($order['shipping']['phone'])?$order['shipping']['phone']:$order['billing']['phone']
			],
			'service_point_id'  => '',
			'priority'          => 2,
			'notes'             => ( isset($order['customer_note'])?$order['customer_note']:'' ) . ( isset($order['notes'])?$order['notes']:'' ),
			'delivery_date'     => gmdate('Y-m-d H:i:s'),
			'items'             => $items
		];

		if($this->mode3pl) {
			$pulpo_order['merchant_id'] = $this->merchant_id;
			$pulpo_order['merchant_channel_id'] = $this->channel_id;
		}

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> pulpo order: ' . json_encode($pulpo_order));

		return $pulpo_order;
	}

	public function post_order_from_woocommerce_order( array $order, $autologin = true) {
		$endpoint = 'sales/orders';
		$this->log('info', __CLASS__ . '::' . __FUNCTION__);

		try {
			$pulpo_order = $this->woocommerce_order_to_pulpo_order($order);
		} catch (\Exception $e) {
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> woocommerce_order_to_pulpo_order -> Exception: ' . $e->getMessage());
			throw $e;
		}

		if (!$this->access_token) {
			throw new \Exception('Unknown Acces Token');
		}

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> url:get: {$this->BASE_URL}{$endpoint}");
		$response = $this->connection->post($endpoint,
			$pulpo_order,
		[
			'Authorization' => 'Bearer ' . $this->access_token
		]);

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> http_code: {$response->info->http_code}");

		if (200 == $response->info->http_code) {
			return $response->decode_response();
		} else if (201 == $response->info->http_code) {
			$response_dec = $response->decode_response();
			$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " -> success: {$response_dec['id']}");
			return $response_dec;
		} else if (401 == $response->info->http_code && $autologin) {
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> error: do autologin');

			$this->access_token = $this->generate_access_token();

			return $this->post_order_from_woocommerce_order($order, false);
		} else {
			$response_dec = $response->decode_response();
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> error: ' . json_encode($response_dec));
			throw new \Exception(json_encode($response_dec) . ' HTTP: ' . $response->info->http_code);
		}

		return null;
	}

	public function set_carrier( $carrier_id) {
		$this->carrier_id = $carrier_id;
	}

	public function get_product_stock_by_sku( $sku) {
		$MIN_COUNTABLE = 2;

		// Obtener producto
		$product_id = null;
		$product_data = $this->_get('inventory/products', ['sku' => $sku]);
		if (count($product_data['products'])) {
			$product_id = $product_data['products'][0]['id'];
		} else {
			throw new \Exception("Error product with sku '{$sku}' not found");
		}

		// Obtener almacenes
		$warehouses = $this->_get('warehouses');
		if (!count($warehouses['warehouses'])) {
			throw new \Exception('Error obtaining warehouses');
		}

		$warehouses_list = [];
		$quantity = 0;
		foreach ($warehouses['warehouses'] as $warehouse) {
			$data = $this->_get('inventory/stocks/products/locations',
				[
					'product_id'    => $product_id,
					'warehouse_id'  => $warehouse['id']
				]);

			if ($data && isset($data['stocks']) && count($data['stocks'])) {
				foreach ($data['stocks'] as $stock) {
					$quantity += ( (int) $stock['quantity'] ) > $MIN_COUNTABLE ? (int) $stock['quantity']:0;
					$warehouses_list[] = [
						'id'        =>  $warehouse['id'],
						'name'      =>  $warehouse['name'],
						'stock'     =>  (int) $stock['quantity']
					];
				}
			} else {
				$warehouses_list[] = [
					'id'        =>  $warehouse['id'],
					'name'      =>  $warehouse['name'],
					'stock'     =>  0
				];
			}
		}

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> success: ' . json_encode($data));

		return [
			'warehouses'    => $warehouses_list,
			'total'         => $quantity
		];
	}

	public function get_product_by_sku( $sku) {
		$product_data = $this->_get('inventory/products', ['sku' => $sku]);
		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> product_data: ' . json_encode($product_data));
		if (count($product_data['products'])) {
			return $product_data['products'][0];
		} else {
			return null;
		}
	}

	public function get_product_by_id( $id) {
		$product_data = $this->_get('inventory/products', ['id' => $id]);
		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> product_data: ' . json_encode($product_data));
		if (isset($product_data['products']) && count($product_data['products'])) {
			return $product_data['products'][0];
		} else {
			return null;
		}
	}

	public function get_products() {
		$products_list = [];
		$offset = 0;
		while (true) {
			$params = ['offset' => $offset, 'limit' => 50];
			if($this->merchant_id) {
				$params['merchant_id'] = $this->merchant_id;
			}

			$product_data = $this->_get('inventory/products', $params);
			$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> product_data: ' . json_encode($product_data));
			if (null === $product_data) {
				return null;
			}

			if (count($product_data['products'])) {
				$products_list = array_merge($products_list, $product_data['products']);
			}

			if (!isset($product_data['products']) || count($product_data['products']) < 50) {
				break;
			}

			$offset += 50;
		}

		return $products_list;
	}

	public function post_products( array $data, $method = 'post') {
		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> woocommerce products: ' . json_encode($data));

		$data_to_post = [];

		foreach ($data as $row) {
			if (null === $row['sku'] || '' === $row['sku']) {
				$this->log('warning', __CLASS__ . '::' . __FUNCTION__ . " -> product id: {$row['id']} no sku: {$row['sku']}");
				continue;
			}

			$width = '' === $row['width']?1:$row['width'];
			$height = '' === $row['height']?1:$row['height'];
			$length = '' === $row['length']?1:$row['length'];
			$volume = $width * $height * $length;
			$weight = '' === $row['weight']?1:$row['weight'];

			$data = [
				'units_per_sales_package'       =>  '1',
				'stackable'                     =>  true,
				'width'                         =>  $width,
				'length'                        =>  $length,
				'active'                        =>  true,
				'height'                        =>  $height,
				'minimum_sales_unit'            =>  '1',
				'name'                          =>  $row['name'],
				'third_party_id'                =>  null,
				'product_categories'            =>  [],
				'third_party'                   =>  [],
				'purchase_measure_units'        =>  'piece',
				'minimum_purchase_unit'         =>  '1',
				'barcodes'                      =>  $row['barcodes'],
				'management_type'               =>  isset($row['management_type'])?$row['management_type']:'none',
				'attributes'                    =>  isset($row['attributes'])?( (object) $row['attributes'] ):( (object) [] ),
				'cost_price'                    =>  $row['price'],
				'batch_control'                 =>  false,
				'weight'                        =>  $weight,
				'units_per_purchase_package'    =>  '1',
				'volume'                        =>  $volume,
				'id'                            =>  $row['pulpo_id'],
				'sales_measure_units'           =>  'piece',
				'description'                   =>  '',
				'sku'                           =>  $row['sku'],
				'supplier_product_id'           =>  ''
			];

			if($this->mode3pl) {
				$data['merchant_id'] = $this->merchant_id;
				$data['merchant_channel_id'] = $this->channel_id;
			}

			$data_to_post[] = $data;
		}

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . " ({$method}) -> data_to_post: " . json_encode($data_to_post));

		if (count($data_to_post)) {
			try {
				if ('post' === $method) {
					$response = $this->_post('inventory/bulk/products', $data_to_post);
				} else if ('put' === $method) {
					$response = $this->_put('inventory/bulk/products', $data_to_post);
				} else {
					throw new \Exception("Unknown method {$method}");
				}

				$this->log('response', __CLASS__ . '::' . __FUNCTION__ . ' -> response: ' . json_encode($response));

				return $response;
			} catch (\Exception $e) {
				$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> exception: ' . $e->getMessage());
				throw new \Exception("Error creating product with sku: {$row['sku']} into Pulpo");
			}
		}

		return null;
	}

	public function get_warehouses() {
		$data = $this->_get('warehouses');

		if (count($data['warehouses'])) {
			return $data['warehouses'];
		} else {
			return null;
		}
	}


	public function get_users() {
		$data = $this->_get('iam/users');

		if ($data && count($data['users'])) {
			return $data['users'];
		} else {
			return null;
		}
	}

	public function get_shipping_methods() {
		$q = [];

		if($this->mode3pl) {
			$q['merchant_id'] = $this->merchant_id;
			$q['merchant_channel_id'] = $this->channel_id;
		}

		$data = $this->_get('shipping/shipping_methods', $q);

		if ($data && count($data['shipping_methods'])) {
			return $data['shipping_methods'];
		} else {
			return null;
		}
	}

	public function get_merchants() {
		$data = $this->_get('merchants');

		if ($data && count($data['merchants'])) {
			return $data['merchants'];
		} else {
			return null;
		}
	}

	public function get_channels($merchant_id) {
		$data = $this->_get('merchants/' . $merchant_id . '/channels');

		if ($data && count($data['channels'])) {
			return $data['channels'];
		} else {
			return null;
		}
	}

	public function get_webhooks() {
		$q = [];

		if($this->mode3pl) {
			$q['merchant_id'] = $this->merchant_id;
			$q['merchant_channel_id'] = $this->channel_id;
		}

		$data = $this->_get('webhook', $q);

		if ($data && count($data['webhooks'])) {
			return $data['webhooks'];
		} else {
			return null;
		}
	}


	public function substract_stock( $warehouse_id, array $stocks) {
		$this->log('info', __CLASS__ . '::' . __FUNCTION__ .
			" warehouse_id: {$warehouse_id}, stocks: " . json_encode($stocks));

		foreach ($stocks as $stock) {
			$product_data = $this->_get('inventory/products', ['sku' => $stock['sku']]);
			if (count($product_data['products'])) {
				$product_id = $product_data['products'][0]['id'];
			} else {
				throw new \Exception("Error product with sku '{$stock['sku']}' not found");
			}

			$locations = $this->_get('inventory/stocks/products/locations',
				[
					'product_id'    => $product_id,
					'warehouse_id'  => $warehouse_id
				]);

			$this->log('info', __CLASS__ . '::' . __FUNCTION__ .
				' pulpo data: ' . json_encode($locations));

			$qty = $stock['qty'];
			$next_qty = null;
			if ($locations && isset($locations['stocks']) && count($locations['stocks'])) {
				foreach ($locations['stocks'] as $location) {
					if ($location['quantity'] < $qty) {
						$next_qty = $qty - $location['quantity'];
						$qty = $location['quantity'];
					}

					$data_to_post = [
						'quantity'      => $qty,
						'product_id'    => $product_id,
						'location_id'   => $location['location_id']
					];

					$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> post_remove: ' . json_encode($data_to_post));
					$response = $this->_post('inventory/stocks/remove', $data_to_post);

					$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> response: ' . json_encode($response));

					if (null === $next_qty) {
						break;
					} else {
						$qty = $next_qty;
					}
				}
			}

			return true;
		}
	}

	public function add_stock( $warehouse_id, array $stocks) {
		$this->log('info', __CLASS__ . '::' . __FUNCTION__ .
			" warehouse_id: {$warehouse_id}, stocks: " . json_encode($stocks));

		foreach ($stocks as $stock) {
			$product_data = $this->_get('inventory/products', ['sku' => $stock['sku']]);
			if (count($product_data['products'])) {
				$product_id = $product_data['products'][0]['id'];
			} else {
				throw new \Exception("Error product with sku '{$stock['sku']}' not found");
			}

			$locations = $this->_get('inventory/stocks/products/locations',
				[
					'product_id'    => $product_id,
					'warehouse_id'  => $warehouse_id
				]);

			$this->log('info', __CLASS__ . '::' . __FUNCTION__ .
				' pulpo data: ' . json_encode($locations));

			$qty = $stock['qty'];
			$next_qty = null;
			if ($locations && isset($locations['stocks']) && count($locations['stocks'])) {
				foreach ($locations['stocks'] as $location) {
					if ($location['quantity'] < $qty) {
						$next_qty = $qty - $location['quantity'];
						$qty = $location['quantity'];
					}

					$data_to_post = [
						'quantity'      => $qty,
						'product_id'    => $product_id,
						'location_id'   => $location['location_id']
					];

					$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> post_add: ' . json_encode($data_to_post));
					$response = $this->_post('inventory/stocks/add', $data_to_post);

					$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> response: ' . json_encode($response));

					if (null === $next_qty) {
						break;
					} else {
						$qty = $next_qty;
					}
				}
			}

			return true;
		}
	}

	public function configure_webhooks( $webhook_id, $warehouse_id, $api_url, $allowed_types) {
		$this->log('info', __CLASS__ . '::' . __FUNCTION__ .
			" webhook_id: {$webhook_id}, warehouse_id: {$warehouse_id}, allowed_types: " . json_encode($allowed_types));

		try {
			if (null === $webhook_id) {
				$data = [
					'warehouse_id'  => $warehouse_id,
					'url'           => $api_url,
					'method'        => 'POST',
					'allowed_types' => $allowed_types,
					'enabled'       => true,
				];

				if($this->mode3pl) {
					$data['merchant_id'] = $this->merchant_id;
					$data['merchant_channel_id'] = $this->channel_id;
				}

				$response = $this->_post('webhook', $data);
			} else {
				$response = $this->_put('webhook/' . $webhook_id, [
					'id'            => $webhook_id,
					'warehouse_id'  => $warehouse_id,
					'url'           => $api_url,
					'method'        => 'POST',
					'allowed_types' => $allowed_types,
					'enabled'       => true
				]);
			}
		} catch (\Exception $e) {
			$this->log('error', __CLASS__ . '::' . __FUNCTION__ . ' -> exception: ' . $e->getMessage());

			return null;
		}

		$this->log('info', __CLASS__ . '::' . __FUNCTION__ . ' -> response: ' . json_encode($response));

		if (isset($response['webhooks']) && $response['webhooks']) {
			return $response['webhooks'];
		}
	}
}

