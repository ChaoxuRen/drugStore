<?php

/**
 *
 * @file   loginModel.php
 */

namespace app\app\model;

use think\Db;

class Shop extends \think\Model
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

// 查询药品详情
	public function drug_details($shop_id, $grid)
	{
		$Information = Db::table('xu_goods')
			->field('name,type,img,sort,shop_id,publish,price,jj,yfyl,blfy,gg,syzz,zysx,zc,zycf,gyzz,bzq,amount,up_amount')
			->alias('g')
			->join('xu_drug d', 'g.barcode_id = d.barcode_id')
			->where(['shop_id' => $shop_id, 'sort' => $grid])
			->find();
		if ($Information) {
			return $Information;
		}
		return false;
	}

//	下单时查询药品是否为指定药店的药品
	public function is_same($k, $shop_id)
	{
		$Information = Db::table('xu_goods')->field('shop_id')->where(['goods_id' => $k])->find();
		if ($Information['shop_id'] != $shop_id) {
			return false;
		}
		return true;

	}

//	查询商品价格
	public function get_drug_price($data)
	{
		$totle = 0;
		$img = '';
		$name = '';
		$return = array();
		foreach ($data as $k => $v) {
			$Information = Db::table('xu_goods')->field('price,img,name')->alias('g')->join('xu_drug d', 'g.barcode_id = d.barcode_id')->where('goods_id', 'in', $k)->find();
			$totle = $totle + (float)$Information['price'] * (int)$v;
			$name = $name . $Information['name'] .'/';
			$img = $Information['img'];
		}
		$return['total'] = $totle;
		$return['img'] = $img;
		$return['name'] = $name;
		return $return;
	}


}
