<?php
namespace app\app\controller;

use think\Controller;
use think\Db;
use think\Exception;
use think\Loader;
use think\Request;
use think\Validate;


class Pay extends Base
{
	/**
	 * @experience：微信APP统一下单处理
	 * @param: body: 说明  price: 价格 1 == 0.01  order: 订单号  ip: 下单者的IP地址
	 * @return:result
	 **/
	public function app_pay()
	{
		// 启动事务
		Db::startTrans();
		try {
			$track = null;
			$data = input('post.');
			$data['ip'] = Request::instance()->ip();
			$rule = [
				'body|body'     => 'require',
				'order_sn|订单号'  => 'require|max:32',
				'price|金额'      => 'require|max:10',
				'delivery|是否配送' => 'require|number'
			];

			$validate = new Validate($rule);
			$result = $validate->check($data);
			if (!$result) {
				self::returnJSON(NULL, 2, $validate->getError());
			}
			$isOK = self::is_delivery($data);
			if (!$isOK) {
				Db::rollback();
				self::returnJSON(NULL, 3, '异常');
			}
			// 提交事务
			Db::commit();
			$wx = new \wxpay\Apppay();
			$data['price'] = (float)$data['price'] * 100;
			$result = $wx->AppPay($data, 'APP');
			self::returnJSON($result, 1, '成功');

		} catch (Exception $e) {
			// 回滚事务
			Db::rollback();
			self::returnJSON(NULL, 0, '异常，请重试');
		}

	}

	/**
	 * @experience::   微信SCAN PAY 统一下单处理
	 * @param: body: 说明  price: 价格 1 == 0.01  order: 订单号  ip: 下单者的IP地址
	 * @return:result
	 */

//	public function scan_pay()
//	{
//		$data = input('get.');
//		$data['ip'] = request()->ip();
//		// 数据验证
//		$rule = [
//			//管理员登陆字段验证
//			'body|body' => 'require',
//			'order|订单号' => 'require|max:32',
//			'price|金额'  => 'require|max:10'
//		];
//		$validate = new Validate($rule);
//		$result = $validate->check($data);
//		if (!$result) {
//			echo json_encode(['code' => 2, 'msg' => $validate->getError()]);
//			exit;
//		}
//		$wx = new \wxpay\Apppay();
//		$result = $wx->ScanPay($data, 'NATIVE');
////		echo '<img src="'.$result.'" />';
//	}

	/**
	 * 微信回调
	 */

	public function wxReturn()
	{

		$wx = new \wxpay\Apppay();
		$xmlInfo = $GLOBALS['HTTP_RAW_POST_DATA'];
		$arrayInfo = $wx->xmlToArray($xmlInfo);
//		$arrayInfo = input('post.');
		$order_info = model('Common')->search_one(['order_id' => $arrayInfo['out_trade_no']], 'total,delivery,user_id,status,details', 'order');
		//如果查询不到数据报错
		if (!$order_info) {
			echo $wx->returnInfo("FAIL", "数据错误");
		}
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
						'pay_type'    => 'WXPAY',
						'get_captcha' => self::randStr(6, 'NUMBER'),
						'order_time'  => date("Y-m-d H:i:s", time())
					];

					//减少库存
					$ok = self::reduce_stock($order_info);
					if (!$ok) {
						echo $wx->returnInfo("FAIL", "减少库存失败");
					}
					//生成运单
					if ($order_info['delivery'] == 1 || $order_info['delivery'] == 2 || $order_info['delivery'] == 3) {
						$receiver = model('Common')->search_one(['user_id' => $order_info['user_id']], 'receiver_' . $order_info['delivery'], 'receiver');
						$arr = explode("|", $receiver['receiver_' . $order_info['delivery']]);
						$where['receiver_name'] = $arr[0];
						$where['receiver_phone'] = $arr[1];
						$where['receiver_address'] = $arr[2];
						$where['price'] = (float)$arrayInfo['total_fee'] / 100 - (float)$order_info['total'];
						$where['order_id'] = $arrayInfo['out_trade_no'];
						$deliveryman = model('Pay')->get_deliveryman_by_shop($arrayInfo['out_trade_no']);
						$where['deliveryman_id'] = $deliveryman['deliveryman_id'];
						$track = model('Common')->insertGetId($where, 'tracking');
						$data['tracking_id'] = $track;
					}

