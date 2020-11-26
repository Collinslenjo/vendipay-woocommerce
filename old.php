<?php

/* 	function callback_webhook(){
            header( "Access-Control-Allow-Origin: *" );
            header( 'Content-Type:Application/json' );
            $inputJSON = file_get_contents('php://input');
            $inputData = json_decode($inputJSON, True);
    	    if(isset($inputData['vendipay_payment_data'])){
                $amount = $inputData['vendipay_payment_data']['amount'];
                $receipt = $inputData['vendipay_payment_data']['receipt'];
                $account = $inputData['vendipay_payment_data']['account'];
                $phone_number = $inputData['vendipay_payment_data']['phone_number'];
                $trx_date = $inputData['vendipay_payment_data']['trx_date'];
                $order_id = $inputData['vendipay_payment_data']['description'];

                global $wpdb;
                $table_name = $wpdb->prefix . "vendipay_lipa_na_mpesa";
                $wpdb->insert($table_name, array(
                    'order_id' => $order_id,
                    'amount' => $amount,
                    'account' => $account,
                    'transaction_timestamp' => $trx_date,
                    'phone_number' => $phone_number,
                    'receipt' => $receipt,
                    'transaction_type' => 'mpesa',
                    'used' => 0,
                    'duplicate' => 0
                ));
                // mark order as complete
                custom_woocommerce_auto_complete_order($order_id);
                $wpdb->update($table_name, array('used' => 1), array('order_id'=>$order_id));
    	    }
    	}
        add_action( 'init', 'callback_webhook');

    	// Mark virtual orders as completed automatically
        add_filter('woocommerce_payment_complete_order_status', 'woocommerce_lipa_na_mpesa_virtual_order_completion', 10, 2);
    	function woocommerce_lipa_na_mpesa_virtual_order_completion($order_status, $order_id) {
            $lipa_na_mpesa_gateway = new WC_Vendipay_Gateway();
            $auto_complete_virtual_orders = $lipa_na_mpesa_gateway->auto_complete_virtual_orders;
            if ($auto_complete_virtual_orders) {
                $order = new WC_Order($order_id);
                if ('processing' == $order_status &&
                    ('on-hold' == $order->status || 'pending' == $order->status || 'failed' == $order->status)) {
                    $virtual_order = null;
                    if ( count( $order->get_items() ) > 0 ) {
                        // loop through each item
                         foreach( $order->get_items() as $item_id => $item) {
                            $product = $item->get_product();
                            if (!$product->is_virtual('yes')) {
                                $virtual_order = false;
                                break;
                            } else {
                                $virtual_order = true;
                            }
                        }

                        $virtual_order = true;
                    }
                    global $wpdb;
                    $table_name = $wpdb->prefix . "vendipay_lipa_na_mpesa";
                    $mpesa_records = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM `$table_name` WHERE `order_id` = %s and `phone_number = %s`",
                            [$order_id, $order->get_billing_phone()])
                    );
                    if ($virtual_order && !empty($mpesa_records)) {
                        return 'completed';
                    }
                }
            }
            return $order_status;
        }

        add_action( 'woocommerce_thankyou', 'custom_woocommerce_auto_complete_order' );
        function custom_woocommerce_auto_complete_order( $order_id ) {
            if ( ! $order_id ) {
                return;
            }
            $order = wc_get_order( $order_id );
            $order->update_status( 'completed' );
        }*/




/**
 * Process the payment and return the result
 *
 * @param int $order_id
 * @return array
 */
/*public function process_payment($order_id) {

	$order = wc_get_order($order_id);
	// Mark as processing (payment won't be taken until delivery)
	$order->update_status('pending', __('Waiting to verify MPESA payment.', 'woocommerce'));
	// Remove cart
	WC()->cart->empty_cart();
	// Save mpesa phone number as note from customer
	$order->add_order_note($this->phone_title . ": " . $_POST['phone_number']);

	// simulate a payment request to the server
	$mpesa_input_phone = trim($_POST['phone_number']);
	// $mpesa_input_phone = $order->get_billing_phone();
	$mpesa_input_phone  = str_replace("+","",$mpesa_input_phone);
	if ($mpesa_input_phone[0] == 0) {
		$mpesa_input_phone  = preg_replace('/^0/',"254",$mpesa_input_phone);
	}

	$payment_process = $this->simulate_stk_push($mpesa_input_phone, $order_id, $order->get_total());
	if ($payment_process['ResponseDesc']=='failed'){
		$order->add_order_note(__("Payment Failed",'woocommerce'));
		throw new Exception( __( 'We were unable to process your payment, please try again.', 'woocommerce' ) );
	}
	// query for the MPESA transaction
	sleep(20);
	global $wpdb;
	$table_name = $wpdb->prefix . "vendipay_lipa_na_mpesa";
	$mpesa_records = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM `$table_name` WHERE `order_id` = %s and `phone_number = %s`",
			[$order_id, $mpesa_input_phone])
	);

	if(!empty($mpesa_records)) {
		$mpesa_record = end($mpesa_records);
		if ((int)$mpesa_record->amount >= $order->get_total()) {
			$note = __("SUCCESS: Payment of KES $mpesa_record->amount from $mpesa_record->phone_number and MPESA reference $mpesa_record->receipt confirmed by VendiPay", 'woocommerce');
			$order->add_order_note($note);
			$order->payment_complete();
		} else {
			// Partly paid
			$note = __("PARTLY PAID: Received $mpesa_record->amount from $mpesa_record->phone_number and MPESA reference $mpesa_record->receipt confirmed by VendiPay", 'woocommerce');
			$order->add_order_note($note);
		}
	} else {
		// Not paid / Payment not received
		$note = __("FAILED: payment not received from MPESA ,please contact the admin with the MPESA-CODE that you have received.", 'woocommerce');
		$order->add_order_note($note);
	}

	// Return thankyou redirect
	return array(
		'result'    => 'success',
		'redirect'  => $this->get_return_url($order)
	);
}

public function simulate_stk_push($phone_number, $description, $amount) {
	# makes a call to vendipay
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt_array($curl, [
		CURLOPT_URL => 'https://vendor.pay.vendipay.com/api/payment/stk_push',
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_USERAGENT => 'Vendipay STK Push Request',
		CURLOPT_POST => 1,
		CURLOPT_POSTFIELDS => json_encode([
			'api_key' => $this->vendipay_api_key,
			'amount' => 10,
			'phone_number' => $phone_number,
			'description' => strval($description)
		])
	]);
	$resp = curl_exec($curl);
	curl_close($curl);

	print_r($resp);
	die();
	return $resp;
}*/
