<?php
	class onedrive{
		static $client_id;
		static $client_secret;
		static $redirect_uri;
		static $app_url;

		static function authorize_url(){
			$client_id = self::$client_id;
			$redirect_uri = self::$redirect_uri;
			$url ="https://login.microsoftonline.com/common/oauth2/authorize?response_type=code&client_id={$client_id}&redirect_uri={$redirect_uri}";
			$url .= '&state='.urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
			return $url;
		}
		
		static function authorize($code = "", $resource_id="https://api.office.com/discovery/"){
			$client_id = self::$client_id;
			$client_secret = self::$client_secret;
			$redirect_uri = self::$redirect_uri;

			$url = "https://login.microsoftonline.com/common/oauth2/token";
			$post_data = "client_id={$client_id}&redirect_uri={$redirect_uri}&client_secret={$client_secret}&code={$code}&grant_type=authorization_code&resource={$resource_id}";
			fetch::$headers = "Content-Type: application/x-www-form-urlencoded";
			$resp = fetch::post($url, $post_data);
			$data = json_decode($resp->content, true);
			return $data;
		}

		static function get_app_url($token){
			fetch::$headers = "Authorization: bearer {$token}";

			$resp = fetch::get("https://api.office.com/discovery/v2.0/me/services");

			$data = json_decode($resp->content, true);
			if(!empty($data['value'])){
				return $data['value'][0]['serviceResourceId'];
			}
			return ;
		}

		static function access_token(){
			$token = config('@token');
			if($token['expires_on'] > time()+600){
				return $token['access_token'];
			}else{
				$refresh_token = config('refresh_token@config');
				$token = self::get_token($refresh_token);
				if(!empty($token['refresh_token'])){
					config('@token', $token);
					return $token['access_token'];
				}
			}
			return "";
		}
		
		static function get_token($refresh_token){
			$client_id = self::$client_id;
			$client_secret = self::$client_secret;
			$redirect_uri = self::$redirect_uri;
			$resource_id = self::$app_url;
			$url = "https://login.microsoftonline.com/common/oauth2/token";
			$post_data = "client_id={$client_id}&redirect_uri={$redirect_uri}&client_secret={$client_secret}&refresh_token={$refresh_token}&grant_type=refresh_token&resource={$resource_id}";
			fetch::$headers = "Content-Type: application/x-www-form-urlencoded";
			$resp = fetch::post($url, $post_data);
			$data = json_decode($resp->content, true);
			return $data;
		}

		static function human_filesize($size, $precision = 1) {
			for($i = 0; ($size / 1024) > 1; $i++, $size /= 1024) {}
			return round($size, $precision).['B','kB','MB','GB','TB','PB','EB','ZB','YB'][$i];
		}

		static function dir($path="/"){
			$token = self::access_token();
			fetch::$headers = "Authorization: bearer {$token}";
			if($path != '/'){
				$path = ':'.rtrim($path, '/').':/';
			}
			$url = self::$app_url."_api/v2.0/me/drive/root".$path."children";
			$resp = fetch::get($url);
			$data = json_decode($resp->content, true);
			if(!empty($data['@odata.nextLink'])){
				self::dir_next_page($data['@odata.nextLink'], $data);
			}
			if(empty($data)){
				return false;
			}
			foreach((array)$data['value'] as $item){
				$return[$item['name']] = array(
					'name'=>$item['name'],
					'size'=>self::human_filesize($item['size']),
					'createdDateTime'=>strtotime($item['createdDateTime']),
					'lastModifiedDateTime'=>strtotime($item['lastModifiedDateTime']),
					'downloadUrl'=>$item['@content.downloadUrl'],
					'folder'=>empty($item['folder'])?false:true
				);
			}
			return (array)$return;
		}

		static function dir_next_page($nextlink, &$data){
			$resp = fetch::get($nextlink);
			$next_data = json_decode($resp->content, true);
			$data['value'] = array_merge($data['value'],$next_data['value']);
			if(!empty($next_data['@odata.nextLink'])){
				self::dir_next_page($next_data['@odata.nextLink'], $data);
			}
		}

		static function thumbnails($path){
			$token = self::access_token();
			fetch::$headers = "Authorization: bearer {$token}";
			$url = self::$app_url."_api/v2.0/me/drive/root:/".$path.':/thumbnails';
			$resp = fetch::get($url);
			$data = json_decode($resp->content, true);
			if(!empty($data['value'][0])){
				return $data['value'][0];
			}
			return false;
		}

		//�ļ��ϴ�����
		static function upload($path,$content){
			$token = self::access_token();
			fetch::$headers = "Authorization: bearer {$token}";
			$url = self::$app_url."_api/v2.0/me/drive/root:/".$path.':/content';	
			$resp = fetch::put($url,$content);
			$data = json_decode($resp->content, true);
			return @$data['@content.downloadUrl'];
		}

		static function create_upload_session($path){
			$token = self::access_token();
			fetch::$headers = "Authorization: bearer {$token}\r\nContent-Type: application/json";
			$url = self::$app_url."_api/v2.0/me/drive/root:/".$path.":/createUploadSession";
			$post_data = '{"item": {"@microsoft.graph.conflictBehavior": "rename","name": "'.$path.'"}}';
			$resp = fetch::post($url,$post_data);
			$data = json_decode($resp->content, true);
			return $data['uploadUrl'];
		}

		static function upload_session($url, $file, $offset, $length){
			$token = self::access_token();
			
			$file_size = filesize($file);
			$content_length = (($offset+$length)>$file_size)?($file_size-$offset):$length;
			$end = $offset+$content_length-1;
			$post_data = self::file_content($file, $offset, $length);

			$request['url'] = $url;
			$request['headers'] = "Authorization: bearer {$token}".PHP_EOL;
			$request['headers'] .= "Content-Length: {$content_length}".PHP_EOL;
			$request['headers'] .= "Content-Range: bytes {$offset}-{$end}/{$file_size}";
			$request['post_data'] = $post_data;

			$resp = fetch::put($request);
			$data = json_decode($resp->content, true);
			return $data;
		}

		static function upload_session_status($url){
			$token = self::access_token();
			fetch::$headers = "Authorization: bearer {$token}\r\nContent-Type: application/json";
			$resp = fetch::get($url);
			$data = json_decode($resp->content, true);
			return $data;
		}

		static function file_content($file, $offset, $length){
			$handler = fopen($file, "r");
			fseek($handler, $offset);
			return fread($handler, $length);
		}
	}