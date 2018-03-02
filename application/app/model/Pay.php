<?php

/**
 *
 * @file   loginModel.php
 */

namespace app\app\model;

use think\Db;
use think\Validate;

class Pay extends \think\Model
{

// 获取药店配送员信息
	public function get_deliveryman_by_shop($data)
	{
		$Information = Db::table('xu_order')
			->field('deliveryman_id')
			->alias('s')
			->join('xu_deliveryman d', 's.shop_id = d.shop_id')
			->where(['order_id' => $data])
			->find();
		if ($Information) {
			return $Information;
		}
		return false;
	}

	public function query_order_all($data)
	{
		$Information = Db::table('xu_order')
			->field('delivery,username,shop_address,shop_name,get_captcha,o.shop_id')
			->alias('o')
			->join('xu_shop s', 's.shop_id = o.shop_id')
			->join('xu_users u', 'u.user_id= o.user_id')
			->where(['order_id' => $data])
			->find();
		if ($Information) {
			return $Information;
		}
		return false;
	}

	//更改下单是否配送
	public function is_delivery($data)
	{

		$Information = Db::table('xu_order')->field('delivery')->where(['order_id' => $data['order_sn']])->find();
		if ($data['delivery'] == 1) {
			if ($Information['delivery'] == 1) {
				return true;
			} else {
				$result = Db::table('xu_order')->where(['order_id' => $data['order_sn'], 'status' => 0])->update(['delivery' => 1]);
				if (!$result) {
					return false;
				}
				return true;
			}
		} else if ($data['delivery'] == 2) {
			$ru = [
				'receiver_name|接收人姓名'    => 'require',
				'receiver_phone|接收人电话'   => 'require',
				'receiver_address|接收人地址' => 'require'
			];
			$validate = new Validate($ru);
			$result = $validate->check($data);
			if (!$result) {
				echo 9999;
//				self::returnJSON(NULL, 2, $validate->getError());
			}
			echo 1212;die();

			$where['receiver_name'] = $data['receiver_name'];
			$where['receiver_phone'] = $data['receiver_phone'];
			$where['receiver_address'] = $data['receiver_address'];
			$where['order_id'] = $data['order'];
//			$deliveryman = model('Pay')->get_deliveryman_by_shop($data['order']);
//			$where['deliveryman_id'] = $deliveryman['deliveryman_id'];
//			$track = model('Common')->insertGetId($where, 'tracking');
//			$result = model('Common')->updates(['order_id' => $data['order'], 'status' => 0], ['delivery' => $data['delivery'], 'tracking_id' => $track], 'order');
//			if (!$result) {
//				Db::rollback();
//				self::returnJSON(NULL, 3, '更新失败,请重试');
//			}




		}

//		var_dump($Information);
//		if ($data['delivery'] == 2) {
//			$ru = [
//				'receiver_name|接收人姓名'    => 'require',
//				'receiver_phone|接收人电话'   => 'require',
//				'receiver_address|接收人地址' => 'require'
//			];
//			$validate = new Validate($ru);
//			$result = $validate->check($data);
//			if (!$result) {
//				self::returnJSON(NULL, 2, $validate->getError());
//			}
//
//			$where['receiver_name'] = $data['receiver_name'];
//			$where['receiver_phone'] = $data['receiver_phone'];
//			$where['receiver_address'] = $data['receiver_address'];
//			$where['order_id'] = $data['order'];
//			$deliveryman = model('Pay')->get_deliveryman_by_shop($data['order']);
//			$where['deliveryman_id'] = $deliveryman['deliveryman_id'];
//			$track = model('Common')->insertGetId($where, 'tracking');
//			$result = model('Common')->updates(['order_id' => $data['order'], 'status' => 0], ['delivery' => $data['delivery'], 'tracking_id' => $track], 'order');
//			if (!$result) {
//				Db::rollback();
//				self::returnJSON(NULL, 3, '更新失败,请重试');
//			}
//		} else if ($data['delivery'] == 1) {
//			$result = model('Common')->updates(['order_id' => $data['order'], 'status' => 0], ['delivery' => 1], 'order');
//			if (!$result) {
//				Db::rollback();
//				self::returnJSON(NULL, 3, '更新失败,请重试');
//			}
//		}

	}


}
