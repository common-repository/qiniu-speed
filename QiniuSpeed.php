<?php
/*
Plugin Name: QiniuSpeed for WordPress
Description: 1) 绕过服务器 最大上传文件大小 限制(基于七牛上传模式2实现)！2) 七牛现注册提供免费10G空间，意味着您将拥有10G免费的附件空间！3) 实现 WordPress 静态文件 CDN 加速，极大加快访问速度！4) 附件所有操作皆可在 WordPress 后台管理完成，省去您登陆 FTP 的麻烦！5) 为您节省一大笔购买空间、带宽的费用！欢迎反馈各类 bug ，联系方式 <a href="mailto:728172841@qq.com" > E-mail:728172841@qq.com </a> 1) Break through the server maximum upload file size limit! 2) Now,Qiniu provide free 10GB space.it means that you will have the accessory space free 10GB! 3) Implementation of WordPress static file CDN acceleration, maximum speed up access! 4) All of media file operations can be done in WordPress back-stage management, without login FTP when upload large file! 5) Save a lot of money to buy server space and bandwidth!
Plugin URI: 
Author: <a href="mailto:728172841@qq.com" > Hong Jian Qiang </a>
Author URI: 
Version: 1.3
*/

date_default_timezone_set( get_option('timezone_string') );

//七牛云存储服务配置信息。	
$QiniuSpeedOption 	= (array) get_option( 'QiniuSpeed' );
$bucket 			= $QiniuSpeedOption['bucket']; 			// 七牛的 bucket
$CallbackUrl 		= home_url('/');	//上传到七牛云存储后，七牛云存储向Web服务器Post数据的地址。注意，必须要有 / ，否则不能接受七牛云存储Post的数据！
$CallbackBody 		= "rfile=$(x:rfile)&etag=$(etag)&fname=$(fname)&fsize=$(fsize)&mimeType=$(mimeType)&imageInfo=$(imageInfo)&exif=$(exif)&endUser=$(endUser)";  //七牛服务器向AppServer服务器Post的数据
$accessKey 			= $QiniuSpeedOption['accesskey'];	// 七牛的 AccessKey
$secretKey 			= $QiniuSpeedOption['secretkey'];	// 七牛的 SecretKey
$StoragePath		= $QiniuSpeedOption['storagepath'];	// 七牛的 存储路径

require_once("InfoFromQiniu.php");

//若当前不在控制面板页面，则定义在七牛绑定的域名，用于七牛CDN加速 或 处理七牛云存储向Web服务器Post的数据。
if(!is_admin()){
	$Qiniu_Speed = (array) get_option( 'QiniuSpeed' );

	if(isset($Qiniu_Speed ['host'])){
		define( 'CDN_HOST', $Qiniu_Speed ['host'] );
  
		function Qiniu_Speed_start_ob_cache(){
            ob_start();
        }
		add_action("wp_loaded", 'Qiniu_Speed_start_ob_cache' ,9999);
		
		// 定义在七牛绑定的域名，用于七牛CDN加速
        function Qiniu_Speed_stop_ob_cache(){
            $html = ob_get_contents();
            ob_end_clean();
            $regex =  '/'.str_replace( '/', '\/', home_url() ).'\/((wp-content|wp-includes)\/[^\s]{1,}.(js|css|png|jpg|jpeg|gif|ico)[^\"\'\s]{0,})/';
            echo preg_replace($regex, CDN_HOST.'/$1', $html);
        }
		add_action("wp_footer", 'Qiniu_Speed_stop_ob_cache' ,9999);
	}
}
	
