<?php
namespace app\app\controller;

use think\captcha;
use think\Exception;
use think\Controller;
use think\Validate;
use think\Loader;
use think\Db;

header('Content-Type: text/plain; charset=utf-8');

class User extends Base
{
	/**
	 * 获取验证码
	 * @param phone 用户电话号码
	 */
	public function reg_captcha()
	{
		$phone = input('post.username');
		$num = self::randStr(6, 'NUMBER');
		$is_phone = self::is_phone($phone);
		//如果PHONE不合法
		if (!$is_phone) {
			self::returnJSON(NULL, 2, '你输入的手机号码不合法');
		}
		//判断当天已发送几次
		$count = model('Common')->search_one(['phone' => $phone], 'count', 'captcha');
		if ($count['count'] >= 3) {
			self::returnJSON(NULL, 3, '今天已使用3次，请明天再试');
		}
		//判断是否存在该用户的验证码
		$data['phone'] = $phone;
		$is_exists = Loader::model('Common')->is_exists($data, 'captcha');
		$data['captcha'] = $num;
		$data['expire_time'] = time() + 60;

		try {
			if (!$is_exists) {
				$is_add = Loader::model('Common')->insert($data, 'captcha');
			} else {
				$is_add = Loader::model('Common')->updates(['phone' => $phone], ['captcha' => $num, 'expire_time' => $data['expire_time']], 'captcha');
			}
			if (!$is_add) {
				self::returnJSON(NULL, 3, '验证码获取异常,请重试');
			}

			$is_send = Loader::model('Sms')->send_SMS($phone, $num);
			if ($is_send['code'] != 'OK') {
				self::returnJSON(NULL, 0, $is_send['msg']);
			}
			//后续待改进  判断成功后才发送， 不然回撤
			Db::table('xu_captcha')->where('phone', $phone)->setInc('count');
			self::returnJSON(NULL, 1, $is_send['msg']);
		} catch (Exception $e) {
			self::returnJSON(NULL, 0, $e);
		}


	}

	/**
	 * 用户注册
	 * @param username 用户名
	 * @param password 密码
	 * @param captcha 验证码
	 */

