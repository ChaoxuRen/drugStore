<?php
namespace app\app\controller;

use think\captcha;
use think\Controller;
use think\Validate;
use think\Loader;

header('Content-Type: text/plain; charset=utf-8');

class Index extends Base
{
	public function index()
	{
		$data = $_SERVER;
//		echo json_encode($_SERVER);
//		var_dump($_SERVER);
		echo self::returnJSON($data, 1, '成功');
	}


}