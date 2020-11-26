<?php

define( 'VG_PATH', dirname( __FILE__ ) );
define( 'VG_VERSION', '1.0.0' );
add_action( 'plugins_loaded', 'init_mpesa_gateway', 0 );

function init_mpesa_gateway() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include( VG_PATH . '/vendipay-gateway-helper.php' );


	class WC_Vendipay_Gateway extends WC_Payment_Gateway {

		/**
		 * Singleton instance
		 *
		 * @var Vendipay_Gatway
		 */
		private static $_instance;

		/**
		 * get_instance
		 *
		 * Returns a new instance of self, if it does not already exist.
		 *
		 * @access public
		 * @static
		 * @return Vendipay_Gatway
		 */
		public static function get_instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public $testmode;
		public $debug;
		public $phone_title;
		public $enable_for_virtual;
		public $vendipay_api_key;
		public $auto_complete_virtual_orders;

		function __construct() {
			$this->id                           = 'lipa_na_mpesa';
			$this->method_title                 = __( 'Lipa na MPESA', 'vendipay-gateway' );
			$this->method_description           = __( 'Allows payments via Lipa na MPESA.', 'vendipay-gateway' );
			$this->has_fields                   = false;
			$this->testmode                     = $this->get_option( 'testmode' ) === 'yes';
			$this->debug                        = $this->get_option( 'debug' );
			$this->title                        = $this->get_option( 'title' );
			$this->phone_title                  = $this->get_option( 'phone_title' );
			$this->description                  = $this->get_option( 'description' );
			$this->enable_for_virtual           = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';
			$this->auto_complete_virtual_orders = $this->get_option( 'auto_complete_virtual_orders', 'yes' ) === 'yes';
			$this->vendipay_api_key             = $this->get_option( 'vendipay_api_key' );
			$this->icon                         = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/assets/img/vendipay-logo.png';
			$this->supports                     = [
				'products'
			];

			$this->init_form_fields();
			$this->init_settings();

		}

		public function init_hooks() {

			add_action( 'woocommerce_api_' . strtolower( get_class() ), array( $this, 'vendipay_gateway_callback' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			if ( is_admin() ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				) );
			}
		}

		/**
		 * Check for IPN Response
		 **/
		public function vendipay_gateway_callback() {
			$params        = stripslashes_deep( $_GET );
			$message       = '';
			$order         = null;
			$response_code = 400;
			try {
				$is_valid_call = Vendipay_Gateway_Helper::validate_vendipay_gateway_callback_params( $params, $order, $message );
				if ( $is_valid_call ) {
					$message       = $this->process_vendipay_gateway_callback( $order, $params );
					$response_code = 200;

				} else {
					if ( ! empty( $order ) ) {
						$order->update_status( 'failed', $message );
					}
				}
			} catch ( Exception $ex ) {
				$message       = 'Callback failed Reason: ' . $ex->getMessage();
				$response_code = 500;
			}

			die( $message );
		}

		/**
		 * Process the Vendipay Callback
		 *
		 * @param WC_Order $order
		 */
		protected function process_vendipay_gateway_callback( $order, $params ) {
			try {
				$type = '';

				$action = $this->process_standard_payments( $order, $params );
				$type   = "Standard Payment {$action}";
			} catch ( Exception $e ) {
				throw $e;
			}

			return "VendiPay Callback completed - {$type}";
		}

		/**
		 * Process standard payments
		 *
		 * @param WC_Order $order
		 * @param array $params
		 *
		 * @return string
		 */
		protected function process_standard_payments( $order, $params ) {
			$action = '';
			$order->add_order_note( __( 'Vendipay Payment completed.', 'vendipay-gateway' ) );
			$action = 'created';
			$order->payment_complete();

			return $action;
		}

		/**
		 * receipt_page
		 **/
		public function receipt_page( $order_id ) {

			$order = wc_get_order( $order_id );

			$vendi_args = [
				'api_key'     => $this->vendipay_api_key,
				'currency'    => $order->get_currency(),
				'amount'      => $order->get_total(),
				'orderid'     => $this->clean_order_number( $order->get_order_number() ),
				'accepturl'   => Vendipay_Gateway_Helper::get_accept_url( $order ),
				'cancelurl'   => Vendipay_Gateway_Helper::get_decline_url( $order ),
				'callbackurl' => apply_filters( 'vendipay_gateway_callback_url', Vendipay_Gateway_Helper::get_vendipay_gateway_callback_url( $order_id ) ),
				'timeout'     => '60',
			];

	/*		wc_enqueue_js( '
                jQuery(function(){
                            jQuery("#submit_vendipay_form").click();
                        });
            ' );

			$vendi_args_array = [];

			foreach ( $vendi_args as $key => $value ) {
				$vendi_args_array[] = '<input type="hidden" name="' . $key . '" value="' . $value . '" /><br>';
			}

			echo '<form action="https://vendor.pay.vendipay.com/api/payment_request/" method="post">
                    ' . implode( '', $vendi_args_array ) . '
                    <input type="submit" class="button-alt" id="submit_vendipay_form" value="' . __( 'Pay via VendiPay', 'vendipay-gateway' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order', 'vendipay-gateway' ) . '</a>
                </form>';*/

			//$vendi_args_json = wp_json_encode( $vendi_args );
			$payment_html = Vendipay_Gateway_Helper::paymentHtml( $vendi_args );

			echo ent2ncr( $payment_html );

		}

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 *
		 * @return string[]
		 */
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			return [
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			];
		}

		/**
		 * Removes any special charactors from the order number
		 *
		 * @param string $order_number
		 *
		 * @return string
		 */
		protected function clean_order_number( $order_number ) {
			return preg_replace( '/[^a-z\d ]/i', "", $order_number );
		}

		public function thankyou_page() {
			if ( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		}

		public function email_instructions( $order, $sent_to_admin ) {
			if ( $this->description && ! $sent_to_admin && 'lipa_na_mpesa' === $order->payment_method ) {
				echo wpautop( wptexturize( $this->description ) ) . PHP_EOL;
			}
		}

		public function get_icon() {
			$icon_html = '<img src="' . $this->icon . '" alt="' . $this->method_title . '" width="50"  />';
			return apply_filters( 'woocommerce_gateway_icon', $icon_html );
		}

		public function init_form_fields() {
			/**
			 * Initialise Gateway Settings Form Fields
			 */
			$shipping_methods = array();

			if ( is_admin() ) {
				foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
					$shipping_methods[ $method->id ] = $method->get_title();
				}
			}

			$mpesa_instructions = '
                    <div class="mpesa-instructions">
                      <p>
                        <h3>' . __( 'Payment Instructions', 'vendipay-gateway' ) . '</h3>
                        <p>
                          ' . __( 'Enter your Mpesa phone number below we will make a payment request to your phone', 'vendipay-gateway' ) . '
                          ' . __( 'Click on Place Order', 'vendipay-gateway' ) . ' </br>
                          ' . __( 'You will be prompted for your MPESA PIN on your phone.', 'vendipay-gateway' ) . '
                        </p>
                      </p>
                    </div>
                ';

			$this->form_fields = array(
				'enabled'                      => array(
					'title'   => __( 'Enable/Disable', 'vendipay-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Lipa na MPESA', 'vendipay-gateway' ),
					'default' => 'no'
				),
				'title'                        => array(
					'title'       => __( 'Title', 'vendipay-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'vendipay-gateway' ),
					'default'     => __( 'Lipa na MPESA', 'vendipay-gateway' ),
					'desc_tip'    => true,
				),
				'description'                  => array(
					'title'       => __( 'Description', 'vendipay-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'vendipay-gateway' ),
					'default'     => $mpesa_instructions,
					'desc_tip'    => true,
				),
				'phone_title'                  => array(
					'title'       => __( 'Phone Number Field Title', 'vendipay-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the MPESA phone number field title which the user sees during checkout.', 'vendipay-gateway' ),
					'default'     => __( "MPESA Phone Number", 'woothemes' ),
					'desc_tip'    => true,
				),
				'enable_for_virtual'           => array(
					'title'   => __( 'Accept for virtual orders', 'vendipay-gateway' ),
					'label'   => __( 'Accept Lipa na MPESA if the order is virtual', 'vendipay-gateway' ),
					'type'    => 'checkbox',
					'default' => 'yes'
				),
				'auto_complete_virtual_orders' => array(
					'title'   => __( 'Auto-complete for virtual orders', 'vendipay-gateway' ),
					'label'   => __( 'Automatically mark virtual orders as completed once payment is received', 'vendipay-gateway' ),
					'type'    => 'checkbox',
					'default' => 'no'
				),
				'vendipay_api_key'             => array(
					'title'       => __( 'VendiPay API Key', 'vendipay-gateway' ),
					'type'        => 'text',
					'description' => __( 'The API Key received from VendiPay.', 'vendipay-gateway' ),
					'desc_tip'    => true,
				),
			);

		}
	}

	add_filter( 'woocommerce_payment_gateways', 'add_mpesa_gateway_to_wc' );
	WC_Vendipay_Gateway::get_instance()->init_hooks();

	function add_mpesa_gateway_to_wc( $methods ) {
		$methods[] = 'WC_Vendipay_Gateway';

		return $methods;
	}

}
