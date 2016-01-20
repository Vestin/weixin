# weixin

## usage
### config
example

```
$config = [
	'access_token_key_name'	=>'weixin_access_token',
	'jsapi_ticket_key_name'	=>'weixin_jsapi_ticket',
	'appid'		=>'wx1dcb74f95d60041f',
	'secret'	=>'2ba6350b18c47cfc23e89b3dbcaed7ca',
	'scope'		=>'snsapi_userinfo',
];

```

### sign package

```
    $Weixin = new Weixin($config,$redis);
    $signPackage = $Weixin->getSignPackage();
```

### getUserAccessToken
//client side get the `code`
`$user_access_token = $Weixin->getUserAccessToken($code)`

### getUserInfo
`$Weixin->getUserInfo($user_access_token,$openid,$user_refresh_token)`