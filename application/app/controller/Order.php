<?php
namespace app\app\controller;

use think\Controller;
use think\Exception;
use think\Validate;
use think\Loader;


header('Content-Type: text/plain; charset=utf-8');

class Order extends Base
{
	/**
	 * 用户获取订单列表
	 */
	public function get_order_center()
	{
		$data = input('post.');
		$rule = [
			'user_id|用户ID' => 'require|number',
			'status|状态'    => 'require|number',
			'page|页码'      => 'require|number'
		];
		$arr[0] = 'no_pay_count';
		$arr[1] = 'has_pay_count';
		$arr[2] = 'no_receive_count';
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$orderInfo['count'] = null;
		$orderInfo['inf'] = null;
		$count['no_pay_count'] = model('Common')->get_count(['status' => 0, 'user_id' => $data['user_id']], 'xu_order');
		$count['has_pay_count'] = model('Common')->get_count(['status' => 1, 'user_id' => $data['user_id']], 'xu_order');
		$count['no_receive_count'] = model('Common')->get_count(['status' => 2, 'user_id' => $data['user_id']], 'xu_order');
		$orderInfo['count'] = $count;
		if ($data['status'] == 3) {

		} else if ($count[$arr[$data['status']]] == 0) {
			self::returnJSON($orderInfo, 1, '成功');
		}
		$result = model('Order')->pageSearch(['user_id' => $data['user_id'], 'status' => $data['status']], $data['page'], 'total,order_name,order_img,order_id,status,delivery,tracking_id', 'order');
		if (!$result) {
			self::returnJSON($orderInfo, 1, '无数据');
		}
		foreach ($result as $k => $v) {
			$result[$k]['order_img'] = PUBLIC_URL . 'images/drug_img/' . $v['order_img'];
		}
		$orderInfo['inf'] = $result;
		self::returnJSON($orderInfo, 1, '成功');
	}

	/**
	 * 获取订单详情
	 */
	public function get_order_details()
	{
		$data = input('post.');
		$rule = [
			'order_id|订单ID' => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$orderList = model('Common')->search_one(['order_id' => $data['order_id']], 'details', 'order');
		if (!$orderList) {
			self::returnJSON(NULL, 3, '无此单');
		}
		$infor = json_decode($orderList['details'], true);
		$goodsList = model('Order')->get_order_details($infor);
		$return['data'] = $goodsList;
		$freight = model('Common')->search_one(['key' => 'freight'], 'values', 'system');
		if(!$freight){
			self::returnJSON(NULL, 3, '失败');
		}
		$return['freight'] = $freight['values'];
		self::returnJSON($return, 1, '成功');

	}

	/**
	 * 取消订单
	 */
	public function delete_order()
	{

		$data = input('post.');
		$rule = [
			'order_id|订单ID' => 'require|number',
			'user_id|用户ID'  => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$list = model('Common')->search_one(['order_id' => $data['order_id']], 'user_id,status', 'order');
		//判断此单是否为此账户下的
		if ($list['user_id'] != $data['user_id']) {
			self::returnJSON(NULL, 3, '此单不是你下的');
		}
		//判断是否付款
		if ($list['status'] != 0) {
			self::returnJSON(NULL, 3, '付款后不能删除');
		}
		try {
			$result = model('Common')->del(['order_id' => $data['order_id']], 'order');
			if (!$result) {
				self::returnJSON(NULL, 3, '取消失败');
			}
			self::returnJSON(NULL, 1, '成功');
		} catch (Exception $e) {
			self::returnJSON(NULL, 0, $e);
		}


	}


}