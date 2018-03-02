<?php

/**
 *
 * @file   loginModel.php
 */

namespace app\app\model;

use think\Db;

class Common extends \think\Model
{

//	判断是否存在
	public function is_exists($data, $table)
	{
		$Information = Db::name($table)->where($data)->find();
		if ($Information) {
			return $Information;
		}
		return false;
	}

//	添加 => 返回ID
	public function insertGetId($data, $table)
	{
		$Information = Db::name($table)->insertGetId($data);
		if ($Information) {
			return $Information;
		}
		return false;
	}

//	添加
	public function insert($data, $table)
	{
		$Information = Db::name($table)->insert($data);
		if ($Information) {
			return $Information;
		}
		return false;
	}

	//	删除
	public function del($where, $table)
	{
		$Information = Db::name($table)->where($where)->delete();
		if ($Information) {
			return $Information;
		}
		return false;
	}

//	修改
	public function updates($where, $data, $table)
	{
		$Information = Db::name($table)->where($where)->update($data);
		if ($Information) {
			return $Information;
		}
		return false;
	}

//	查询 => 单一查找
	public function search_one($where, $some, $table)
	{
		$Information = Db::name($table)->field($some)->where($where)->find();
		if ($Information) {
			return $Information;
		}
		return false;

	}

//	查询 => 多条查找
	public function search_some($where, $some, $table)
	{
		$Information = Db::name($table)->field($some)->where($where)->select();
		if ($Information) {
			return $Information;
		}
		return false;
	}

//	修改增加/减少
	public function setNum($where, $set, $data, $table)
	{
		$Information = Db::table($table)->where($where)->$set($data);
		if ($Information) {
			return $Information;
		}
		return false;
	}


//	查询总数
	public function get_count($where, $table)
	{
		return Db::table($table)->where($where)->count();
	}

//	分页查询

	public function pageSearch($where, $page, $some, $table)
	{
		$Information = Db::name($table)->field($some)->where($where)->limit((int)$page * 10, 10)->select();
		if ($Information) {
			return $Information;
		}
		return false;
	}


}
