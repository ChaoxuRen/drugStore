<?php
namespace app\app\controller;

use think\Controller;
use think\Exception;
use think\Request;
use think\Validate;
use think\Loader;
use think\Db;


header('Content-Type: text/plain; charset=utf-8');

class Medic extends Base
{
	/**
	 * 获取药师列表
	 */
	public function medic_list()
	{
		$data = input('post.');
		$rule = [
			'page|页码' => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$medicInformation = model('Common')->pageSearch(['status' => 1], $data['page'], 'name,star,username, head_img, medic_id', 'medic');
		if (!$medicInformation) {
			self::returnJSON(null, 1, '无数据了');
		}
		foreach ($medicInformation as $k => $v) {
			$medicInformation[$k]['head_img'] = PUBLIC_URL . 'images/medic_img/' . $v['head_img'];
		}
		if (!$medicInformation) {
			self::returnJSON(null, 3, '无数据');
		}
		self::returnJSON($medicInformation, 1, '成功');
	}

	/**
	 * 请求药师聊天
	 */
	public function chat()
	{

		$data = input('post.');
		$rule = [
			'user_id|用户ID'  => 'require|number',
			'medic_id|药师ID' => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$time = model('Medic')->query_medic_is_expire($data);
		if (!$time) {
			self::returnJSON(NULL, 3, '未下单');
		}

		if ($time['expire_time'] < time() || $time['status'] == 2) {
			self::returnJSON(NULL, 3, '你的订单已过期');
		}
		self::returnJSON($time, 1, '成功');

	}

	/**
	 * 评星
	 */
	public function evaluateStar()
	{
		$data = input('post.');
		$rule = [
			'user_id|用户ID'  => 'require|number',
			'medic_id|药师ID' => 'require|number',
			'star|星级'       => 'require|number|max:1'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$order = model('Medic')->query_medic_is_expire($data);
		if (!$order) {
			self::returnJSON(NULL, 3, '无可评价单');
		}
		if ($order['expire_time'] < time() || $order['status'] == 2) {
			self::returnJSON(NULL, 3, '你的订单已过期');
		}
		if ($order['star'] != 0) {
			self::returnJSON(NULL, 3, '你已评价过了');
		}
		$result = model('Common')->updates(['medicorder' => $order['medicorder']], ['star' => $data['star'], 'status' => 2], 'medicorder');
		if (!$result) {
			self::returnJSON(NULL, 3, '操作异常，请重试');
		}
		self::returnJSON(NULL, 1, 'SUCCESS');
	}


	/**
	 * 药师微信下单
	 */
	public function medic_wx_pay()
	{

		$data = input('post.');
		$rule = [
			'user_id|用户ID'  => 'require|number',
			'medic_id|药师ID' => 'require|number',
			'body|标题'       => 'require',
			'price|金额'      => 'require'
		];

		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$medicOrderId = null;
		$is_no_pay = model('Common')->search_one(['user_id' => $data['user_id'], 'medic_id' => $data['medic_id'], 'status' => 0], 'medicorder,user_id,medic_id,price', 'medicorder');
		if ($is_no_pay) {
			if ($is_no_pay['user_id'] == $data['user_id'] && $is_no_pay['medic_id'] == $data['medic_id'] && $is_no_pay['price'] == $data['price']) {
				$medicOrderId = $is_no_pay['medicorder'];
			} else {
				$is_ok = model('Common')->updates(['medicorder' => $is_no_pay['medicorder']], ['user_id' => $data['user_id'], 'medic_id' => $data['medic_id'], 'price' => $data['price']], 'medicorder');
				if (!$is_ok) {
					self::returnJSON(NULL, 3, '创建失败');
				}
				$medicOrderId = $is_no_pay['medicorder'];
			}
		} else {
			$medicOrderId = model('Common')->insertGetId(['user_id' => $data['user_id'], 'medic_id' => $data['medic_id'], 'price' => $data['price']], 'medicorder');
			if (!$medicOrderId) {
				self::returnJSON(NULL, 3, '创建失败');
			}

		}
		$wx = new \wxpay\Apppay();
		$ip = Request::instance()->ip();
		$result = $wx->MedicAppPay(['body' => $data['body'], 'order_sn' => 'MEDIC-' . $medicOrderId, 'price' => (float)$data['price'] * 100, 'ip' => $ip], 'APP');
		self::returnJSON($result, 1, '成功');

	}

	public function wxReturn()
	{
		$xmlInfo = $GLOBALS['HTTP_RAW_POST_DATA'];
		$wx = new \wxpay\Apppay();
		$arrayInfo = $wx->xmlToArray($xmlInfo);
//		$arrayInfo = input('post.');
		$order = explode('-', $arrayInfo['out_trade_no']);
		$order_info = model('Common')->search_one(['medicorder' => $order[1]], 'status', 'medicorder');
		//如果已修改状态则直接返回成功，且不往下执行
		if ($order_info['status'] != 0) {
			echo $wx->returnInfo("SUCCESS", "OK");
			die;
		}
		$log = "<br />\r\n\r\n" . '===================' . "\r\n" . date("Y-m-d H:i:s") . "\r\n" . json_encode($arrayInfo);
		@file_put_contents(PUBLIC_PATH . "ali.txt", $log, FILE_APPEND);
		if ($arrayInfo['return_code'] == "SUCCESS") {
			Db::startTrans();
			try {
				$wxSign = $arrayInfo['sign'];
				unset($arrayInfo['sign']);
				$arrayInfo['appid'] = $wx::APPID;
				$arrayInfo['mch_id'] = $wx::MCHID;
				ksort($arrayInfo);//按照字典排序参数数组
				$sign = $wx->sign($arrayInfo);//生成签名
				if ($wx->checkSign($wxSign, $sign)) {
					$data = [
						'status'      => 1,
						'expire_time' => time() + 86400
					];
					$result = model('Common')->updates(['medicorder' => $order[1]], $data, 'medicorder');
					if (!$result) {
						echo $this->returnInfo("FAIL", "修改状态失败");
					}
					// 提交事务
					Db::commit();
					echo $wx->returnInfo("SUCCESS", "OK");
				} else {
					echo $wx->returnInfo("FAIL", "签名失败");
					$this->logecho("签名验证结果失败:" . $sign);//log打印保存
				}
			} catch (\Exception $e) {
				// 回滚事务
				Db::rollback();
				echo $wx->returnInfo("FAIL", "失败");
			}
		} else {
			echo $wx->returnInfo("FAIL", "支付失败");
		}

	}


	/**
	 * 药师支付宝下单
	 */
	public function medic_ali_pay()
	{

		$data = input('post.');
		$rule = [
			'user_id|用户ID'  => 'require|number',
			'medic_id|药师ID' => 'require|number',
			'subject|说明'    => 'require',
			'body|标题'       => 'require',
			'price|金额'      => 'require'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$medicOrderId = null;
		$is_no_pay = model('Common')->search_one(['user_id' => $data['user_id'], 'medic_id' => $data['medic_id'], 'status' => 0], 'medicorder,user_id,medic_id,price', 'medicorder');
		if ($is_no_pay) {
			if ($is_no_pay['user_id'] == $data['user_id'] && $is_no_pay['medic_id'] == $data['medic_id'] && $is_no_pay['price'] == $data['price']) {
				$medicOrderId = $is_no_pay['medicorder'];
			} else {
				$is_ok = model('Common')->updates(['medicorder' => $is_no_pay['medicorder']], ['user_id' => $data['user_id'], 'medic_id' => $data['medic_id'], 'price' => $data['price']], 'medicorder');
				if (!$is_ok) {
					self::returnJSON(NULL, 3, '创建失败');
				}
				$medicOrderId = $is_no_pay['medicorder'];
			}
		} else {
			$medicOrderId = model('Common')->insertGetId(['user_id' => $data['user_id'], 'medic_id' => $data['medic_id'], 'price' => $data['price']], 'medicorder');
			if (!$medicOrderId) {
				self::returnJSON(NULL, 3, '创建失败');
			}
		}
		$alipay = new \alipay\alipay();
		$result = $alipay->medicTradeAppPay($data['body'], $data['subject'], 'MEDIC-' . $medicOrderId, (float)$data['price']);
		self::returnJSON($result, 1, '成功');
	}

	public function aliReturn()
	{

		$request = input('post.');

		$order = explode('-', $request['out_trade_no']);
		$order_info = model('Common')->search_one(['medicorder' => $order[1]], 'status', 'medicorder');
		//如果已修改状态则直接返回成功，且不往下执行
		if ($order_info['status'] != 0) {
			exit('fail');
		}

		//写入文件做日志 调试用
		$log = "<br />\r\n\r\n" . '===================' . "\r\n" . date("Y-m-d H:i:s") . "\r\n" . json_encode($request);
		@file_put_contents(PUBLIC_PATH . "ali.txt", $log, FILE_APPEND);

		//支付成功:TRADE_SUCCESS   交易完成：TRADE_FINISHED
		if ($request['trade_status'] == 'TRADE_SUCCESS' || $request['trade_status'] == 'TRADE_FINISHED') {
			// 启动事务
			Db::startTrans();
			try {
				$data = [
					'status'      => 1,
					'expire_time' => time() + 86400
				];
				$order = explode('-', $request['out_trade_no']);
				$result = model('Common')->updates(['medicorder' => $order[1]], $data, 'medicorder');
				if (!$result) {
					exit('fail');
				}
				// 提交事务
				Db::commit();
				exit('success'); //成功处理后必须输出这个字符串给支付宝
			} catch (\Exception $e) {
				// 回滚事务
				exit('fail');
				Db::rollback();
			}
		} else {
			exit('fail');
		}
	}

	/**
	 * 进入药师聊天界面发送欢迎语句
	 */
	public function sendMsg()
	{

		$data = input('post.');
		$rule = [
			'user_phone|用户帐号'  => 'require|number',
			'medic_phone|药师帐号' => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		//环信注册用户
		$Hx = new \Huanxin\Hx();
		$Hx_result = $Hx->hx_send($data['medic_phone'], $data['user_phone'], '亲爱的客户，需要咨询什么？');
		$Hx_result = json_decode($Hx_result, true);
		if ($Hx_result['data'][$data['user_phone']] != 'success') {
			self::returnJSON(NULL, 3, '异常');
		}
		self::returnJSON(NULL, 1, '成功');

	}


}