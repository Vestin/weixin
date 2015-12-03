<?php

/**
 * @vestin 12-02
 * Weixin jiekou
 */
namespace Weixin;
use Redis;

class Weixin {

	/**
	 * config array;
	 */
	private $config = [];

	/**
	 * storage for accesstoken;
	 */
	private $redis;

	public function __construct($config = []){
		$this->config = $config;
		$redis = new Redis();
		$redis->connect('localhost','6379');
		$redis->auth('');
		$this->redis = $redis;
	}

	/**
	 * use this method to get access token
	 */
	public function getAccessToken(){
		if($token = $this->redis->get($this->config['access_token_key_name'])){
			return $token;
		}else{
			//$response = $this->httpGet('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->config['appid'].'&secret='.$this->config['secret']);
			$response = file_get_contents('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->config['appid'].'&secret='.$this->config['secret']);

			$res = json_decode($response,true);
			if(isset($res['access_token']) && isset($res['expires_in'])){
				$this->redis->setEx(
					$this->config['access_token_key_name'],
					$res['expires_in'],
					$res['access_token']
					);
				return $res['access_token'];
			}else{
				throw new \Exception("Error Processing Request code:".$res['errcode'].";msg:".$res['errmsg'], 1);
				
				return false;
			}
		}
	}

	public function getJsapiTicket(){
		if($ticket = $this->redis->get($this->config['jsapi_ticket_key_name'])){
			return $ticket;
		}
		$token = $this->getAccessToken();
		//$response = $this->httpGet('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$token.'&type=jsapi');
		$response = file_get_contents('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$token.'&type=jsapi');
		$res = json_decode($response,true);
		if($res['errcode']==0 && $res['errmsg']=='ok'){
			$this->redis->setEx($this->config['jsapi_ticket_key_name'],$res['expires_in'],$res['ticket']);
			return $res['ticket'];
		}else{
			throw new \Exception("Error Processing Request code:".$res['errcode'].";msg:".$res['errmsg'], 1);
			return false;	
		}
	}

	public function getSignPackage() {
	    $jsapiTicket = $this->getJsapiTicket();

	    // 注意 URL 一定要动态获取，不能 hardcode.
	    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
	    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	    $timestamp = time();
	    $nonceStr = $this->createNonceStr();

	    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
	    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

	    $signature = sha1($string);

	    $signPackage = array(
	      "appId"     => $this->config['appid'],
	      "nonceStr"  => $nonceStr,
	      "timestamp" => $timestamp,
	      "url"       => $url,
	      "signature" => $signature,
	      "rawString" => $string
	    );
	    return $signPackage; 
	}

	private function createNonceStr($length = 16) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
		  $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}

	/**
	 * https get
	 */
	private function httpGet($url) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 500);
		// 为保证第三方服务器与微信服务器之间数据传输的安全性，所有微信接口采用https方式调用，必须使用下面2行代码打开ssl安全校验。
		// 如果在部署过程中代码在此处验证失败，请到 http://curl.haxx.se/ca/cacert.pem 下载新的证书判别文件。
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, true);
		curl_setopt($curl, CURLOPT_URL, $url);

		$res = curl_exec($curl);
		curl_close($curl);

		return $res;
	}
}