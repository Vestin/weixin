<?php

/**
 * @vestin 1-20
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

	public function __construct(array $config = [],$redis){
		$this->config = $config;
		$this->redis = $redis;
	}

	/**
	 * use this method to get access token
	 */
	public function getAccessToken(){
		if($token = $this->redis->get($this->config['access_token_key_name'])){
			return $token;
		}else{
			$response = $this->httpGet('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->config['appid'].'&secret='.$this->config['secret']);

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
		$response = $this->httpGet('https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$token.'&type=jsapi');
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
		return file_get_contents($url);

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


	/**
	 * [getUserAccessToken description]
	 * @Author   Vestin
	 * @DataTime 2016-01-19T11:50:56+0800
	 * @return 
	 * 	"access_token":"ACCESS_TOKEN", 
	 * 	"expires_in":7200, 
	 * 	"refresh_token":"REFRESH_TOKEN", 
	 * 	"openid":"OPENID", 
	 * 	"scope":"SCOPE" 
	 */
	private function getUserAccessToken($code){
		$response = $this->httpGet("https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->config['appid']."&secret=".$this->config['secret']."&code=".$code."&grant_type=authorization_code");
		if( $response && $res = json_decode($res) && !isset($res['errcode'])){
			return $res;
		}else{
			return false;
		}
	}

	/**
	 * [getUserInfo description]
	 * @Author   Vestin
	 * @DataTime 2016-01-19T12:07:43+0800
	 * @param    [type]                   $user_access_token  [description]
	 * @param    [type]                   $openid             [description]
	 * @param    [type]                   $user_refresh_token [description]
	 * @return   [type]                                       [
	 * openid	普通用户的标识，对当前开发者帐号唯一
	 * nickname	普通用户昵称
	 * sex	普通用户性别，1为男性，2为女性
	 * province	普通用户个人资料填写的省份
	 * city	普通用户个人资料填写的城市
	 * country	国家，如中国为CN
	 * headimgurl	用户头像，最后一个数值代表正方形头像大小（有0、46、64、96、132数值可选，0代表640*640正方形头像 * ，用户没有头像时该项为空
	 * privilege	用户特权信息，json数组，如微信沃卡用户为（chinaunicom）
	 * unionid	用户统一标识。针对一个微信开放平台帐号下的应用，同一用户的unionid是唯一的。
	 * ]
	 */
	private function getUserInfo($user_access_token,$openid,$user_refresh_token){
		$response = $this->httpGet("https://api.weixin.qq.com/sns/userinfo?access_token=".$user_access_token."&openid=".$openid);
		if( $response && $res = json_decode($res) && !isset($res['errcode'])){
			return $res;
		}else{
			return $this->getUserInfoByRefresh($openid,$user_refresh_token);
		}
	}

	private function getUserInfoByRefresh($openid,$user_refresh_token){
		$refresh_res = $this->refreshUserAcessToken($refresh_token);
		if($refresh_res === false){
			return false;
		}else{
			$response = $this->httpGet("https://api.weixin.qq.com/sns/userinfo?access_token=".$refresh_res['access_token']."&openid=".$openid);
			if( $response && $res = json_decode($res) && !isset($res['errcode'])){
				return $res;
			}else{
				return false;
			}
		}
	}

	/**
	 * [refreshUserAcessToken description]
	 * @Author   Vestin
	 * @DataTime 2016-01-19T12:13:09+0800
	 * @param    [type]                   $refresh_token [description]
	 * @return   [type]                                  
	 * "access_token":"ACCESS_TOKEN", 
	 *  "expires_in":7200, 
	 *  "refresh_token":"REFRESH_TOKEN", 
	 *  "openid":"OPENID", 
	 *  "scope":"SCOPE" 
	 */
	private function refreshUserAcessToken($refresh_token){
		$response = $this->httpGet("https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=".$this->config['appid']."&grant_type=refresh_token&refresh_token=
".$refresh_token);
		if( $response && $res = json_decode($res) && !isset($res['errcode'])){
			return $res;
		}else{
			return false;
		}
	}
}