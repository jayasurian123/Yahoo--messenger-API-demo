<?php

require('YahooCurl.php');
require('oauthUtil.php');

/* define your Yahoo! oauth keys here....*/
define('OAUTH_CONSUMER_KEY', '<oauth consumer key>');
define('OAUTH_CONSUMER_SECRET', '<oauth consumer secret>');
define('OAUTH_APP_ID', '<oauth appID>');

const URL_OAUTH_REQUEST_TOKEN = 'https://api.login.yahoo.com/oauth/v2/get_request_token';
const URL_OAUTH_ACCESS_TOKEN  = 'https://api.login.yahoo.com/oauth/v2/get_token';

class OauthHandler {
    
    public $auth_secret = null;

    function get_request_token() {
        
        $params = array(
            'oauth_nonce' => OAuthUtil::generate_nonce(),
            'oauth_timestamp' => OAuthUtil::generate_timestamp(),
            'oauth_consumer_key' => OAUTH_CONSUMER_KEY,
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_version' => 1.0,
            'xoauth_lang_pref' => 'en-us',
            'oauth_signature' => OAuthUtil::build_signature(OAUTH_CONSUMER_SECRET)
//            'oauth_callback' => <oauth call back url>

        );
        
        $http = YahooCurl::fetch(URL_OAUTH_REQUEST_TOKEN, $params);
        return OAuthUtil::parse_parameters($http['response_body']);
    }
        
    function get_user_authorization($url) {
        header('Location: '.$url);
    
    }
    
    function get_access_token() {
    
        $params = array(
            'oauth_consumer_key' => OAUTH_CONSUMER_KEY,
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_nonce' => OAuthUtil::generate_nonce(),
            'oauth_signature' => OAuthUtil::build_signature(OAUTH_CONSUMER_SECRET, $this->auth_secret ), //to change
            'oauth_timestamp' => OAuthUtil::generate_timestamp(),
            'oauth_version' => 1.0,
            'oauth_verifier' => isset($_REQUEST['oauth_verifier'])?$_REQUEST['oauth_verifier']:null,
            'oauth_token' => isset($_REQUEST['oauth_token'])?$_REQUEST['oauth_token']:null
        );
        
        $http = YahooCurl::fetch(URL_OAUTH_ACCESS_TOKEN, $params);
        return OAuthUtil::parse_parameters($http['response_body']);
    }
}

$authObj = new OauthHandler();
if (! (isset($_REQUEST['oauth_token']) || isset($_REQUEST['oauth_verifier']))) {
    $req_token = $authObj->get_request_token();
    OAuthUtil::setdataCookie('auth_secret', $req_token['oauth_token_secret']);
    $authObj->get_user_authorization($req_token['xoauth_request_auth_url']);

} else {
    $authObj->auth_secret = isset($_COOKIE['auth_secret'])?$_COOKIE['auth_secret']:null;
    $access_token = $authObj->get_access_token();
    $access_token['auth_secret'] = $authObj->auth_secret;
    OAuthUtil::unsetdataCookie('auth_secret');
    
    apc_store('oauth_values', $access_token);
//    print_r(apc_fetch('saikumar_mongo'));
}

?>