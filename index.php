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

function handle_folder($path) {
	global $cache;
	list($expire, $items) = $cache->get('dir_'.$path);
	// 缓存无效
	if (TIME > $expire || !is_array($items)) {
		// 从 API 获取
		$api_items = onedrive::dir(config('onedrive_root').$path);
		// 设置缓存
		if(is_array($api_items)) {
			$items = $api_items;
			$cache->set('dir_'.$path, $items);
		}
	}
	if (!is_array($items)) {
		// 404
		http_response_code(404);
		view::load('404')->with('path',urldecode($path))->show();
		die();
	}
	view::load('list')->with('path',$path)->with('items', $items)->show();
	if(!is_array($api_items) && TIME > $expire - config('cache_expire_time') + config('cache_refresh_time')) {
		fastcgi_finish_request();
		$api_items = onedrive::dir(config('onedrive_root').$path);
		if(is_array($api_items)){
			$cache->set('dir_'.$path, $api_items);
		}
	}
}

function handle_file($path, $name) {
	global $cache;
	list($expire, $items) = $cache->get('dir_'.$path);
	// 缓存无效
	if (TIME > $expire || empty($items[$name])) {
		// 从 API 获取
		$api_items = onedrive::dir(config('onedrive_root').$path);
		// 设置缓存
		if(is_array($api_items)) {
			$items = $api_items;
			$cache->set('dir_'.$path, $items);
		}
	}
	if (empty($items[$name])) {
		// 404
		http_response_code(404);
		view::load('404')->with('path',urldecode($path).$name)->show();
		die();
	}
	//是文件夹
	if ($items[$name]['folder']) {
		header('Location: '.$_SERVER['REQUEST_URI'].'/');
		die();
	}
	// redirect
	header('Location: '.$items[$name]['downloadUrl']);
	// 刷新缓存
	if(!is_array($api_items) && TIME > $expire - config('cache_expire_time') + config('cache_refresh_time')) {
		fastcgi_finish_request();
		$api_items = onedrive::dir(config('onedrive_root').$path);
		if(is_array($api_items)){
			$cache->set('dir_'.$path, $api_items);
		}
	}
}

function handle_thumbnail($path, $size) {
	global $cache;
	// 是否有缓存
	list($expire, $item) = $cache->get('thumbnails_'.$path);
	if(TIME > $expire || empty($item[$size])){
		$api_item = onedrive::thumbnails(config('onedrive_root').$path); 
		if(!empty($api_item)) {
			$item = $api_item;
			$cache->set('thumbnails_'.$path, $item);
		}
	}
	if (empty($item[$size]['url'])) {
		// 404
		http_response_code(404);
		view::load('404')->with('path',urldecode($path))->show();
		die();
	}
	// redirect
	header('Location: '.$item[$size]['url']);
	// 刷新缓存
	if(empty($api_item) && TIME > $expire - config('cache_expire_time') + config('cache_refresh_time')) {
		fastcgi_finish_request();
		$api_item = onedrive::thumbnails(config('onedrive_root').$path);
		if(is_array($api_item)){
			$cache->set('thumbnails_'.$path, $api_item);
		}
	}
}

route::get('{path:#all}',function(){
	//获取路径和文件名
	$paths = explode('/', $_GET['path']);
	if(substr($_SERVER['REQUEST_URI'], -1) != '/'){
		$name = urldecode(array_pop($paths));
	}
	$path = get_absolute_path(implode('/', $paths));

	if (empty($name)) {
		handle_folder($path);
	} elseif(in_array($_GET['thumbnails'], ['large','medium','small'])) {
		handle_thumbnail($path.$name, $_GET['thumbnails']);
	} else {
		handle_file($path, $name);
	}
});

