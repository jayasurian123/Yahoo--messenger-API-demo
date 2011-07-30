<?php

require('YahooCurl.php');
require('oauthUtil.php');

const URL_YM_SESSION = 'http://developer.messenger.yahooapis.com/v1/session';
const URL_YM_PRESENCE = 'http://developer.messenger.yahooapis.com/v1/presence';
const URL_YM_CONTACT  = 'http://developer.messenger.yahooapis.com/v1/contacts';
const URL_YM_MESSAGE  = 'http://developer.messenger.yahooapis.com/v1/message/yahoo/{{USER}}';
const URL_YM_NOTIFICATION_LONG = 'http://{{NOTIFICATION_SERVER}}/v1/pushchannel/{{USER}}';

//update ur oAuth keys here 
define('OAUTH_CONSUMER_KEY', '<oauth consumer key>');
define('OAUTH_CONSUMER_SECRET', '<oauth consumer secret>');
define('OAUTH_APP_ID', '<oauth appID>');

class MsgrEngine {

    public $auth,
           $params,
           $header;
    
    function __construct() {
    
        if($this->auth = apc_fetch('oauth_values')) {
        }
        
        $this->params = array(
            'realm' => 'yahooapis.com',
            'oauth_consumer_key' => OAUTH_CONSUMER_KEY,
            'oauth_nonce' => OAuthUtil::generate_nonce(),
            'oauth_signature' => OAuthUtil::build_signature(OAUTH_CONSUMER_SECRET, $this->auth['oauth_token_secret']),
            'oauth_signature_method' => 'PLAINTEXT',
            'oauth_timestamp' => OAuthUtil::generate_timestamp(),
            'oauth_token' => $this->auth['oauth_token'],
            'oauth_version' => '1.0'
        );
        
        $this->header = array('Content-type: application/json; charset=utf-8');
        
    }

    //initial signing in...
    function signon() {
    
        $url = URL_YM_SESSION;
        $url = $url. '?fieldsBuddyList=%2Bgroups';
        $this->params['notifyServerToken'] = 2; 
        $postdata = '{"presenceState" : 0, "presenceMessage" : "i am here ..."}';
        $http = YahooCurl::fetch($url, $this->params, $this->header, 'POST', $postdata);
        
        $obj = OAuthUtil::parse_json($http['response_body']);
        $this->auth['imtoken'] = $obj->notifyServerToken->token;
        $this->auth['sessionId'] = $obj->sessionId;
        $this->auth['notifyServer'] = $obj->notifyServer;
        $this->auth['primaryLoginId'] = $obj->primaryLoginId;
        apc_store('oauth_values', $this->auth);
        
        //print_r($obj);
        return $obj;

    }
    
    function signoff() {
        $this->params['sid'] = $this->auth['sessionId'];
            
        $url = URL_YM_SESSION;
        $http = YahooCurl::fetch($url, $this->params, $this->header, 'DELETE');
    }
    
    //to send message
    public function send_message($user, $message) {

        $this->params['sid'] = $this->auth['sessionId'];
        
        $url = URL_YM_MESSAGE;
        $url = str_replace('{{USER}}', $user, $url);
        
        $postdata = '{"message" : "'. str_replace('"', '\"', $message) . '"}';

        $http = YahooCurl::fetch($url, $this->params, $this->header, 'POST', $postdata);        
        return OAuthUtil::parse_json($http['response_body']);
    }

    //fetch contact list ...
    public function fetch_contact_list() {
        //prepare url
        $url = URL_YM_CONTACT;
        $this->params['sid'] = $this->auth['sessionId'];;
        
        $http = YahooCurl::fetch($url, $this->params, $this->header);
        return OAuthUtil::parse_json($http['response_body']);
    }
    
    //fetch long notification
    public function fetch_long_notification($seq=0) {
        
        $this->params['seq'] = $seq;
        $this->params['format'] = 'json';
        $this->params['sid'] = $this->auth['sessionId'];
        $this->params['imtoken'] = $this->auth['imtoken'];
        $this->params['count'] = 100;
        $this->params['idle'] = 120;
    
        $url = URL_YM_NOTIFICATION_LONG;
		$url = str_replace('{{NOTIFICATION_SERVER}}', $this->auth['notifyServer'], $url);
		$url = str_replace('{{USER}}', $this->auth['primaryLoginId'], $url);

		$this->header[] = 'Connection: keep-alive';
		
		$options = array();
		$options['timeout'] = '160';
		
        $http = YahooCurl::fetch($url, $this->params, $this->header, 'GET', null, $options);
//        print_r($http);
        return OAuthUtil::parse_json($http['response_body']);
		
    }

}

header('Content-type: text/plain');
$msgEngineObj = new MsgrEngine();
$obj = $msgEngineObj->signon();
//print_r(apc_fetch('oauth_values'));
print_r( "Messenger demo \n\n================= \n\n");
flush();
$seq = 0;
while (true) {

	$resp = $msgEngineObj->fetch_long_notification($seq+1);
	$resp = $resp->responses;
    if (isset($resp)) {
        if($resp == false) {
            exit;
        }

        foreach ($resp as $row) {
            foreach ($row as $key=>$val) {
                if ($val->sequence > $seq) {
                    $seq = intval($val->sequence);
                }
                
                switch($key) {
                    case 'message': 
                        echo "Message from \"".$val->sender."\"-->".$val->msg."\n";
                            if ($val->msg == 'quit dude') {
                                $msgEngineObj->signoff();
                                exit;
                            } else if ($val->msg == 'reply') {
                                $msgEngineObj->send_message($val->sender, 'yes dude... i will... :)');
                            } else if ($val->msg == 'news') {

                                $rss = file_get_contents('http://rss.news.yahoo.com/rss/topstories');
												
        						if (preg_match_all('|<title>(.*?)</title>|is', $rss, $m)) {
                                    $out = 'Recent Yahoo News: ';
                                    $out .= $m[1][2];
        						}
                                //$msgEngineObj->send_message($val->sender, $out);
                                echo $out;
                            }
                        break;
                        
				    case 'buddyInfo': //contact list
    					if (!isset($val->contact)) continue;
    					
    					foreach ($val->contact as $item) {
    						print_r( $item);
    					}
    				    break;
                }
//                print_r($val);
            }
        }

	}
    flush();
    usleep(2); //php sucks.. so sleep for 2 sec...
}

?>