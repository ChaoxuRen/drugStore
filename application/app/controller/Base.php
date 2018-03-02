<?php
namespace app\app\controller;

use think\Controller;
use think\Db;
use think\Request;

class Base extends Controller
{
	protected $uid;
	protected $username;

	/**
	 *  每次前置操作
	 */

	public function _initialize()
	{
		$isMobile = Request::instance()->isMobile();
		if (!$isMobile) {
			$action = Request()->action();
			if ($action == 'wxreturn' || $action == 'alireturn') {
				return;
			} else {
				self::returnJSON(null, 0, '非法请求');
				die;
			}
		}
	}

	/**
	 * 随机产生六位数
	 */

	function randStr($len = 6, $format = 'ALL')
	{
		switch ($format) {
			case 'ALL':
				$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
				break;
			case 'CHAR':
				$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-@#~';
				break;
			case 'NUMBER':
				$chars = '0123456789';
				break;
			default :
				$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
				break;
		}
		mt_srand((double)microtime() * 1000000 * getmypid());
		$password = "";
		while (strlen($password) < $len)
			$password .= substr($chars, (mt_rand() % strlen($chars)), 1);
		return $password;
	}


	/**
	 * JSON数据返回
	 */
	protected function returnJSON($code = '', $msg = '', $data)
	{
		$this->result($code, $msg, $data, 'JSON');
		exit();
	}

	/**
	 * 验证是否为手机号码
	 */
	protected function is_phone($phone)
	{
		if (preg_match("/^1[34578]{1}\d{9}$/", $phone)) {
			return 1;
		}
		return 0;
	}


	/** 根据特定经纬度和一定范围获取经纬度范围
	 * @param $lat -- 纬度
	 * @param $lng -- 经度
	 * @param $distince -- 距离范围 单位km
	 */
	function GetRange($lat, $lng, $distince = 2)
	{
		$EARTH_RADIUS = 6378.137;
		$dlng = 2 * asin(sin($distince / (2 * $EARTH_RADIUS)) / cos(deg2rad($lat)));
		$dlng = rad2deg($dlng);
		$dlat = ($distince / $EARTH_RADIUS);
		$dlat = rad2deg($dlat);
		return array(
			'maxX' => $lat + $dlat,
			'minX' => $lat - $dlat,
			'maxY' => $lng + $dlng / 4, //除4 是我自己加的
			'minY' => $lng - $dlng / 4 //除4 是我自己加的
		);
	}


	/**
	 * 求两个已知经纬度之间的距离,单位为米
	 *
	 * @param lng1 $ ,lng2 经度
	 * @param lat1 $ ,lat2 纬度
	 * @return INT 距离，单位米
	 * @author CHAOXUREN
	 */
	function getDistance($lat1, $lng1, $lat2, $lng2)
	{
		$earthRadius = 6367000;
		$lat1 = ($lat1 * pi()) / 180;
		$lng1 = ($lng1 * pi()) / 180;
		$lat2 = ($lat2 * pi()) / 180;
		$lng2 = ($lng2 * pi()) / 180;
		$calcLongitude = $lng2 - $lng1;
		$calcLatitude = $lat2 - $lat1;
		$stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
		$stepTwo = 2 * asin(min(1, sqrt($stepOne)));
		$calculatedDistance = $earthRadius * $stepTwo;
		return round($calculatedDistance);
	}


	/**
	 * PHP 排序  保留KEY不变
	 * @param $arr
	 * @param string $orderby
	 * @return array
	 */

	function sort_with_keyName($arr, $orderby = 'desc')
	{
		//在内存的另一处 $a 复制内容与 $arr 一样的数组
		foreach ($arr as $key => $value)
			$a[$key] = $value;
		if ($orderby == 'asc') {//对数组 $arr 进行排序
			asort($arr);
		} else {
			arsort($arr);
		}
		/*创建一个以原始数组的键名为元素值 (键值) 的
		 *数组 $b, 其元素 (键值) 顺序，与排好序的数组 $arr 一致。
		*/
		$index = 0;
		foreach ($arr as $keys => $values) //按排序后数组的顺序
			foreach ($a as $key => $value) //在备份数组中寻找键值
				if ($values == $value)//如果找到键值
					$b[$index++] = $key; // 则将数组 $b 的元素值，设置成备份数组 $a 的键名
		//返回用数组 $b 的键值作为键名,数组 $arr 的键值作为键值,所组成的数组
		return array_combine($b, $arr);
	}


}