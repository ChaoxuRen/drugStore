<?php
namespace app\app\controller;

use think\Exception;
use think\Controller;
use think\Validate;
use think\Loader;

header('Content-Type: text/plain; charset=utf-8');

class Receiver extends Base
{

	/**
	 * 用户收货地址添加/修改
	 */
	public function add_receiver()
	{
		$data = input('post.');
		$rule = [
			'user_id|用户ID'  => 'require|number',
			'sort|序号'       => 'require|number',
			'name|收件人姓名'    => 'require',
			'phone|收件人电话'   => 'require|number|max:11',
			'address|收件人地址' => 'require'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}

		$is_exists = model('Common')->is_exists(['user_id' => $data['user_id']], 'receiver');
		try {
			if (!$is_exists) {
				$is_add = Loader::model('Common')->insert(['user_id' => $data['user_id'], 'receiver_' . $data['sort'] => $data['name'] . '|' . $data['phone'] . '|' . $data['address']], 'receiver');
			} else {
				$is_add = Loader::model('Common')->updates(['user_id' => $data['user_id']], ['receiver_' . $data['sort'] => $data['name'] . '|' . $data['phone'] . '|' . $data['address']], 'receiver');
			}
			if (!$is_add) {
				self::returnJSON(NULL, 4, '服务器内部错误,请重试');
			}
			self::returnJSON(NULL, 1, '成功');

		} catch (Exception $e) {
			self::returnJSON(NULL, 0, '网络异常');
		}

	}

	/**
	 * 客户端获取用户收货地址
	 */
	public function get_receiver()
	{
		$data = input('post.');
		$rule = [
			'user_id|用户ID' => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$receiverInfor = model('Common')->search_one(['user_id' => $data['user_id']], 'receiver_1,receiver_2,receiver_3,status', 'receiver');
		if (!$receiverInfor) {
			self::returnJSON(NULL, 3, '无数据');
		}
		self::returnJSON($receiverInfor, 1, '成功');
	}

	/**
	 * 修改收货默认地址
	 */
	public function up_default_receiver()
	{
		$data = input('post.');
		$rule = [
			'user_id|用户ID' => 'require|number',
			'sort|序号'      => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$receiverInfor = model('Common')->search_one(['user_id' => $data['user_id']], 'status', 'receiver');
		if (!$receiverInfor) {
			self::returnJSON(NULL, 3, '此帐号未添加过收货地址');
		}
		if ($receiverInfor['status'] == $data['sort']) {
			self::returnJSON(NULL, 3, '修改数据与原数据相同');
		}
		try {
			$is_up = Loader::model('Common')->updates(['user_id' => $data['user_id']], ['status' => $data['sort']], 'receiver');
			if (!$is_up) {
				self::returnJSON(NULL, 4, '内部错误,请重试');
			}
			self::returnJSON(NULL, 1, '成功');
		} catch (Exception $e) {
			self::returnJSON(NULL, 0, '网络异常');
		}
	}

	/**
	 * 清除收货地址
	 */
	public function delete_receiver()
	{
		$data = input('post.');
		$rule = [
			'user_id|用户ID' => 'require|number',
			'sort|序号'      => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$receiverInfo = model('Common')->search_one(['user_id' => $data['user_id']], 'status', 'receiver');
		if ($receiverInfo['status'] == 1) {
			$result = Loader::model('Common')->updates(['user_id' => $data['user_id']], ['receiver_' . $data['sort'] => ''], 'receiver');
		} else {
			$result = Loader::model('Common')->updates(['user_id' => $data['user_id']], ['receiver_' . $data['sort'] => '', 'status' => 1], 'receiver');
		}
		if (!$result) {
			self::returnJSON(NULL, 3, '失败');
		}
		self::returnJSON(NULL, 1, '成功');


	}


}