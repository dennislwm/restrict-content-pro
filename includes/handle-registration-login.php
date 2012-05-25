<?php

// register a new user
function rcp_add_new_member() {
	
  	if (isset( $_POST["rcp_register_nonce"] ) && wp_verify_nonce($_POST['rcp_register_nonce'], 'rcp-register-nonce')) {
	
		global $rcp_options, $user_ID;
	
		if(!is_user_logged_in()) {
			$user_login		= $_POST["rcp_user_login"];	
			$user_email		= $_POST["rcp_user_email"];
			$user_first 	= $_POST["rcp_user_first"];
			$user_last	 	= $_POST["rcp_user_last"];
			$user_pass		= $_POST["rcp_user_pass"];
			$pass_confirm 	= $_POST["rcp_user_pass_confirm"];
			$need_new_user 	= true;
		} else {
			$user_id = $user_ID;
			$need_new_user = false;
			$userdata = get_userdata($user_id);
			$user_login = $userdata->user_login;
			$user_email = $userdata->user_email;
		}
		$level = '';
		if(isset($_POST["rcp_level"])) {
			$level = $_POST["rcp_level"];
		}
		$code = '';
		if(isset($_POST['rcp_discount'])) {
			$code = $_POST["rcp_discount"];
		}
		
		// extract the subscription id and price from the input
		$subscription_id = rcp_rstrstr($level, ':');
		$price = str_replace(':', '', strstr($level, ':'));
		$expiration = rcp_get_subscription_length($subscription_id);

		/***********************
		* validate the form
		***********************/		
		
		do_action('rcp_before_form_errors', $_POST);
		
		if($need_new_user) {
			if(username_exists($user_login)) {
				// Username already registered
				rcp_errors()->add('username_unavailable', __('Username already taken', 'rcp'));
			}
			if(!validate_username($user_login)) {
				// invalid username
				rcp_errors()->add('username_invalid', __('Invalid username', 'rcp'));
			}
			if($user_login == '') {
				// empty username
				rcp_errors()->add('username_empty', __('Please enter a username', 'rcp'));
			}
			if(!is_email($user_email)) {
				//invalid email
				rcp_errors()->add('email_invalid', __('Invalid email', 'rcp'));
			}
			if(email_exists($user_email)) {
				//Email address already registered
				rcp_errors()->add('email_used', __('Email already registered', 'rcp'));
			}
			if($user_pass == '') {
				// passwords do not match
				rcp_errors()->add('password_empty', __('Please enter a password', 'rcp'));
			}
			if($user_pass != $pass_confirm) {
				// passwords do not match
				rcp_errors()->add('password_mismatch', __('Passwords do not match', 'rcp'));
			}
		}
		if(!isset($level) || $level == '') {
			// no subscription level was chosen
			rcp_errors()->add('no_level', __('Please choose a subscription level', 'rcp'));
		}
		if(strlen(trim($code)) > 0) {
			if(!rcp_validate_discount($code)) {
				// the entered discount code is incorrect
				rcp_errors()->add('invalid_discount', __('The discount you entered is invalid', 'rcp'));
			}
			if(!$need_new_user && rcp_user_has_used_discount($user_id, $code)) {
				rcp_errors()->add('discount_already_used', __('You can only use the discount code once', 'rcp'));
			}
		}
		if(isset($level) && $level != '') {
			if($price == 0 && $expiration->duration > 0 && rcp_has_used_trial($user_id)) {
				// this ensures that users only sign up for a free trial once
				rcp_errors()->add('free_trial_used', __('You may only sign up for a free trial once', 'rcp'));
			}
		}
		if($price == 0 && isset($_POST['rcp_auto_renew'])) {
			// since free subscriptions do not go through PayPal, they cannot be auto renewed
			rcp_errors()->add('invalid_auto_renew', __('Free subscriptions cannot be automatically renewed', 'rcp'));
		}
		
		/* validate recaptcha, if enabled */
		if(isset($rcp_options['enable_recaptcha']) && $rcp_options['enable_recaptcha']) {
			$privatekey = $rcp_options['recaptcha_private_key'];
			$resp = recaptcha_check_answer(
				$privatekey,
				$_SERVER["REMOTE_ADDR"],
				$_POST["recaptcha_challenge_field"],
				$_POST["recaptcha_response_field"]
			);
			if (!$resp->is_valid) {
				// recaptcha is incorrect
				rcp_errors()->add('invalid_recaptcha', __('The words/numbers you entered did not match the reCaptcha', 'rcp'));
			}
		}
		
		do_action('rcp_form_errors', $_POST);
		
		// retrieve all error messages, if any
		$errors = rcp_errors()->get_error_messages();
		
		// only create the user if there are no errors
		if(empty($errors)) {
			
			// deterime the expiration date of the user's subscription
			if($expiration->duration > 0) {
				$expiration_unit = $expiration->duration_unit;
				$expiration_length = $expiration->duration;
				$member_expires = date('Y-m-d', strtotime('+' . $expiration_length . ' ' . $expiration_unit));
			} else {
				$member_expires = 'none';
			}
			
			if($need_new_user) {
				$user_id = wp_insert_user(array(
						'user_login'		=> $user_login,
						'user_pass'	 		=> $user_pass,
						'user_email'		=> $user_email,
						'first_name'		=> $user_first,
						'last_name'			=> $user_last,
						'user_registered'	=> date('Y-m-d H:i:s'),
						'role'				=> 'subscriber'
					)
				);
			}
			if($user_id) {
				
				// the page to send users after a successfull registration
				if(isset($rcp_options['redirect'])) {
					$redirect = get_permalink($rcp_options['redirect']);
				} else {
					$redirect = home_url();
				}
				
				$currency = $rcp_options['currency'];
				$return_url = $redirect;
				
				// get the details of this subscription
				$subscription = rcp_get_subscription_details($_POST['rcp_level']);
				
				// setup a unique key for this subscription
				$subscription_key = urlencode(strtolower(md5(uniqid())));
				update_user_meta($user_id, 'rcp_subscription_key', $subscription_key );
				update_user_meta($user_id, 'rcp_subscription_level', $subscription_id);
				update_user_meta($user_id, 'rcp_status', 'pending' );
				update_user_meta($user_id, 'rcp_expiration', $member_expires);
				
				do_action('rcp_form_processing', $_POST, $user_id);
				
				// process a paid subscription
				if($price > '0') {
					
					if($code != '') {
						
						// get the details of this discount code
						$discount = rcp_get_discount_details_by_code($code);

						// calculate the after-discount price
						$discounted_price = rcp_get_discounted_price($price, $discount->amount, $discount->unit);
						$price = $discounted_price;
						
						// record the usage of this discount code
						$user_discounts = get_user_meta($user_id, 'rcp_user_discounts', true);
						$user_discounts[] = $code;
						update_user_meta($user_id, 'rcp_user_discounts', $user_discounts);
						rcp_increase_code_use($discount->id);
						
						// if the discount is 100%, log the user in and redirect to success page
						if($price == '0') {
							rcp_set_status($user_id, 'active');
							rcp_email_subscription_status($user_id, 'active');
							rcp_login_user_in($user_id, $user_login, $user_pass);
							wp_redirect($redirect); exit;
						}
						
					}
					
					// this is a premium registration
					if(isset($_POST['rcp_auto_renew'])) {
						
						// set the user to recurring
						update_user_meta( $user_id, 'rcp_recurring', 'yes');
						$auto_renew = true;
					
					} else {		
						$auto_renew = false;
					}
					
					$subscription_data = array(
						'price' => $price,
						'length' => $expiration_length,
						'length_unit' => strtolower($expiration_unit),
						'subscription_name' => $subscription->name,
						'key' => $subscription_key,
						'user_id' => $user_id,
						'user_name' => $user_login,
						'user_email' => $user_email,
						'currency' => $currency,
						'auto_renew' => $auto_renew,
						'return_url' => $return_url,
						'new_user' => $need_new_user,
						'post_data' => $_POST
					);
					
					// get the selected payment method/gateway
					if(!isset($_POST['rcp_gateway'])) {
						$gateway = 'paypal';
					} else {
						$gateway = $_POST['rcp_gateway'];
					}
					
					// send all of the subscription data off for processing by the gateway
					rcp_send_to_gateway($gateway, $subscription_data);
				
				// process a free or trial subscription	
				} else {
					
					// This is a free user registration or trial
					
					// if the subscription is a free trial, we need to record it in the user meta
					if($member_expires != 'none') {
						
						// this is so that users can only sign up for one trial
						update_user_meta($user_id, 'rcp_has_trialed', 'yes');
						
						// activate the user's trial subscription
						rcp_set_status($user_id, 'active');
						
						rcp_email_subscription_status($user_id, 'trial');
						
					} else {
						
						// set the user's status to free
						rcp_set_status($user_id, 'free');
						
						rcp_email_subscription_status($user_id, 'free');
					}
					
					// date for trial / paid users, "none" for free users
					update_user_meta($user_id, 'rcp_expiration', $member_expires);
					
					if($need_new_user) {
					
						// send an email to the admin alerting them of the registration
						wp_new_user_notification($user_id);
						// log the new user in
						rcp_login_user_in($user_id, $user_login, $user_pass);

					}
					// send the newly created user to the redirect page after logging them in
					wp_redirect($redirect); exit;
				
				} // end price check
				
			} // end if new user id
			
		} // end if no errors
	
	} // end nonce check
}
add_action('init', 'rcp_add_new_member', 100);

