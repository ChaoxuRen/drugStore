<?php
namespace app\app\controller;

use think\Controller;
use think\Validate;
use think\Loader;

//use think\Db;

header('Content-Type: text/plain; charset=utf-8');

class Map extends Base
{
	/**
	 * 获取附近药店
	 * @param  coord_x
	 * @param  coord_y
	 */
	public function get_nearby_shop()
	{
		$coordX = input('post.coord_x');
		$coordY = input('post.coord_y');
		if ($coordY == false || $coordX == false) {
			self::returnJSON(null, 2, '定位坐标出错');
		}
		$scope = self::GetRange($coordX, $coordY);
		$shop = model('Map')->search_coords($scope['minX'], $scope['maxX'], $scope['minY'], $scope['maxY']);
		if (!$shop) {
			self::returnJSON(null, 3, '暂无数据');
		}
		self::returnJSON($shop, 1, '成功');
	}

}