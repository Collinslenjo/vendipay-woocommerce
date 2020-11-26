<?php

class Vendipay_Gateway_Helper {

	/**
	 * Returns the module header
	 *
	 * @return string
	 */
	public static function get_module_header_info() {
		global $woocommerce;

		$epay_version        = VG_VERSION;
		$woocommerce_version = $woocommerce->version;
		$php_version         = phpversion();
		$result              = "WooCommerce/{$woocommerce_version} Module/{$epay_version} PHP/{$php_version}";

		return $result;
	}

	/**
	 * Returns the Callback url
	 *
	 * @param WC_Order $order
	 */
	public static function get_vendipay_gateway_callback_url( $order_id ) {
		$args = array( 'wc-api' => 'WC_Vendipay_Gateway', 'wcorderid' => $order_id );

		return add_query_arg( $args, site_url( '/' ) );
	}

	/**
	 * Returns the Accept url
	 *
	 * @param WC_Order $order
	 */
	public static function get_accept_url( $order ) {
		if ( method_exists( $order, 'get_checkout_order_received_url' ) ) {
			$acceptUrlRaw  = $order->get_checkout_order_received_url();
			$acceptUrlTemp = str_replace( '&amp;', '&', $acceptUrlRaw );
			$acceptUrl     = str_replace( '&#038', '&', $acceptUrlTemp );

			return $acceptUrl;
		}

		return add_query_arg( 'key', $order->order_key, add_query_arg(
				'order', $order->get_id(),
				get_permalink( get_option( 'woocommerce_thanks_page_id' ) )
			)
		);
	}

	/**
	 * Returns the Decline url
	 *
	 * @param WC_Order $order
	 */
	public static function get_decline_url( $order ) {
		if ( method_exists( $order, 'get_cancel_order_url' ) ) {
			$declineUrlRaw  = $order->get_cancel_order_url();
			$declineUrlTemp = str_replace( '&amp;', '&', $declineUrlRaw );
			$declineUrl     = str_replace( '&#038', '&', $declineUrlTemp );

			return $declineUrl;
		}

		return add_query_arg( 'key', $order->get_order_key(), add_query_arg(
				array(
					'order'                => $order->get_id(),
					'payment_cancellation' => 'yes',
				),
				get_permalink( get_option( 'woocommerce_cart_page_id' ) ) )
		);
	}

	/**
	 * Validate Callback
	 *
	 * @param mixed $params
	 * @param string $md5_key
	 * @param WC_Order $order
	 * @param string $message
	 *
	 * @return bool
	 */
	public static function validate_vendipay_gateway_callback_params( $params, &$order, &$message ) {
		// Check for empty params
		if ( ! isset( $params ) || empty( $params ) ) {
			$message = "No GET parameteres supplied to the system";

			return false;
		}

		// Validate woocommerce order!
		if ( empty( $params['wcorderid'] ) ) {
			$message = "No WooCommerce Order Id was supplied to the system!";

			return false;
		}

		$order = wc_get_order( $params['wcorderid'] );
		if ( empty( $order ) ) {
			$message = "Could not find order with WooCommerce Order id {$params["wcorderid"]}";

			return false;
		}

		// Check exists transactionid!
		if ( ! isset( $params['txnid'] ) ) {
			$message = 'No GET(txnid) was supplied to the system!';

			return false;
		}

		return true;
	}

	/**
	 * Remove all special characters
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public static function json_value_remove_special_characters( $value ) {
		return preg_replace( '/[^\p{Latin}\d ]/u', ' ', $value );
	}

	/**
	 * Return the WC_Vendipay_Gateway instance
	 *
	 * @return WC_Vendipay_Gateway
	 */
	public static function VENDI_instance() {
		return WC_Vendipay_Gateway::get_instance();
	}

	/**
	 * Converts bool string to int
	 *
	 * @param string $str
	 *
	 * @return int
	 */
	public static function yes_no_to_int( $str ) {
		return $str === 'yes' ? 1 : 0;
	}

	/**
	 * Format date time
	 *
	 * @param string $raw_date_time
	 *
	 * @return string
	 */
	public static function format_date_time( $raw_date_time ) {
		$date_format      = wc_date_format();
		$time_format      = wc_time_format();
		$date_time_format = "{$date_format} - {$time_format}";
		$formated_date    = "";
		if ( self::is_woocommerce_3_1() ) {
			$date_time     = wc_string_to_datetime( $raw_date_time );
			$formated_date = wc_format_datetime( $date_time, $date_time_format );
		} else {
			$formated_date = date( $date_time_format, strtotime( $raw_date_time ) );
		}

		return $formated_date;
	}

