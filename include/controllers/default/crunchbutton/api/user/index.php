<?php

class Controller_api_user extends Crunchbutton_Controller_Rest {
	public function init() {
		switch (c::getPagePiece(2)) {
			case 'cookie':
				switch ($this->method()) {
					case 'get':
						echo json_encode(['error' => 'invalid request']);
						break;
					case 'post':
						// store cookies on the server for use with facebook api
						foreach ($this->request()['cookie'] as $key => $value) {

						}
						break;
				}
				break;

			case 'points':
				echo json_encode( Crunchbutton_Credit::exportPoints() );exit;
				break;
				// Verify if the login was already taken
			case 'verify':
				switch ($this->method()) {
					case 'get':
						if( c::getPagePiece(3) != '' ){
							$emailExists = User_Auth::checkEmailExists( c::getPagePiece(3) );
							if( $emailExists ){
								echo json_encode(['error' => 'user exists']);
							} else {
								echo json_encode(['success' => 'user not exists']);
							}
						} else {
							echo json_encode(['error' => 'invalid request']);
						}
						break;
					default:
						echo json_encode(['error' => 'invalid request']);
						break;
				}
				break;
				// Sign in the user
			case 'auth':
				switch ($this->method()) {
					case 'post':
						$params = array();
						$params[ 'email' ] = $this->request()[ 'email' ];
						$params[ 'password' ] = $this->request()[ 'password' ];
						$user = c::auth()->doAuthByLocalUser( $params );
						if( $user ){
							echo c::user()->json(['auth' => true]);
						} else {
							echo json_encode(['error' => 'invalid user']);
						}
						exit;
						break;
					default:
						echo json_encode(['error' => 'invalid request']);
						exit;
						break;
				}

			case 'update':
				$this->_update();
				break;

			case 'remove-payment-method':
				$this->_removePaymentMethod();
				break;

			case 'enter':

				$params = array();
				$params[ 'email' ] = $this->request()['email'];
				$params[ 'password' ] = $this->request()['password'];
				$emailExists = User_Auth::checkEmailExists( $this->request()['email'] );
				// if the email exists do the login
				if( $emailExists ){
					$user = c::auth()->doAuthByLocalUser( $params );
					if( $user ){
						echo c::user()->json(['auth' => true]);
					} else {
						echo json_encode(['error' => 'invalid user']);
					}
				}
				// else create a new user
				elseif (!$this->request()['login_only']) {
					$user = c::user();
					if (!$user->id_user) {
						// we dont have a user, and we need to make one
						$user = new User;
						$user->active = 1;
						if( filter_var( $this->request()['email'], FILTER_VALIDATE_EMAIL ) ){
							$user->email = $this->request()['email'];
						} else {
							$user->phone = $this->request()['email'];
						}
						if ($this->request()['first_name']) {
							$user->name = $this->request()['first_name'].'  ';
						}
						if ($this->request()['last_name']) {
							$user->name .= $this->request()['first_name'];
						}
						if ($this->request()['name']) {
							$user->name = $this->request()['name'];
						}
						if ($this->request()['phone']) {
							$user->phone = $this->request()['phone'];
						}

						$user->saving_from = $user->saving_from.'API user post - ';
						$user->save();
					}
					$user_auth = new User_Auth();
					$user_auth->id_user = $user->id_user;
					$user_auth->type = 'local';
					$user_auth->auth = User_Auth::passwordEncrypt( $params[ 'password' ] );
					$user_auth->email = $params[ 'email' ];
					$user_auth->active = 1;
					$user_auth->save();

					// Reward
					$reward = new Crunchbutton_Reward;
					$points = $reward->makeAccountAfterOrder( $user->id_user );

					if( floatval( $points ) > 0 ){
						$order = $user->lastOrder();
						$reward->saveReward( [  'id_user' => $user->id_user, 'id_order' => $order->id_order, 'points' => $points, 'note' => 'points by creating an account' ] );
					}

					// This line will create a phone user auth just if the user already has an email auth
					if( $user->phone ){
						User_Auth::createPhoneAuth( $user->id_user, $user->phone );
					}

					$user = c::auth()->doAuthByLocalUser( $params );

					echo c::user()->json(['auth' => true]);
				}

				break;

			case 'auths':
				switch ($this->method()) {
					case 'get':
						$user = c::user();
						if( $user->id_user ){
							$auths = User_Auth::byUserExport( $user->id_user );
							echo json_encode( [ 'auths' => $auths ] );
							exit;
						}
						echo json_encode(['error' => 'invalid request']);
						exit;
						break;
				}
				// Create a user
			case 'create':
				switch ($this->method()) {
					case 'post':
						switch ( c::getPagePiece( 3 ) ) {
							case 'local':
								$params = array();
								$params[ 'email' ] = $this->request()[ 'email' ];
								$params[ 'password' ] = $this->request()[ 'password' ];
								$emailExists = User_Auth::checkEmailExists( $params[ 'email' ] );
								if( $emailExists ){
									echo json_encode(['error' => 'user exists']);
									exit;
								}
								$user = c::user();
								if (!$user->id_user) {
									// we dont have a user, and we need to make one
									$user = new User;
									$user->active = 1;
									if( filter_var( $this->request()[ 'email' ], FILTER_VALIDATE_EMAIL ) ){
										$user->email = $this->request()[ 'email' ];
									} else {
										$user->phone = $this->request()[ 'email' ];
									}
									$user->name = '';
									$user->saving_from = $user->saving_from.'API user post - ';
									$user->save();
								}
								$user_auth = new User_Auth();
								$user_auth->id_user = $user->id_user;
								$user_auth->type = 'local';
								$user_auth->auth = User_Auth::passwordEncrypt( $params[ 'password' ] );
								$user_auth->email = $params[ 'email' ];
								$user_auth->active = 1;
								$user_auth->save();

								// This line will create a phone user auth just if the user already has an email auth
								if( $user->phone ){
									User_Auth::createPhoneAuth( $user->id_user, $user->phone );
								}

								// Reward
								$reward = new Crunchbutton_Reward;
								$points = $reward->makeAccountAfterOrder( $user->id_user );
								if( floatval( $points ) > 0 ){
									$order = $user->lastOrder();
									$reward->saveReward( [  'id_user' => $user->id_user, 'id_order' => $order->id_order, 'points' => $points, 'note' => 'points by creating an account' ] );
								}

								$user = c::auth()->doAuthByLocalUser( $params );
								echo c::user()->json(['auth' => true]);
								break;
						}
						break;
					default:
						echo json_encode(['error' => 'invalid request']);
						break;
				}
				break;
				// Reset the user password - create a reset code
			case 'reset':
				switch ( $this->method() ) {
					case 'post':
						$email = $this->request()[ 'email' ];
						$user_auth = User_Auth::checkEmailExists( $email );
						if( !$user_auth ){
							echo json_encode(['error' => 'user is not registered']);
							exit;
						}
						$code = User_Auth::resetCodeGenerator();
						$user_auth->reset_code = $code;
						$user_auth->reset_date = date('Y-m-d H:i:s');
						$user_auth->save();

						// Send the code by email
						if( filter_var( $this->request()[ 'email' ], FILTER_VALIDATE_EMAIL ) ){
							$mail = new User_Auth_Reset_Email( [
								'code' => $code,
								'email' => $email
							] );
							$mail->send();
						}
						// Send the code by sms
						else {
							$phone = $email;

							$url = 'http://' . $_SERVER['HTTP_HOST'] . '/reset/';

							$message = Crunchbutton_Message_Sms::greeting( $user_auth->user()->firstName() );
							$message .= "Your crunchbutton password reset code is '".$code."'.\n\n";
							$message .= "Access ".$url." to reset your password.\n\n";

							$id_user = $user_auth->user()->id_user;

							Crunchbutton_Message_Sms::send([
								'to' => $phone,
								'message' => $message,
								'reason' => Crunchbutton_Message_Sms::REASON_PASSWORD_RESET
							]);

						}

						$userHasFacebookAuth = User_Auth::userHasFacebookAuth( $id_user );

						echo json_encode( [ 'success' => 'code generated', 'userHasFacebookAuth' => $userHasFacebookAuth ] );
						exit;
						break;
				}
				// Validate a reset code
			case 'code-validate':
				switch ( $this->method() ) {
					case 'post':
						$code = $this->request()[ 'code' ];
						$user_auth = User_Auth::validateResetCode( $code );
						if( !$user_auth ){
							echo json_encode(['error' => 'invalid code']);
							exit;
						} else {
							$now = strtotime( 'now' );
							$reset_date = strtotime( $user_auth->reset_date );
							$time = 86400; // 24 hours has 86400 seconds
							// The code is valid for 24 hours
							if( ( $reset_date + $time ) < $now ){
								echo json_encode(['error' => 'expired code']);
								exit;
							} else {
								echo json_encode(['success' => 'valid code']);
								exit;
							}
						}
						break;
				}
				// Change the user password
			case 'change-password':
				switch ( $this->method() ) {
					case 'post':
						$code = $this->request()[ 'code' ];
						// Make sure that the user is not cheating!
						$user_auth = User_Auth::validateResetCode($code );
						if( !$user_auth ){
							echo json_encode(['error' => 'invalid code']);
							exit;
						} else {
							$now = strtotime( 'now' );
							$reset_date = strtotime( $user_auth->reset_date );
							$time = 86400; // 24 hours has 86400 seconds
							// The code is valid for 24 hours
							if( ( $reset_date + $time ) < $now ){
								echo json_encode(['error' => 'expired code']);
								exit;
							} else {
								$password = $this->request()[ 'password' ];
								$password = User_Auth::passwordEncrypt( $password  );
								$user_auth->auth = $password;
								$user_auth->reset_code = NULL;
								$user_auth->reset_date = NULL;
								$user_auth->save();
								echo json_encode(['success' => 'password changed']);
							}
						}
						break;
					default:
						echo json_encode(['error' => 'invalid request']);
						break;
				}
				break;

			case 'facebook':
				if ($_REQUEST['fbrtoken']) {

					// log in from the app
					$fb = c::auth()->facebook(new Crunchbutton_Auth_Facebook($_REQUEST['fbrtoken']));
					$user = c::user();

					$isNew = true;

					if( User_Auth::userHasFacebookAuth( $user->id_user ) ){
						$isNew = false;
					}



					// if we have a user and they do not have an email, save it as the facebook email
					if ($user->id_user && !$user->email && $fb->fbuser()->email) {
						$user->email = $fb->fbuser()->email;
						$user->save();
					}

					// log them in as the facebook user instead of the previous user they were logged in as.
					// @todo: merge account info if this is the case as previous user data could be lost
					if ($fb->fbuser()->id) {
						$fb_user = User::facebook($fb->fbuser()->id);
						if ($user->id_user && $fb_user->id_user && $fb_user->id_user != $user->id_user) {
							c::auth()->setUser($fb_user);
						}
					}

					c::auth()->facebook()->check();
					c::auth()->fbauth();
					echo c::user()->json(['auth' => true,'fb_new' => $isNew]);

					break;
				}
				echo c::user()->json(['auth' => true]);
				break;

				// Return the user's credit
			case 'credit':
				if( c::getPagePiece(3) != '' ){
					$user = c::user();
					if ( $user->id_user ){
						$id_restaurant = c::getPagePiece(3);
						$credit = Crunchbutton_Credit::creditByUserRestaurant( $user->id_user, $id_restaurant );
						echo json_encode( [ 'credit' => $credit ] );
					} else {
						echo json_encode(['error' => 'invalid request']);
					}
				} else {
					$user = c::user();
					if ( $user->id_user ){
						$id_restaurant = c::getPagePiece(3);
						$credit = Crunchbutton_Credit::creditByUserValidToAnyRestaurant( $user->id_user );
						echo json_encode( [ 'credit' => $credit ] );
					} else {
						echo json_encode(['error' => 'invalid request']);
					}
				}
				break;
			default:
				switch ($this->method()) {
					case 'get':
						echo c::user()->json();
						break;
					case 'post':
						// we are going to use this for saving user data
						echo c::user()->json();
						break;
				}
				break;
		}
	}

