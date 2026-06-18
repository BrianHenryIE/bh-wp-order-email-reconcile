<?php

namespace BrianHenryIE\WP_Order_Email_Reconcile_Test_Plugin\WooCommerce;

use WC_Payment_Gateway;

class My_Payment_Gateway extends WC_Payment_Gateway {

	public $id = 'my-gateway-id';


}
