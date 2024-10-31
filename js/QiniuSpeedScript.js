/*
 * 七牛镜像存储插件的javascript文件
 * Version: 1.3
*/

/* 上传页面中的 Ajax 上传 */
jQuery(document).ready(function($){
	var options = {
		//target: '#output',
		beforeSubmit: validate,
		//success: showResponse,
		//url: url,
		//type: type,
		//dataType: json,
		clearForm: true,
		resetForm: true,
		timeout: 5000
	}
	
	/*
	 * 上传前的验证操作
	*/
	function validate(formData, jqForm, options){
		var fileValue = $("input[name=file]").fieldValue();
		if( !fileValue[0] ){
			alert("上传文件路径不能为空！");
			return false;
		}
		var queryString = $.param(formData);
		return true;
	}
//	$("#Upload-To-Cloud").ajaxForm(options);
	
	/*
	 * 构造云存储路径
	*/
	$("#UploadBtn").mouseover(function(){
	    // 构造云存储路径
		var cur_time, ext, RFile="";
		cur_time=get_cur_time();
		ext=get_file_ext();
		
		RFile = $("#StoragePath").val()+cur_time+parseInt(Math.random()*100)+ext;
		$("#RemoteFile").attr( "value", RFile );
		$("#RFile").attr( "value", RFile );
	});
	
	$("#UploadBtn").click(function(){
	    // 构造云存储路径
		var cur_time, ext, RFile="";
		cur_time=get_cur_time();
		ext=get_file_ext();
		
		RFile = $("#StoragePath").val()+cur_time+ext;
		$("#RemoteFile").attr( "value", RFile );
		$("#RFile").attr( "value", RFile );
	});
	
	function get_file_ext(){
		// 取得本地文件路径
		var $filepath = $("#LocalFile").val();
		// 取得本地文件名
		var $filename = $filepath.match(/[^\/]*$/)[0];
		// 取得本地文件名后缀
		var ext = $filename.substr($filename.indexOf("."));
		
		return ext;
	}
	function get_cur_time(){
		// 构造当前时间，年月日时分秒
		var d = new Date(), cur_time = "";
		cur_time += d.getFullYear();
		if( (d.getMonth()+1)<=9 ) { cur_time += "0"; }
		cur_time += d.getMonth()+1;
		if( (d.getDate())<=9 ) { cur_time += "0"; }
		cur_time += d.getDate();
		if( (d.getHours())<=9 ) { cur_time += "0"; }
		cur_time += d.getHours();
		if( (d.getMinutes())<=9 ) { cur_time += "0"; }
		cur_time += d.getMinutes();
		if( (d.getSeconds())<=9 ) { cur_time += "0"; }
		cur_time += d.getSeconds();
		
		return cur_time;
	}
});

/* 修改 "媒体库" 中 添加按钮指向的位置 */
jQuery(document).ready(function($){
	if( "media-new.php" == $(".add-new-h2").attr( "href" ) ){
		$(".add-new-h2").attr( "href", "upload.php?page=UploadToCloud-Page" );
	}
});