// logs the specified user in
function rcp_login_user_in($user_id, $user_login, $user_pass) {
	wp_set_auth_cookie($user_id);
	wp_set_current_user($user_id, $user_login);	
	do_action('wp_login', $user_login);
}

// logs a member in after submitting a form
function rcp_process_login_form() {

	if(isset($_POST['rcp_action']) && $_POST['rcp_action'] == 'login') {
		if(isset($_POST['rcp_login_nonce']) && wp_verify_nonce($_POST['rcp_login_nonce'], 'rcp-login-nonce')) {
			
			// this returns the user ID and other info from the user name
			$user = get_user_by('login', $_POST['rcp_user_login']);
		
			do_action('rcp_before_form_errors', $_POST);
		
			if(!$user) {
				// if the user name doesn't exist
				rcp_errors()->add('empty_username', __('Invalid username'));
			}
			
			if(!isset($_POST['rcp_user_pass']) || $_POST['rcp_user_pass'] == '') {
				// if no password was entered
				rcp_errors()->add('empty_password', __('Please enter a password'));
			}
			
			if($user) {	
				// check the user's login with their password
				if(!wp_check_password($_POST['rcp_user_pass'], $user->user_pass, $user->ID)) {
					// if the password is incorrect for the specified user
					rcp_errors()->add('empty_password', __('Incorrect password'));
				}
			}
			do_action('rcp_login_form_errors', $_POST);
		
			// retrieve all error messages
			$errors = rcp_errors()->get_error_messages();
		
			// only log the user in if there are no errors
			if(empty($errors)) {
			
				$user = get_user_by('login', $_POST['rcp_user_login']);
			
				rcp_login_user_in($user->ID, $_POST['rcp_user_login'], $_POST['rcp_user_pass']);
			
				// redirect the user back to the page they were previously on
				wp_redirect($_POST['rcp_redirect']); exit;
			}
		}
	}
}
add_action('init', 'rcp_process_login_form');

