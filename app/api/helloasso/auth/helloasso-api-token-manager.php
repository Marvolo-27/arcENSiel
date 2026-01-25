<?php
header('Content-Type: application/json');

// load helloasso sdk
require $root . "/libraries/vendor/autoload.php";

// load libraries
use League\OAuth2\Client\Provider\GenericProvider;

// class to wrap token management and make clean calls
class HATokenManager {
	// declare private variables to restrict their access to within the class
	private string $clientID;		// api token
	private string $clientSecret;	// api token password
	private string $APIurl;			// api url to be auth
	private string $file;			// file to save token info
	
	public function __construct($clientID, $clientSecret, $APIurl = 'https://api.helloasso.com/oauth2/token', $file = __DIR__ . '/../../../../.private/token.json') {// constructor : run  at each instance creation to declare core variables
		// are private var, so can't be accessed from outside the instance
		$this->clientID = $clientID;
		$this->clientSecret = $clientSecret;
		$this->APIurl = $APIurl;
		$this->tokenFile = $file;
		
		// generate API provider - cf helloasso github page
		$this->provider = new GenericProvider([
			'clientId'                => $this->clientID,
			'clientSecret'            => $this->clientSecret,
			'urlAccessToken'          => $this->APIurl,
			'urlAuthorize'            => '',
			'urlResourceOwnerDetails' => ''
		]);
	}
	
	private function loadToken() {// load token data if exists - return into array
		$tokenData = json_decode(file_get_contents($this->tokenFile), true);
		
		return $tokenData;
	}
	
	private function saveToken($tokenObject) {// save Token object data to json file
		$tokenInfo = [
            'access-token'  => $tokenObject->getToken(),
            'refresh-token' => $tokenObject->getRefreshToken(), // may change!
            'expires-at'    => $tokenObject->getExpires(),
        ];
		
		file_put_contents($this->tokenFile, json_encode($tokenInfo, JSON_PRETTY_PRINT));
		logger('/log/helloasso.log', "Authentification successful.", 'Authentification');

		return $tokenInfo['access-token'];
	}
	
	private function isExpired($tokenData) {// check token validity
		return $tokenData['expires-at'] < time();
	}
	
	
	public function GetToken() {
		$tokenData = $this->loadToken();
		
		if ($tokenData && !empty($tokenData['access-token'])) {// token saved and not empty
			if (!$this->isExpired($tokenData)) {// valid token
				logger('/log/helloasso.log', "Authentification successful with saved token.", 'Authentification');

				return $tokenData['access-token'];
			
			} else {// refresh token
				try {
					$newToken = $this->provider->getAccessToken('refresh_token', ['refresh_token' => $tokenData['refresh-token']]);
					logger('/log/helloasso.log', "New OAuth token generated with refresh token", 'Token generation');

					return $this->saveToken($newToken);
				} catch (Exception $e) {// invalid / expired resfresh token
					$token = $this->provider->getAccessToken('client_credentials');
					logger('/log/helloasso.log', "Refresh token invalid: wew OAuth token generated with API credentials.", 'Token generation');

					return $this->saveToken($token);
				}
			}
		
		} else {// (re)generate Token
			$token = $this->provider->getAccessToken('client_credentials');
			logger('/log/helloasso.log', "No saved token: wew OAuth token generated with API credentials.", 'Token generation');

			return $this->saveToken($token);
		}
	}
}