<?php
require 'init.php';

if( empty(onedrive::$app_url) ){
	route::any('/install',function(){
			$authorize_url = onedrive::authorize_url();
			if (empty($_REQUEST['code'])) {
				return view::load('auth')->with('authorize_url',$authorize_url);
			}
			$data = onedrive::authorize($_REQUEST['code']);
			if(empty($data['access_token'])){
				return view::load('auth')->with('authorize_url',$authorize_url)
							->with('error','认证失败');
			}
			$app_url = onedrive::get_app_url($data['access_token']);
			if(empty($app_url)){
				return view::load('auth')->with('authorize_url',$authorize_url)
							->with('error','获取app url 失败');
			}
			config('refresh_token', $data['refresh_token']);
			config('app_url', $app_url);
			view::direct('/');
	});
	if((route::$runed) == false){
		view::direct('?/install');
	}
}



route::get('{path:#all}',function(){
	//获取路径和文件名
	$paths = explode('/', $_GET['path']);
	if(substr($_SERVER['REQUEST_URI'], -1) != '/'){
		$name = urldecode(array_pop($paths));
	}
	$url_path = get_absolute_path(implode('/', $paths));
	$path = config('onedrive_root').$url_path;
	
	//是否有缓存
	list($time, $items) = cache('dir_'.$path);
	//缓存失效或文件不存在，重新抓取
	if( !is_array($items) || (TIME - $time) > config('cache_expire_time') || (!empty($name) && !array_key_exists($name, $items))){
		$items = onedrive::dir($path);
		if(is_array($items)){
			$time = TIME;
			cache('dir_'.$path, $items);
		} else {
			http_response_code(404);
			view::load('404')->with('path',urldecode($url_path))->show();
			die();
		}
	}
	//输出
	if(!empty($name)){//file
		//文件不存在
		if (!array_key_exists($name, $items)) {
			http_response_code(404);
			view::load('404')->with('path',urldecode($url_path).$name)->show();
			die();
		}
		//是文件夹
		if ($items[$name]['folder']) {
			header('Location: '.$_SERVER['REQUEST_URI'].'/');
		}
		if(in_array($_GET['thumbnails'],['large','medium','small'])){
			list($time, $item) = cache('thumbnails_'.$path.$name);
			if(empty($item[$_GET['thumbnails']]) ||  (TIME - $time) > config('cache_expire_time') ){
				$item = onedrive::thumbnails($path.$name); 
				if(!empty($item)){
					cache('thumbnails_'.$path.$name, $item);
				}
			}
			$url = $item[$_GET['thumbnails']]['url'];
		}else{
			$url = $items[$name]['downloadUrl'];
		}
		header('Location: '.$url);
	}else{//dir
		
		view::load('list')->with('path',$url_path)->with('items', $items)->show();
	}
	
	//后台刷新缓存
	if((TIME - $time) > config('cache_refresh_time')){
		fastcgi_finish_request();
		$items = onedrive::dir($path);
		if(is_array($items)){
			cache('dir_'.$path, $items);
		}
	}
});

