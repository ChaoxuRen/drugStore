<?php
namespace app\app\controller;

use think\Controller;
use think\Exception;
use think\Validate;
use think\Loader;

//use think\Db;

header('Content-Type: text/plain; charset=utf-8');

class Shop extends Base
{
	/**
	 * 获取药店详情
	 * @param shop_id
	 */
	public function shop_info()
	{
		$data = input('post.');
		$rule = [
			'shop_id|药店ID' => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$shopInformation = model('Shop')->search_shop_info($data['shop_id']);
		if (!$shopInformation) {
			self::returnJSON(null, 3, '暂无数据');
		}
		foreach ($shopInformation as $k => $v) {
			$shopInformation[$k]['img'] = PUBLIC_URL.'images/drug_img/'.$v['img'];
		}
		self::returnJSON($shopInformation, 1, '成功');
	}

	/**
	 * 按找药品名称查找
	 */
	public function drug_search()
	{
		$data = input('post.');
		$rule = [
			'drug_name|药品名称' => 'require',
			'coord_x|经度'     => 'require',
			'coord_y|纬度'     => 'require'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$scope = self::GetRange($data['coord_x'], $data['coord_y']);
		$shop = model('Map')->query_coord($scope['minX'], $scope['maxX'], $scope['minY'], $scope['maxY'], $data['drug_name']);
		if(!$shop){
			self::returnJSON(null, 3, '无数据');
		}
		$sort = Array();
		foreach ($shop as $k => $v) {
			$sort[$k] = self::getDistance($data['coord_x'], $data['coord_y'], $v['shop_x'], $v['shop_y']);
		}
		$sort = self::sort_with_keyName($sort, 'asc');
		$index = current(array_keys($sort));
		$shop[$index]['img'] = PUBLIC_URL.'images/drug_img/'.$shop[$index]['img'];
		self::returnJSON($shop[$index], 1, '成功');

	}


	/**
	 * 获取药品详情
	 * @param shop_id
	 */
	public function drug_details()
	{
		$data = input('post.');
		$rule = [
			'shop_grid|格子ID' => 'require|number',
			'shop_id|药店ID'   => 'require|number'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$drugInformation = model('Shop')->drug_details($data['shop_id'], $data['shop_grid']);
		if (!$drugInformation) {
			self::returnJSON(null, 3, '暂无数据');
		}
		$drugInformation['img'] = PUBLIC_URL.'images/drug_img/'.$drugInformation['img'];
		self::returnJSON($drugInformation, 1, '成功');
	}

	/**
	 * 生成订单
	 * @param shop_id
	 */
	public function unified_order()
	{
		$data = input('post.');
		$rule = [
			'user_id|用户ID' => 'require|number',
			'shop_id|药店ID' => 'require|number',
			'total|总价格'    => 'require'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$infor = json_decode($data['details'], true);
		if (!$infor) {
			self::returnJSON(NULL, 2, '你传输的数据不是JSON字符串');
		}
		if (count($infor) == 0) {
			self::returnJSON(NULL, 2, '你未选择什么商品');
		}

		//判断是否为统一药店药品
		foreach ($infor as $k => $v) {
			$is_same = model('Shop')->is_same($k, $data['shop_id']);
			if ($is_same == false) {
				self::returnJSON(NULL, 3, '下单中有商品不是指定药店的商品');
				break;
			}
		}
		//判断价格是否正确
		$n_total = model('Shop')->get_drug_price($infor);
		if ($data['total'] != (string)$n_total['total']) {
			self::returnJSON(NULL, 3, '你提交的商品价格有所变动，请刷新后重试');
		}
		$data['order_img'] = $n_total['img'] ;
		$data['order_name'] = $n_total['name'] ;
		$data['order_time'] = date("Y-m-d H:m:s");
		try {
			$return['order_id'] = Model('Common')->insertGetId($data, 'order');
			if (!$result) {
				self::returnJSON(NULL, 4, '异常，请重试');
			}
			$return['title'] = $data['order_name'];
			self::returnJSON($return, 1, '成功');
		} catch (Exception $e) {
			self::returnJSON(NULL, 0, $e);
		}


	}


}