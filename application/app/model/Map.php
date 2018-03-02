<?php

/**
 *
 * @file   loginModel.php
 */

namespace app\app\model;

use think\Db;

class Map extends \think\Model
{

// 查找区域里坐标
	public function search_coords($minX, $maxX, $maxY, $minY)
	{
		$Information['shop'] = Db::table('xu_shop')
			->field('shop_id,shop_x,shop_y,shop_name,shop_address')
			->where('shop_x', ['>', $minX], ['<', $maxX])->where('shop_y', ['>', $minY], ['<', $maxY])
			->select();
		if (!$Information['shop']) {
			return false;
		}
		$where = array();
		foreach ($Information['shop'] as $k => $v) {
			array_push($where, $v['shop_id']);
		}
		$Information['drug'] = Db::table('xu_goods')
			->distinct(true)//去重
			->field('name')
			->alias('g')
			->join('xu_drug d', 'g.barcode_id = d.barcode_id')
			->where('shop_id', 'in', $where)
			->select();
		return $Information;
	}

	// 查找范围坐标内有没有该药品名
	public function query_coord($minX, $maxX, $maxY, $minY, $name)
	{
		$Information = Db::table('xu_shop')
			->field('name,s.shop_id,sort,img,price,shop_name,shop_x,shop_y,shop_name,goods_id')
			->alias('s')
			->join('xu_goods g', 's.shop_id = g.shop_id')
			->join('xu_drug d', 'd.barcode_id = g.barcode_id')
			->where('shop_x', ['>', $minX], ['<', $maxX])
			->where('shop_y', ['>', $minY], ['<', $maxY])
			->where(['name' => $name])
			->select();
		if ($Information) {
			return $Information;
		}
		return false;
	}


}