//若当前在控制面板页面，用于七牛云存储插件的设置及上传媒体操作。
if ( is_admin() ){
	//加载本插件的js文件
	wp_register_script('QiniuSpeed_scripts', plugins_url('js/QiniuSpeedScript.js', __FILE__), array('jquery'), '1.2', true);
	wp_enqueue_script('QiniuSpeed_scripts');
	wp_enqueue_script('jquery-form');

	// 装载七牛的PHP SDK
	require_once("qiniu/io.php");
	require_once("qiniu/rs.php");
	// 七牛 SetKeys
	Qiniu_SetKeys($accessKey, $secretKey);
	
	//上传文件自动以当前的年月日时分秒+随机数的格式重命名
	function Qiniu_Speed_new_filename($filename){
		$info = pathinfo($filename);
		$ext = empty($info['extension']) ? '': '.'.$info['extension'];
		$name = basename($filename, $ext);
		
		return date("YmdHis").rand(10,99).$ext;
	}
	add_filter('sanitize_file_name', 'Qiniu_Speed_new_filename', 10);
	
	/*
	 * Hook 所有上传操作，对于小文件上传完服务器后，再存到云存储，最后删除原来服务器上的文件。
	*/
	function mv_attachments_to_qiniu($data) {
		global $bucket, $StoragePath;

		$object = $StoragePath.basename($data['file']);     
		$file = $data['file'];
		
		$putPolicy = new Qiniu_RS_PutPolicy($bucket);
		$upToken = $putPolicy->Token(null);
		$putExtra = new Qiniu_PutExtra();
		$putExtra->Crc32 = 1;
		Qiniu_PutFile($upToken, $object, $file, $putExtra);
		$url = "http://$bucket.qiniudn.com/$object"; 	
		unlink( $data['file'] ); //删除服务器上的文件
		
		return array( 'file' => basename($url), 'url' => $url, 'type' => $data['type'] );
	}
	add_filter('wp_handle_upload', 'mv_attachments_to_qiniu');
	
	if( isset($bucket) && isset($accessKey) && isset($secretKey) ){
		/*
		 * 移除默认的 "多媒体" => "添加" 子菜单
		*/
		function Qiniu_Speed_remove_submenu() {
			remove_submenu_page( 'upload.php', 'media-new.php' );
		}
		add_action('admin_init','Qiniu_Speed_remove_submenu');

		/*
		 * 添加 "多媒体" => "上传" 子菜单（重构一个上传子页面，用于上传大文件，采用七牛上传模式2）
		*/
		function Qiniu_Speed_add_submenu() {
			add_submenu_page( 'upload.php', '上传新媒体文件到云存储', '上传到云存储', 'manage_options', 'UploadToCloud-Page', 'Qiniu_Speed_Upload_Page_func');
		}
		add_action('admin_menu', 'Qiniu_Speed_add_submenu');
	}

	function Qiniu_Speed_Upload_Page_func() {
		global $bucket, $CallbackUrl, $CallbackBody, $StoragePath;
		
		$putPolicy = new Qiniu_RS_PutPolicy($bucket);
		$putPolicy->CallbackUrl = $CallbackUrl;
		$putPolicy->CallbackBody = $CallbackBody;
		$upToken = $putPolicy->Token(null);
		
		// "多媒体" => "上传" 子菜单页面的HTML代码
		echo '
		<div class="wrap">';
		screen_icon();
		echo '
		<h2>上传新媒体文件到云存储</h2>
		<form id="Upload-To-Cloud" class="media-upload-form" target="post_frame" method="post" action="http://up.qiniu.com/" enctype="multipart/form-data">
			<input id="RemoteFile" name="key" type="hidden" value="" />
			<input id="RFile" name="x:rfile" type="hidden" value="" />
			<input id="LocalFile" name="file" type="file" />
			<input id="StoragePath" type="hidden" value="'.$StoragePath.'" />
			<input name="token" type="hidden" value="'.$upToken.'" />
			<input id="UploadBtn" class="button" type="submit" value="上传" name="UploadBtn" />
		</form>
		<p>您正在使用浏览器内置的标准上传工具，文件将会直接上传到云存储，所以不必担心最大上传文件大小的限制。</p>
		<p>上传完成后，可以移步到 "媒体库" 中查看该文件（可能会有几秒的延时，刷新 "媒体库" 即可）</p>
		<p>返回信息：</p>
		<iframe id="post_frame" name="post_frame" style="border:1px red solid;width:100%;height:50px;" mce_style="width:100%;height:50px;">
		<html>
		<head></head>
		<body>Hello!</body>
		</html>
		</iframe>
		';
	}

	/*
	 * 删除七牛上的附件
	*/
	function del_attachments_from_qiniu($file) {
		global $wpdb, $bucket, $StoragePath;

		$object = $StoragePath.basename($file);
		$client = new Qiniu_MacHttpClient(null);
		Qiniu_RS_Delete($client, $bucket, $object);
		
		return $file;
	}
	add_action('wp_delete_file', 'del_attachments_from_qiniu');

	/*
	 * Hook 所有文件URL，使得类似于 "多媒体" => "媒体库" => "编辑" 中右侧中的 文件URL 能显示连接到 七牛云服务器
	*/
	function qiniu_attachment_url( $url ) {
		//对URL重构，使其指向七牛云存储
		global $bucket, $StoragePath;
		
		$url = "http://$bucket.qiniudn.com/$StoragePath".basename($url);
		
		return $url;
	}
	add_action('wp_get_attachment_url', 'qiniu_attachment_url');

	function Qiniu_Speed_admin_head(){
	?>
		<style>#icon-qiniutek{background-image: url("<?php echo plugins_url('qiniulogo.png', __FILE__); ?>");background-repeat: no-repeat;}</style>
	<?php
	}
	add_action('admin_head','Qiniu_Speed_admin_head');

	function Qiniu_Speed_admin_menu() {
		add_options_page( '七牛云存储设置', '七牛云存储', 'manage_options', 'QiniuSpeed', 'Qiniu_Speed_setting_page' );
	}
	add_action( 'admin_menu', 'Qiniu_Speed_admin_menu' );
	
	function Qiniu_Speed_setting_page() {
		?>
		<div class="wrap">
			<div id="icon-qiniutek" class="icon32"><br></div>
			<h2>七牛云存储设置</h2>
			<form action="options.php" method="POST">
				<?php settings_fields( 'QiniuSpeed-group' ); ?>
				<?php do_settings_sections( 'QiniuSpeed' ); ?>
				<?php submit_button(); ?>
			</form>
			<h2>赞助 Donate</h2>
			<p>如果您发现这个插件对您有帮助，欢迎<a href="https://me.alipay.com/hongjianqiang" target="_blank">赞助 Donate</a>！有您的支持，我一定会做得更好！</p>
			<p>比特币赞助地址为(Bitcoin Donate Address)：14wLGwhzQE7dUDFrXnjm2vimRF5qYssfx6</p>
			<p><a href="https://me.alipay.com/hongjianqiang" target="_blank"><img src="<?php echo plugins_url('alipaylogo.png', __FILE__); ?>" alt="支付宝赞助" title="支付宝" /></a></p>
			<br />
		</div>
		<?php
	}
	
	/*
	 * 添加设置
	*/
	function Qiniu_Speed_admin_init() {
		
		register_setting( 'QiniuSpeed-group', 'QiniuSpeed', '' );
		add_settings_section( 'QiniuSpeed-section', '', '', 'QiniuSpeed' );

		$Qiniu_Speed_settings_fields = array(
			array('name'=>'host', 'title'=>'七牛绑定的域名（CDN镜像加速）', 'type'=>'text', 'default'=>'http://xxxx.qiniudn.com', 'description'=>'设置为你在七牛绑定的域名即可，注意要域名前面要加上 http://，结尾无需 / 。'),
			array('name'=>'bucket', 'title'=>'Bucket', 'type'=>'text', 'default'=>'', 'description'=>'<a href="https://portal.qiniu.com/signup?code=2ao6dcv3kw501" target="_blank">注册</a>，然后访问 <a href="https://portal.qiniu.com/" target="_blank">七牛云存储</a> 创建一个 <font color="red">公开空间</font> 后，填写以上内容。( <font color="blue">空间名</font> 即是你的bucket)'),
			array('name'=>'accesskey', 'title'=>'Access Key', 'type'=>'text', 'default'=>'', 'description'=>'访问 <a href="https://portal.qiniu.com/setting/key" target="_blank">七牛云存储密钥</a> 管理页面，获取 Access Key 和 Secret Key'),
			array('name'=>'secretkey', 'title'=>'Secret Key', 'type'=>'text', 'default'=>'', 'description'=>''),
			array('name'=>'storagepath', 'title'=>'七牛存储路径', 'type'=>'text', 'default'=>'wp-content/uploads/', 'description'=>'若存储于 bucket 根(目录)下，请为空。')
		);

		foreach ($Qiniu_Speed_settings_fields as $field) {
			add_settings_field( 
				$field['name'],
				$field['title'],        
				'Qiniu_Speed_settings_field_callback',    
				'QiniuSpeed', 
				'QiniuSpeed-section',    
				$field
			);
		}
		
	}
	add_action( 'admin_init', 'Qiniu_Speed_admin_init' );

	function Qiniu_Speed_settings_field_callback($args) {
		$Qiniu_Speed = (array) get_option( 'QiniuSpeed' );
		
		if($args['type'] == 'text'){
			if(isset($Qiniu_Speed[$args['name']])){
				$value = $Qiniu_Speed[$args['name']];
			}
			if($value == '') $value = $args['default'];
			echo '<input type="text" name="QiniuSpeed['.$args['name'].']" value="'.$value.'" class="regular-text" />';
		} else if ($args['type'] == 'checkbox'){
			$checked = '';
			if(isset($Qiniu_Speed[$args['name']])){
				$value = $Qiniu_Speed[$args['name']];
				if($value){
					$checked = 'checked';
				}
			}
			echo '<label for="QiniuSpeed['.$args['name'].']"><input name="QiniuSpeed['.$args['name'].']" type="checkbox" id="QiniuSpeed['.$args['name'].']" ' . $checked . ' value="1">'.$args['label'].'</label>';
		}
		if(isset($args['description'])) echo '<p class="description">'.$args['description'].'</p>';
	}
}

/*
 * 调试函数
*/
function Qiniu_Speed_debug( $str ) {
	global $wpdb;
	
	// 创建数据库
	$query ='CREATE TABLE IF NOT EXISTS QiniuSpeedDebug (
		`id` bigint(20) NOT NULL auto_increment,
		`content` text NOT NULL,
		`date` datetime NOT NULL,
		PRIMARY KEY (`id`)
	) COLLATE utf8_general_ci;';
	$wpdb->query( $query );
	
	// 把调试信息插入数据库中
	$wpdb->insert('QiniuSpeedDebug', array('content' => $str,
									'date' => date('Y-m-d H:i:s') ),
									array( '%s', '%s' ));
}