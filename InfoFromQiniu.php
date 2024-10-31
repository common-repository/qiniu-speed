<?php
/* 
 *	七牛云存储向Web服务器Post数据的处理函数
 *
 *	该函数把从七牛云存储获得的数据存入MySQL数据库中
 */

require_once("qiniu/rs.php");

add_action("wp_footer", "Info_From_Qiniu");

function Info_From_Qiniu(){
	global $wpdb, $bucket, $accessKey, $secretKey, $StoragePath;
/*
	echo "<p>Debug Info:</p>";
	$client = new Qiniu_MacHttpClient(null);
	list($ret, $err) = Qiniu_RS_Stat( $client, "qiniuspeed", "wp-content/uploads/20130819221637.xls" );
	if ($err !== null) {
		var_dump($err);
	} else {
		var_dump($ret);
	}
*/	
	if( isset($_POST['etag']) ){
		$rfile	 	= strip_tags($_POST['rfile']);
		$etag 		= strip_tags($_POST['etag']);
		$fname		= strip_tags($_POST['fname']);	
		$fsize		= strip_tags($_POST['fsize']);
		$mimeType	= strip_tags($_POST['mimeType']);
		$imageInfo	= strip_tags($_POST['imageInfo']);	
		$exif		= strip_tags($_POST['exif']);	
		$endUser	= strip_tags($_POST['endUser']);
		
		Qiniu_SetKeys($accessKey, $secretKey);
		$client = new Qiniu_MacHttpClient(null);
		list($ret, $err) = Qiniu_RS_Stat( $client, $bucket, $rfile );
		//if ($err !== null) { var_dump($err); Qiniu_Speed_debug($err.'#err#'); } else { var_dump($ret); Qiniu_Speed_debug($ret.'#ret#'); }
		$path = basename($rfile);
		$qiniuURL = "http://$bucket.qiniudn.com/$rfile";
		$SQL = "SELECT count(*) FROM $wpdb->posts WHERE $wpdb->posts.guid='$qiniuURL'";
		// 验证数据来源是否合法
		if( ($err == null) && (0 == $wpdb->get_var($SQL)) && (strpos($rfile, $StoragePath) >= 0) ){
			QiniuSpeed_insert( $fname, $qiniuURL, $mimeType, $path ); 
		}
	}
		
}

/* 
 *	把文件信息插入MySQL数据库中
 *	
 *	$title		AppClient客户端的原文件名
 *	$qiniuURL	文件URL
 *	$mime_type	文件类型
 *	$path		文件路径
 */
function QiniuSpeed_insert( $title, $qiniuURL, $mime_type, $path ){
	global $wpdb;

	$wpdb->insert($wpdb->posts, array('post_author'=>1,
									  'post_title'=>$title,
									  'post_status'=>'inherit',
									  'post_name'=>$title,
									  'guid'=>$qiniuURL,
									  'post_type'=>'attachment',
									  'post_date'=>date('Y-m-d H:i:s'),
									  'post_date_gmt'=>date('Y-m-d H:i:s', gmmktime()),
									  'post_modified'=>date('Y-m-d H:i:s'),
									  'post_modified_gmt'=>date('Y-m-d H:i:s', gmmktime()),
									  'post_mime_type'=>$mime_type),
									  array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'));

	$SQL = "SELECT $wpdb->posts.ID FROM $wpdb->posts WHERE $wpdb->posts.guid='$qiniuURL'";
	$post_id = $wpdb->get_var($SQL);
	
	$wpdb->insert($wpdb->postmeta, array('post_id'=>$post_id,
										 'meta_key'=>'_wp_attached_file',
										 'meta_value'=>$path),
										 array( '%d', '%s', '%s' ));

}

/*自定义json格式处理函数（可以处理中文，斜杠等字符）
例如:
<?php
	$error_respon = array('code' => 'ERROR_MSG_MISS', 'msg' => '消/息不存在');
	echo Qiniu_Speed_JSON($error_respon);
?>
结果为：
{"code":"ERROR_MSG_MISS","msg":"消/息不存在"}
*/
function Qiniu_Speed_arrayRecursive(&$array, $function, $apply_to_keys_also = false)  
{  
	static $recursive_counter = 0;  
	if (++$recursive_counter > 1000) {  
		die('possible deep recursion attack');  
	}  
	foreach ($array as $key => $value) {  
		if (is_array($value)) {  
			Qiniu_Speed_arrayRecursive($array[$key], $function, $apply_to_keys_also);  
		} else {  
			$array[$key] = $function($value);  
		}  
		if ($apply_to_keys_also && is_string($key)) {  
			$new_key = $function($key);  
			if ($new_key != $key) {  
				$array[$new_key] = $array[$key];  
				unset($array[$key]);  
			}  
		}  
	}  
	$recursive_counter--;  
}  

function Qiniu_Speed_JSON($array) {  
	Qiniu_Speed_arrayRecursive($array, 'urlencode', true);  
	$json = json_encode($array);  
	return urldecode($json);  
}
?>