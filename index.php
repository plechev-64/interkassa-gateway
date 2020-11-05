<?php

add_action( 'plugins_loaded', 'rcl_interkassa_load_plugin_textdomain', 10 );
function rcl_interkassa_load_plugin_textdomain() {
	global $locale;
	load_textdomain( 'rcl-interkassa', rcl_addon_path( __FILE__ ) . '/languages/rcl-interkassa-' . $locale . '.mo' );
}

add_action( 'rcl_payments_gateway_init', 'rcl_gateway_interkassa_init', 10 );
function rcl_gateway_interkassa_init() {
	rcl_gateway_register( 'interkassa', 'Rcl_Interkassa_Payment' );
}

class Rcl_Interkassa_Payment extends Rcl_Gateway_Core {
	function __construct() {
		parent::__construct( array(
			'request'	 => 'ik_co_id',
			'name'		 => 'Интеркасса',
			'submit'	 => __( 'Оплатить через Интеркасса' ),
			'icon'		 => rcl_addon_url( 'icon.jpg', __FILE__ )
		) );
	}

	function get_options() {

		return array(
			array(
				'type'	 => 'password',
				'slug'	 => 'intersecretkey',
				'title'	 => __( 'Secret Key', 'rcl-interkassa' )
			),
			array(
				'type'	 => 'password',
				'slug'	 => 'intertestkey',
				'title'	 => __( 'Test Key', 'rcl-interkassa' )
			),
			array(
				'type'	 => 'text',
				'slug'	 => 'interidshop',
				'title'	 => __( 'The ID of the store', 'rcl-interkassa' )
			),
			array(
				'type'	 => 'select',
				'slug'	 => 'interkassatest',
				'title'	 => __( 'The status of the account Interkassa', 'rcl-interkassa' ),
				'values' => array(
					__( 'Work', 'rcl-interkassa' ),
					__( 'Test', 'rcl-interkassa' )
				)
			)
		);
	}

	function get_form( $data ) {
		global $rmag_options;

		$shop_id = rcl_get_commerce_option( 'interidshop' );
		$test	 = rcl_get_commerce_option( 'interkassatest' );
		$key	 = rcl_get_commerce_option( 'intersecretkey' );

		$arr['ik_desc'] = $data->description;

		if ( $test == 1 ) {
			$arr['ik_pw_via'] = 'test_interkassa_test_xts';
		}

		$arr['ik_am']				 = $data->pay_summ;
		$arr['ik_co_id']			 = $shop_id;
		$arr['ik_pm_no']			 = $data->pay_id;
		$arr['ik_x_user_id']		 = $data->user_id;
		$arr['ik_x_type']			 = $data->pay_type;
		$arr['ik_x_baggage_data']	 = $data->baggage_data;

		ksort( $arr, SORT_STRING );
		array_push( $arr, $key );
		$signStr = implode( ':', $arr );
		$ik_sign = base64_encode( md5( $signStr, true ) );

		$fields = array(
			'ik_co_id'			 => $shop_id,
			'ik_am'				 => $data->pay_summ,
			'ik_desc'			 => $data->description,
			'ik_pm_no'			 => $data->pay_id,
			'ik_desc'			 => $arr['ik_desc'],
			'ik_x_user_id'		 => $data->user_id,
			'ik_sign'			 => $ik_sign,
			'ik_x_type'			 => $data->pay_type,
			'ik_x_baggage_data'	 => $data->baggage_data,
		);

		if ( $test == 1 ) {
			$fields['ik_pw_via'] = 'test_interkassa_test_xts';
		}

		return parent::construct_form( array(
				'action' => 'https://sci.interkassa.com/',
				'fields' => $fields
			) );
	}

	function result( $data ) {

		foreach ( $_POST as $key => $value ) {
			if ( ! preg_match( '/ik_/', $key ) )
				continue;
			$arr[$key] = $value;
		}

		$ikSign = $arr['ik_sign'];
		unset( $arr['ik_sign'] );

		if ( $arr['ik_pw_via'] == 'test_interkassa_test_xts' ) {
			$secret_key = rcl_get_commerce_option( 'intertestkey' );
		} else {
			$secret_key = rcl_get_commerce_option( 'intersecretkey' );
		}

		ksort( $arr, SORT_STRING );
		array_push( $arr, $secret_key );
		$signStr = implode( ':', $arr );
		$sign	 = base64_encode( md5( $signStr, true ) );

		if ( $sign != $ikSign ) {
			rcl_mail_payment_error( $sign );
			die;
		}

		if ( ! parent::get_payment( $_REQUEST["ik_pm_no"] ) ) {
			parent::insert_payment( array(
				'pay_id'		 => $_REQUEST["ik_pm_no"],
				'pay_summ'		 => $_REQUEST["ik_am"],
				'user_id'		 => $_REQUEST["ik_x_user_id"],
				'pay_type'		 => $_REQUEST["ik_x_type"],
				'baggage_data'	 => $_REQUEST["ik_x_baggage_data"]
			) );
		}

		exit;
	}

	function success( $process ) {

		if ( parent::get_payment( $_REQUEST['ik_pm_no'] ) ) {
			wp_redirect( get_permalink( $process->page_successfully ) );
			exit;
		} else {
			wp_die( 'Платеж не найден в базе данных!' );
		}
	}

}