	public static function paymentHtml( $json_data ) {

		$html = '<section>';
		$html .= '<h3>' . __( 'Thank you for using VendiPay.', 'vendipay-gateway' ) . '</h3>';
		$html .= '<p>' . __( 'Please wait...', 'vendipay-gateway' ) . '</p>';
		$html .= '<script type="text/javascript" src="https://code.jquery.com/jquery-1.11.3.min.js" charset="UTF-8"></script>';
		$html .= '<script type="text/javascript" src="https://vendor.pay.vendipay.com/static/js/paybox.js?v.1.0" charset="UTF-8"></script>';
		$html .= '<div id="overlay" style="position: fixed;z-index: 1000;top: 0px;left: 0px;background: #a7a7a7;width: 100%;height: 100%;opacity: .50;filter: alpha(opacity=50);-moz-opacity: .50;"></div>';
		$html .= '<div id="frame_container" style="position: absolute;z-index: 10001;width: 100%;height: 100%;top: 0px;left: 0px;display: flex;justify-content: center;align-items: center;"><div id="paybox"
                style="
    background-color: white;
    height: 400px;
    width: 350px;
    justify-content: center;
    align-items: center;
    display: flex;
    flex-direction: column;" data-api_key="' . $json_data['api_key'] . '"
                data-phone_number="254708457639"
                data-callbackurl="' . $json_data['callbackurl'] . '"
                data-accepturl="' . $json_data['accepturl'] . '"
                data-cancelurl="' . $json_data['cancelurl'] . '"
                data-orderid="' . $json_data['orderid'] . '"
                data-amount="' . $json_data['amount'] . '" >
                            Loading Vendipay Paybox<br>
                <img src="https://vendor.pay.vendipay.com/static/img/76.GIF" />
              </div></div>';
		$html .= '</section>';

		return $html;
	}

	/**
	 * Get language code id based on name
	 *
	 * @param string $locale
	 *
	 * @return string
	 */
	public static function get_language_code( $locale = null ) {
		if ( ! isset( $locale ) ) {
			$locale = get_locale();
		}
		$languageArray = array(
			'da_DK' => '1',
			'en_AU' => '2',
			'en_GB' => '2',
			'en_NZ' => '2',
			'en_US' => '2',
			'sv_SE' => '3',
			'nb_NO' => '4',
			'nn_NO' => '4',
			'is-IS' => '6',
			'de_CH' => '7',
			'de_DE' => '7',
			'fi-FI' => '8',
			'es-ES' => '9',
			'fr-FR' => '10',
			'pl-PL' => '11',
			'it-IT' => '12',
			'nl-NL' => '13'
		);

		return key_exists( $locale, $languageArray ) ? $languageArray[ $locale ] : '2';
	}

