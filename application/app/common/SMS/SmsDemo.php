<?php

namespace appsms;

use alidayu\Sms;


class SmsDemo
{
	/**
	 * 验证码 发送
	 *  注册、找回密码
	 */

	public function reg_sms()
	{
		// 调用示例：
		set_time_limit(0);
		header('Content-Type: text/plain; charset=utf-8');

		$sms = new \alidayu\Sms();
		$response = $sms->sendSms(
			"四川一百医疗器械有限公司", // 短信签名
			"SMS_113455059", // 短信模板编号
			"13404012973", // 短信接收者
			Array(  // 短信模板中字段的值
				"code" => "654654"
			)
		);
		echo "发送短信(sendSms)接口返回的结果:\n";
		print_r($response);

	}


}

?>