	public function _removePaymentMethod(){
		$user = c::user();
		if( !$user->id_user ){
			echo json_encode( [ 'error' => 'You must log in!' ] );exit;
		}
		$paymentMethod = $user->payment_type();
		$paymentMethod->active = false;
		$paymentMethod->save();
		echo c::user()->json(['auth' => true]);
	}

	public function _update(){

		$user = c::user();
		if( !$user->id_user ){
			echo json_encode( [ 'error' => 'You must log in!' ] );exit;
		}

		$name = $this->request()[ 'name' ];
		$email = $this->request()[ 'email' ];
		$address = $this->request()[ 'address' ];
		$phone = $this->request()[ 'phone' ];

		$errors = [];

		if (!$name) {
			$errors['name'] = 'Please enter a name.';
		}
		if (!$phone) {
			$errors['phone'] = 'Please enter a phone #.';
		}

		if($phone){
			$phone = Crunchbutton_Phone::clean( $phone );
			if( !$phone ){
				$errors['phone'] = 'Please enter a valid phone #.';
			}
		}

		if (!$address) {
			$errors['address'] = 'Please enter an address.';
		}
		if ($email) {
			$email = filter_var( $email, FILTER_VALIDATE_EMAIL );
			if( !$email ){
				$errors['email'] = 'Please enter a valid email.';
			}
		}

		if( count( $errors ) ){
			echo json_encode( [ 'errors' => $errors ] );exit;
		}

		$phone = Crunchbutton_Phone::byPhone( $phone );

		$changes = [];
		if( $user->phone != $phone->phone ){
			$changes[ 'phone' ] = [ 'old' => $user->phone ];
		}
		if( $user->address != $address ){
			$changes[ 'address' ] = [ 'old' => $user->address ];
		}


		$user->name = $name;
		$user->email = $email;
		$user->phone = $phone->phone;
		$user->address = $address;
		$user->id_phone = $phone->id_phone;
		$user->save();

		$user->notifyCSAboutChanges( $changes );

		echo c::user()->json(['auth' => true]);
	}

}