	/**
	 * Get the iso code based iso name
	 *
	 * @param string $code
	 * @param boolean $isKey
	 *
	 * @return string
	 */
	public static function get_iso_code( $code, $isKey = true ) {
		$isoCodeArray = array(
			'ADP' => '020',
			'AED' => '784',
			'AFA' => '004',
			'ALL' => '008',
			'AMD' => '051',
			'ANG' => '532',
			'AOA' => '973',
			'ARS' => '032',
			'AUD' => '036',
			'AWG' => '533',
			'AZM' => '031',
			'BAM' => '052',
			'BBD' => '004',
			'BDT' => '050',
			'BGL' => '100',
			'BGN' => '975',
			'BHD' => '048',
			'BIF' => '108',
			'BMD' => '060',
			'BND' => '096',
			'BOB' => '068',
			'BOV' => '984',
			'BRL' => '986',
			'BSD' => '044',
			'BTN' => '064',
			'BWP' => '072',
			'BYR' => '974',
			'BZD' => '084',
			'CAD' => '124',
			'CDF' => '976',
			'CHF' => '756',
			'CLF' => '990',
			'CLP' => '152',
			'CNY' => '156',
			'COP' => '170',
			'CRC' => '188',
			'CUP' => '192',
			'CVE' => '132',
			'CYP' => '196',
			'CZK' => '203',
			'DJF' => '262',
			'DKK' => '208',
			'DOP' => '214',
			'DZD' => '012',
			'ECS' => '218',
			'ECV' => '983',
			'EEK' => '233',
			'EGP' => '818',
			'ERN' => '232',
			'ETB' => '230',
			'EUR' => '978',
			'FJD' => '242',
			'FKP' => '238',
			'GBP' => '826',
			'GEL' => '981',
			'GHC' => '288',
			'GIP' => '292',
			'GMD' => '270',
			'GNF' => '324',
			'GTQ' => '320',
			'GWP' => '624',
			'GYD' => '328',
			'HKD' => '344',
			'HNL' => '340',
			'HRK' => '191',
			'HTG' => '332',
			'HUF' => '348',
			'IDR' => '360',
			'ILS' => '376',
			'INR' => '356',
			'IQD' => '368',
			'IRR' => '364',
			'ISK' => '352',
			'JMD' => '388',
			'JOD' => '400',
			'JPY' => '392',
			'KES' => '404',
			'KGS' => '417',
			'KHR' => '116',
			'KMF' => '174',
			'KPW' => '408',
			'KRW' => '410',
			'KWD' => '414',
			'KYD' => '136',
			'KZT' => '398',
			'LAK' => '418',
			'LBP' => '422',
			'LKR' => '144',
			'LRD' => '430',
			'LSL' => '426',
			'LTL' => '440',
			'LVL' => '428',
			'LYD' => '434',
			'MAD' => '504',
			'MDL' => '498',
			'MGF' => '450',
			'MKD' => '807',
			'MMK' => '104',
			'MNT' => '496',
			'MOP' => '446',
			'MRO' => '478',
			'MTL' => '470',
			'MUR' => '480',
			'MVR' => '462',
			'MWK' => '454',
			'MXN' => '484',
			'MXV' => '979',
			'MYR' => '458',
			'MZM' => '508',
			'NAD' => '516',
			'NGN' => '566',
			'NIO' => '558',
			'NOK' => '578',
			'NPR' => '524',
			'NZD' => '554',
			'OMR' => '512',
			'PAB' => '590',
			'PEN' => '604',
			'PGK' => '598',
			'PHP' => '608',
			'PKR' => '586',
			'PLN' => '985',
			'PYG' => '600',
			'QAR' => '634',
			'ROL' => '642',
			'RUB' => '643',
			'RUR' => '810',
			'RWF' => '646',
			'SAR' => '682',
			'SBD' => '090',
			'SCR' => '690',
			'SDD' => '736',
			'SEK' => '752',
			'SGD' => '702',
			'SHP' => '654',
			'SIT' => '705',
			'SKK' => '703',
			'SLL' => '694',
			'SOS' => '706',
			'SRG' => '740',
			'STD' => '678',
			'SVC' => '222',
			'SYP' => '760',
			'SZL' => '748',
			'THB' => '764',
			'TJS' => '972',
			'TMM' => '795',
			'TND' => '788',
			'TOP' => '776',
			'TPE' => '626',
			'TRL' => '792',
			'TRY' => '949',
			'TTD' => '780',
			'TWD' => '901',
			'TZS' => '834',
			'UAH' => '980',
			'UGX' => '800',
			'USD' => '840',
			'UYU' => '858',
			'UZS' => '860',
			'VEB' => '862',
			'VND' => '704',
			'VUV' => '548',
			'XAF' => '950',
			'XCD' => '951',
			'XOF' => '952',
			'XPF' => '953',
			'YER' => '886',
			'YUM' => '891',
			'ZAR' => '710',
			'ZMK' => '894',
			'ZWD' => '716',
		);

		if ( $isKey ) {
			return $isoCodeArray[ strtoupper( $code ) ];
		}

		return array_search( strtoupper( $code ), $isoCodeArray );
	}

	/**
	 * Get Payment type name based on Card id
	 *
	 * @param int $card_id
	 *
	 * @return string
	 */
	public static function get_card_name_by_id( $card_id ) {
		switch ( $card_id ) {
			case 1:
				return 'Mpesa';
		}

		return 'Unknown';
	}


	/**
	 * Convert message to HTML
	 *
	 * @param string $type
	 * @param string $message
	 *
	 * @return string
	 * */
	public static function message_to_html( $type, $message ) {

		$class = '';
		if ( $type === self::SUCCESS ) {
			$class = "notice-success";
		} else {
			$class = "notice-error";
		}

		$html = '<div id="message" class="' . $class . ' notice"><p><strong>' . ucfirst( $type ) . '! </strong>' . $message . '</p></div>';

		return ent2ncr( $html );
	}

	/**
	 * Get the Card type group id and Name by card type id
	 *
	 * @param int $card_type_id
	 *
	 * @return array
	 */
	public static function get_cardtype_groupid_and_name( $card_type_id ) {
		$card_type_array = array(
			1 => array( 'Mpesa', '1' ),
		);

		if ( $card_type_id == null || ! key_exists( $card_type_id, $card_type_array ) ) {
			return array( 'Unknown', '-1' );
		}

		return $card_type_array[ $card_type_id ];
	}

}