	public function reg_user()
	{
		$data = input('post.');
		$is_phone = self::is_phone($data['username']);
		//验证数据
		if (!$is_phone) {
			self::returnJSON(NULL, 2, '你输入的手机号码不合法');
		}
		$rule = [
			'password|密码' => 'require|min:6|max:15',
			'captcha|验证码' => 'require|number|min:6|max:6'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$is_exists = Loader::model('Common')->is_exists(['username' => $data['username']], 'users');
		if ($is_exists) {
			self::returnJSON(NULL, 3, '你已经注册过了');
		}
		$modelInfo = model('Common')->search_one(['phone' => $data['username']], 'captcha,expire_time', 'captcha');
		if (!$modelInfo || $modelInfo['expire_time'] < time()) {
			self::returnJSON(NULL, 5, '你的验证码已经过期');
		}
		if ($modelInfo['captcha'] != $data['captcha']) {
			self::returnJSON(NULL, 3, '验证码错误');
		}
		//环信注册用户
		$Hx = new \Huanxin\Hx();
		$Hx_result = $Hx->hx_register($data['username'], $data['password']);
		$Hx_result = json_decode($Hx_result, true);
		if (!isset($Hx_result['action'])) {
			self::returnJSON(NULL, 3, '环信帐号注册失败');
		}
		try {
			$is_add = Loader::model('Common')->insert(['username' => $data['username'], 'password' => md5($data['password']), 'create_time' => time()], 'users');
			if (!$is_add) {
				self::returnJSON(NULL, 4, '注册失败，需重试');
			}
			self::returnJSON(NULL, 1, '成功');
		} catch (Exception $e) {
			self::returnJSON(NULL, 0, '注册失败，需重试');
		}

	}

	/**
	 *  用户登录
	 * @param username
	 * @param password
	 */

	public function user_login()
	{
		$data = input('post.');
		$is_phone = self::is_phone($data['username']);
		//验证数据
		if (!$is_phone) {
			self::returnJSON(NULL, 2, '你输入的手机号码不合法');
		}
		$rule = [
			'password|密码' => 'require|min:6|max:15'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$UserModelInfo = model('Common')->search_one(['username' => $data['username']], 'user_id,name,head_img,birthday,weight,height,sex,username,password', 'users');
		if (!$UserModelInfo) {
			self::returnJSON(NULL, 3, '无此用户');
		}
		if ($UserModelInfo['password'] != MD5($data['password'])) {
			self::returnJSON(NULL, 3, '密码错误');
		}
		unset($UserModelInfo['password']);
		$UserModelInfo['head_img'] = PUBLIC_URL . 'images/head_img/' . $UserModelInfo['head_img'];
		self::returnJSON($UserModelInfo, 1, '成功');

	}

	/**
	 * 找回密码
	 * @param username
	 * @param password
	 * @param captcha
	 */
	public function forget_psw()
	{
		$data = input('post.');
		$is_phone = self::is_phone($data['username']);
		//验证数据
		if (!$is_phone) {
			self::returnJSON(NULL, 2, '你输入的手机号码不合法');
		}
		$rule = [
			'password|密码' => 'require|min:6|max:15',
			'captcha|验证码' => 'require|number|min:6|max:6'
		];
		$validate = new Validate($rule);
		$result = $validate->check($data);
		if (!$result) {
			self::returnJSON(NULL, 2, $validate->getError());
		}
		$userModel = Loader::model('Common')->is_exists(['username' => $data['username']], 'users');
		if (!$userModel) {
			self::returnJSON(NULL, 3, '无此用户');
		}
		if (MD5($data['password']) == $userModel['password']) {
			self::returnJSON(NULL, 6, '与原密码相同');
		}

		$modelInfo = model('Common')->search_one(['phone' => $data['username']], 'captcha,expire_time', 'captcha');
		if (!$modelInfo || $modelInfo['expire_time'] < time()) {
			self::returnJSON(NULL, 5, '你的验证码已经过期');
		}
		if ($modelInfo['captcha'] != $data['captcha']) {
			self::returnJSON(NULL, 3, '验证码错误');
		}

		//环信注册用户
		$Hx = new \Huanxin\Hx();
		$Hx_result = $Hx->hx_user_update_password($data['username'], $data['password']);
		$Hx_result = json_decode($Hx_result, true);
		if (!isset($Hx_result['action'])) {
			self::returnJSON(NULL, 3, '环信帐号注册失败');
		}
		$is_ok = Loader::model('Common')->updates(['username' => $data['username']], ['password' => MD5($data['password'])], 'users');
		if (!$is_ok) {
			self::returnJSON(NULL, 0, '异常');
		}
		self::returnJSON(NULL, 1, '成功');


	}

	/**
	 * 头像上传
	 * @param user_id 用户ID
	 * @param head_img 图片上传参数
	 */

	public function upload_head_img()
	{
		$file = request()->file('head_img0');
		$fileName = 'IMG' . input('post.user_id') . time();
		if (!isset($file)) {
			self::returnJSON(NULL, 2, '图片不存在');
		}
		$imageInfo = $file->getInfo();
		$imagesize = getimagesize($imageInfo['tmp_name']);

		if ($imagesize[0] > 200) {
			self::returnJSON(NULL, 2, '图片宽度超过200PX');
		}
		if ($imagesize[1] > 200) {
			self::returnJSON(NULL, 2, '图片高度超过200PX');
		}
		$userInfo = Loader::model('Common')->search_one(['user_id' => input('post.user_id')], 'head_img', 'users');
		$info = $file->validate(['size' => 1024 * 100, 'ext' => 'jpg,png,gif'])->rule('uniqid')->move(PUBLIC_PATH . 'images/head_img', $fileName);
		if (!$info) {
			self::returnJSON(NULL, 2, $file->getError());
		}
		$is_ok = Loader::model('Common')->updates(['user_id' => input('post.user_id')], ['head_img' => $info->getSaveName()], 'users');

		if (!$is_ok) {
			self::returnJSON(NULL, 4, '保存时出错，请重试');
		}
		//删除以前的图片
		$delFile = PUBLIC_PATH . 'images/head_img/' . $userInfo['head_img'];
		if (file_exists($delFile)) {
//			unlink($delFile);
		}
		self::returnJSON(NULL, 1, '成功');
	}

	/**
	 * 个人信息修改
	 * @param user_id
	 * @param name height weight birthday sex
	 */

	public function update_info()
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
		$where['user_id'] = $data['user_id'];
		unset($data['user_id']);
		$result = model('Common')->updates($where, $data, 'users');
		if (!$result) {
			self::returnJSON(NULL, 4, '请重试');
		}
		self::returnJSON(NULL, 1, '成功');
	}

	/**
	 * 获取个人信息
	 * @param user_id
	 */

	public function get_user_info()
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
		$userInformation = model('Common')->search_one(['user_id' => $data['user_id']], 'username,user_id,name,birthday,height,weight,sex,head_img', 'users');
		if (!$userInformation) {
			self::returnJSON(NULL, 3, '无数据');
		}
		$userInformation['head_img'] = PUBLIC_URL . 'images/head_img/' . $userInformation['head_img'];
		self::returnJSON($userInformation, 1, '成功');
	}


}