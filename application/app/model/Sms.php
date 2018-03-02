<?php

/**
 *
 * @file   loginModel.php
 */

namespace app\app\model;

use think\Db;
use think\controller;

class Sms extends \think\Model
{


//	注册、找回密码
	public function send_SMS($phone, $num)
	{
		$SMS = new \alidayu\Sms();
		$response = $SMS::sendSms(
			"四川一百医疗器械有限公司", // 短信签名
			"SMS_113455059", // 短信模板编号
			$phone, // 短信接收者
			Array(  // 短信模板中字段的值
				"code" => $num
			)
		);
		return $response;
	}

	//提货码
	public function pick_up_goods($phone, $username, $number, $address)
	{
		$SMS = new \alidayu\Sms();
		$response = $SMS::sendSms(
			"四川一百医疗器械有限公司", // 短信签名
			"SMS_122288496", // 短信模板编号
			$phone, // 短信接收者
			Array(  // 短信模板中字段的值
				"username" => $username,
				"number"   => $number,
				"address"  => $address
			)
		);
		return $response;

	}

}
