<?php

/**
 * PHP5 class for interfacing with the Tor network
 * by Josh Sandlin <josh@thenullbyte.org>
 * 
 * Licensed: MIT/X11
 * 
 * NOTE: The proxy host is configurable by the user, 
 * so therefore one i not limited to only the Tor
 * network. The default setting however is to run
 * all data through the Tor/Privoxy network.
 * 
 */
 
 /* 
NOTES on Irongeek's TorOrNot script:
Consider this code to be GPLed, but I'd love for you to email me at Irongeek (at) irongeek.com with any changes you make. 
More information about using php and images can be found at http://us3.php.net/manual/en/ref.image.php
More information on detecting Tor exit nodes with TorDNSEL see https://www.torproject.org/tordnsel/

Adrian Crenshaw
http://www.irongeek.com
*/

class Tor
{
    private $url;
    private $userAgent;
    private $timeout;
    private $proxy;
    private $vector;
    private $payload;
    private $returnData;

	function __construct() {$this->Tor();}
	
    public function Tor()
    {
        $this->url = null;
        $this->userAgent = null;
        $this->timeout = 300;
        $this->proxy = '127.0.0.1:9050';
        $this->vector = null;
        $this->payload = null;
        $this->returnData = null;
    }

    private function setUrl($url)
    {
        $this->url = $url;
    }

    private function setUserAgent()
    {
        //list of browsers
        $agentBrowser = array(
                'Firefox',
                'Safari',
                'Opera',
                'Flock',
                'Internet Explorer',
                'Seamonkey',
                'Konqueror',
                'GoogleBot'
        );
        //list of operating systems
        $agentOS = array(
                'Windows 3.1',
                'Windows 95',
                'Windows 98',
                'Windows 2000',
                'Windows NT',
                'Windows XP',
                'Windows Vista',
                'Redhat Linux',
                'Ubuntu',
                'Fedora',
                'AmigaOS',
                'OS 10.5'
        );
        //randomly generate UserAgent
        $this->userAgent = $agentBrowser[rand(0,7)].'/'.rand(1,8).'.'.rand(0,9).' (' .$agentOS[rand(0,11)].' '.rand(1,7).'.'.rand(0,9).'; en-US;)';
    }

    private function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    public function setProxy($ip, $port)
    {
        $this->proxy = $ip .":". $port;
    }

    private function setVector($vector)
    {
        $this->vector = $vector;
    }

    private function setCurl()
    {
        $action = curl_init();
        curl_setopt($action, CURLOPT_PROXY, $this->proxy);
        curl_setopt($action, CURLOPT_URL, $this->payload);
        curl_setopt($action, CURLOPT_HEADER, 1);
        curl_setopt($action, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($action, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($action, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($action, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($action, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
        $this->returnData = curl_exec($action);
        curl_close($action);
    }
    
    private function setPayload()
    {
        $this->payload = $this->url . $this->vector;
    }

    public function launch($url, $vector, $timeout = null)
    {
        //set parameters
        $this->setUrl($url);
        $this->setVector($vector);
        $this->setUserAgent();
            
        //set payload
        $this->setPayload();
            
        //if a timeout is set in the args, use it
        if(isset($timeout))
        {
            $this->setTimeout($timeout);
        }
            
        //run cURL action against url
        $this->setCurl();
    }

    public function getTorData()
    {
        return array(
                'url' => $this->url,
                'userAgent' => $this->userAgent,
                'timeout' => $this->timeout,
                'proxy' => $this->proxy,
                'payload' => $this->payload,
                'return' => $this->returnData
        );
    }
	
	// should detect if the remote user is using TOR or not
	public function IsTorExitPoint(){
		if (gethostbyname($this->ReverseIPOctets($_SERVER[(isset($_SERVER['HTTP_CF_CONNECTING_IP'])?'HTTP_CF_CONNECTING_IP':'REMOTE_ADDR')]).".".$_SERVER['SERVER_PORT'].".".$this->ReverseIPOctets($_SERVER['SERVER_ADDR']).".ip-port.exitlist.torproject.org")=="127.0.0.2") {
			return true;
		} 
		else {
			return false;
		}
	}
	
	private function ReverseIPOctets($inputip){
		$ipoc = explode(".",$inputip);
		return $ipoc[3].".".$ipoc[2].".".$ipoc[1].".".$ipoc[0];
	}
}
?>