					//修改状态
					$result = model('Common')->updates(['order_id' => $arrayInfo['out_trade_no']], $data, 'order');
					if (!$result) {
						echo $wx->returnInfo("FAIL", "修改状态失败");
					}

					// 付款成功后需要发送验证码给用户
					$is_send = self::pay_send($arrayInfo['out_trade_no']);
					if ($is_send['code'] != 'OK') {
						echo $wx->returnInfo("FAIL", "短信发送失败");
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
	 * 支付宝APP接口
	 */

	public function ali_app_pay()
	{
		// 启动事务
		Db::startTrans();
		try {
			$track = null;
			$data = input('post.');
			$rule = [
				'body|描述信息'       => 'require',
				'subject|商品标题'    => 'require',
				'order_sn|订单号'    => 'require|number',
				'total_amount|金额' => 'require',
				'delivery|是否配送'   => 'require|number'
			];
			$validate = new Validate($rule);
			$result = $validate->check($data);
			if (!$result) {
				self::returnJSON(NULL, 2, $validate->getError());
			}
			$data['price'] = $data['total_amount'];
			$isOK = self::is_delivery($data);
			if (!$isOK) {
				Db::rollback();
				self::returnJSON(NULL, 3, '异常');
			}
			// 提交事务
			Db::commit();
			$alipay = new \alipay\alipay();
			$result = $alipay->tradeAppPay($data['body'], $data['subject'], $data['order_sn'], (float)$data['total_amount']);
//			$alipay->jsonReturn(1, 1, $result, 1);
			self::returnJSON($result, 1, '成功');
		} catch (Exception $e) {
			Db::rollback();
			self::returnJSON(NULL, 0, '异常，请重试');
		}

	}

	public function aliReturn()
	{

		$request = input('post.');
		//写入文件做日志 调试用

		$order_info = model('Common')->search_one(['order_id' => $request['out_trade_no']], 'total,delivery,user_id,status,details', 'order');

		//如果查询不到数据报错
		if (!$order_info) {
			exit('fail');
		}

		//如果已修改状态则直接返回成功，且不往下执行
		if ($order_info['status'] != 0) {
			exit('success');
		}

		//打印日志
		$log = "<br />\r\n\r\n" . '===================' . "\r\n" . date("Y-m-d H:i:s") . "\r\n" . json_encode($request);
		@file_put_contents(PUBLIC_PATH . "ali.txt", $log, FILE_APPEND);

		//支付成功:TRADE_SUCCESS   交易完成：TRADE_FINISHED
		if ($request['trade_status'] == 'TRADE_SUCCESS' || $request['trade_status'] == 'TRADE_FINISHED') {

			// 启动事务
			Db::startTrans();
			try {
				//减少库存
				$ok = self::reduce_stock($order_info);
				if (!$ok) {
					exit('fail');
				}
				$object = json_decode(($request['fund_bill_list']), true);
				$trade_type = $object[0]['fundChannel'];

				$data = [
					'status'      => 1,
					'pay_type'    => $trade_type,
					'get_captcha' => self::randStr(6, 'NUMBER'),
					'order_time'  => $request['gmt_payment']
				];
				//生成运单
				if ($order_info['delivery'] == 1 || $order_info['delivery'] == 2 || $order_info['delivery'] == 3) {
					$receiver = model('Common')->search_one(['user_id' => $order_info['user_id']], 'receiver_' . $order_info['delivery'], 'receiver');
					$arr = explode("|", $receiver['receiver_' . $order_info['delivery']]);
					$where['receiver_name'] = $arr[0];
					$where['receiver_phone'] = $arr[1];
					$where['receiver_address'] = $arr[2];
					$where['price'] = (float)$request['receipt_amount'] - (float)$order_info['total'];
					$where['order_id'] = $request['out_trade_no'];
					$deliveryman = model('Pay')->get_deliveryman_by_shop($request['out_trade_no']);
					$where['deliveryman_id'] = $deliveryman['deliveryman_id'];
					$track = model('Common')->insertGetId($where, 'tracking');
					$data['tracking_id'] = $track;
				}

				$result = model('Common')->updates(['order_id' => $request['out_trade_no']], $data, 'order');
				if (!$result) {
					Db::rollback();
					exit('fail');
				}

				// 付款成功后需要发送验证码给用户
				$is_send = self::pay_send($request['out_trade_no']);
				if ($is_send['code'] != 'OK') {
					exit('fail');
				}

				Db::commit();
				exit('success'); //成功处理后必须输出这个字符串给支付宝
				// 提交事务
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
	 * 更改下单是否配送
	 */
	public function is_delivery($data)
	{

		$Information = model('Common')->search_one(['order_id' => $data['order_sn']], 'delivery,details,shop_id,total', 'order');

		if ($data['delivery'] == 1 || $data['delivery'] == 2 || $data['delivery'] == 3) {
			$freight = model('Common')->search_one(['key' => 'freight'], 'values', 'system');
			if ((float)$data['price'] != (float)$Information['total'] + (float)$freight['values']) {
				self::returnJSON(NULL, 3, '运费和金额异常');
			}
		}
		$is_stock = self::in_stock($Information, $data);
		if (!$is_stock) {
			self::returnJSON(NULL, 3, '库存不足');
		}
		if ($data['delivery'] == 0) {
			return false;
		}
		if ($Information['delivery'] == $data['delivery']) {
			return true;
		}
		$result = model('Common')->updates(['order_id' => $data['order_sn'], 'status' => 0], ['delivery' => $data['delivery']], 'order');
		if (!$result) {
			return false;
		}
		return true;
	}

	/**
	 * 查看购买商品是否有货
	 */

	public function in_stock($Info)
	{
		$infor = json_decode($Info['details'], true);
		//判断商品数量是否够
		foreach ($infor as $k => $v) {
			$stock = Db::table('xu_goods')->field('amount,up_amount')->where(['goods_id' => $k])->find();
			if ((int)$v > (int)$stock['amount'] + (int)$stock['up_amount']) {
				return false;
				exit;
			}
		}
		return true;
	}

	/**
	 * 购买成功后发送短信
	 */

	private function pay_send($order_id)
	{

		$phone = null;
		$orderModel = model('Pay')->query_order_all($order_id);

		$captcha = $orderModel['get_captcha'];
		$shop_id = $orderModel['shop_id'];
		$shop_address = $orderModel['shop_address'];
		$shop_name = $orderModel['shop_name'];
		if ($orderModel['delivery'] == 1 || $orderModel['delivery'] == 2 || $orderModel['delivery'] == 3) {
			$deliverymanList = Model('Common')->search_one(['shop_id' => $shop_id], 'username', 'deliveryman');
			$phone = $deliverymanList['username'];
		} else if ($orderModel['delivery'] == 4) {
			$phone = $orderModel['username'];
		}
		$is_send = model('Sms')->pick_up_goods($phone, $orderModel['username'], $captcha, $shop_address . $shop_name);
		return $is_send;

	}

	/**
	 *  减少库存
	 */

	private function reduce_stock($data)
	{
		$infor = json_decode($data['details'], true);
		foreach ($infor as $k => $v) {
			$stock = Db::table('xu_goods')->field('amount,up_amount')->where(['goods_id' => $k])->find();
			if ($stock['up_amount'] == 0) {
				$ok = Db::table('xu_goods')->where(['goods_id' => $k])->setDec('amount', $v);
				if (!$ok) {
					return false;
				}
			} else {
				if ((int)$stock['up_amount'] >= (int)$v) {
					$ok = Db::table('xu_goods')->where(['goods_id' => $k])->setDec('up_amount', $v);
					if (!$ok) {
						return false;
					}
				} else {
					$ok1 = Db::table('xu_goods')->where(['goods_id' => $k])->setDec('up_amount', $stock['up_amount']);
					$ok2 = Db::table('xu_goods')->where(['goods_id' => $k])->setDec('amount', (int)$v - (int)$stock['up_amount']);
					if ($ok1 == false || $ok2 == false) {
						return false;
					}
				}
			}
		}
		return true;
	}


}