function rcp_reset_password() {
	// reset a users password
	if(isset($_POST['rcp_action']) && $_POST['rcp_action'] == 'reset-password') {
		
		global $user_ID;
		
		if(!is_user_logged_in())
			return;
			
		if(wp_verify_nonce($_POST['rcp_password_nonce'], 'rcp-password-nonce')) {
		
			do_action('rcp_before_password_form_errors', $_POST);
		
			if($_POST['rcp_user_pass'] == '' || $_POST['rcp_user_pass_confirm'] == '') {
				// password(s) field empty
				rcp_errors()->add('password_empty', __('Please enter a password, and confirm it', 'rcp'));
			}
			if($_POST['rcp_user_pass'] != $_POST['rcp_user_pass_confirm']) {
				// passwords do not match
				rcp_errors()->add('password_mismatch', __('Passwords do not match', 'rcp'));
			}
			
			do_action('rcp_password_form_errors', $_POST);
			
			// retrieve all error messages, if any
			$errors = rcp_errors()->get_error_messages();
			
			if(empty($errors)) {
				// change the password here
				$user_data = array(
					'ID' => $user_ID,
					'user_pass' => $_POST['rcp_user_pass']
				);
				wp_update_user($user_data);
				// send password change email here (if WP doesn't)
				wp_redirect(add_query_arg('password-reset', 'true', $_POST['rcp_redirect']));
				exit;
			}
		}
	}	
}
add_action('init', 'rcp_reset_password');


// displays error messages from form submissions
function rcp_show_error_messages() {
	if($codes = rcp_errors()->get_error_codes()) {
		echo '<div class="rcp_message error">';
		    // Loop error codes and display errors
		   foreach($codes as $code){
		        $message = rcp_errors()->get_error_message($code);
		        echo '<p class="rcp_error"><span><strong>' . __('Error', 'rcp') . '</strong>: ' . $message . '</span></p>';
		    }
		echo '</div>';
	}	
}

// used for tracking error messages
function rcp_errors(){
    static $wp_error; // Will hold global variable safely
    return isset($wp_error) ? $wp_error : ($wp_error = new WP_Error(null, null, null));
}