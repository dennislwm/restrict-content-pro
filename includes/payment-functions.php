<?php 
/**
 * Check if a payment already exists
 *
 * @access      private
 * @param       $transaction_id string/date The unique ID associated with the transaction
 * @return      bool
*/

function rcp_check_for_existing_payment_by_id( $transaction_id ) {

	global $wpdb, $rcp_payments_db_name;

	if( $wpdb->get_results( $wpdb->prepare("SELECT id FROM " . $rcp_payments_db_name . " WHERE `transaction_id`='%s';", $transaction_id ) ) )
		return true; // this payment already exists

	return false; // this payment doesn't exist
}
