<?php

/**
 *
 * @file   loginModel.php
 */

namespace app\app\model;

use think\Db;

class Medic extends \think\Model
{

// 查询用户对应药师是否有效
	public function query_medic_is_expire($data)
	{
		$Information = Db::table('xu_medicorder')
			->field('expire_time,status,medicorder,star')
			->where(['user_id' => $data['user_id'], 'medic_id' => $data['medic_id'], 'status' => 1])
			->order('expire_time desc')
			->find();
		if ($Information) {
			return $Information;
		}
		return false;
	}


}
