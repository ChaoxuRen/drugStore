<?php

/**
 *
 * @file   loginModel.php
 */

namespace app\app\model;

use think\Db;

class Order extends \think\Model
{

// 查询药店详情
	public function search_shop_info($shop_id)
	{
		$Information = Db::table('xu_goods')
			->field('name,type,img,sort,shop_id,price,goods_id')
			->alias('g')
			->join('xu_drug d', 'g.barcode_id = d.barcode_id')
			->where(['shop_id' => $shop_id])
			->select();
		if ($Information) {
			return $Information;
		}
		return false;
	}

	public function get_order_details($data)
	{
		$result = array();
		foreach ($data as $k => $v) {
			$Information = Db::table('xu_goods')
				->field('price,img,name,shop_name,gg')
				->alias('g')
				->join('xu_drug d', 'g.barcode_id = d.barcode_id')
				->join('xu_shop s', 's.shop_id = g.shop_id')
				->where(['goods_id' => $k])
				->find();
			$Information['img'] =PUBLIC_URL. 'images/drug_img/'.$Information['img'];
			$Information['count'] = $v;
			array_push($result, $Information);
		}
		return $result;
	}

	//	分页查询

	public function pageSearch($where, $page, $some, $table)
	{
		$Information = Db::name($table)->field($some)->where($where)->order('order_id desc')->limit((int)$page * 10, 10)->select();
		if ($Information) {
			return $Information;
		}
		return false;
	}


}
