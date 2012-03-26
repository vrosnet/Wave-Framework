<?php/* WWW - PHP micro-frameworkIndex gateway file handlerFile handler is used for every file that is accessed and is not already served by other handlers. This handler serves files such as PDF files or other files that are not usually considered 'web' formats.Author and support: Kristo Vaher - kristo@waher.net*/// Currently known location of the file$resource=str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR,DIRECTORY_SEPARATOR,$_SERVER['DOCUMENT_ROOT'].$_SERVER['REDIRECT_URL']);// Getting information about current resource$fileInfo=pathinfo($resource);// Assigning file information$file=$fileInfo['basename'];// Solving the folder that client is loading resource from$folder=$fileInfo['dirname'].DIRECTORY_SEPARATOR;// If filename includes & symbol, then system assumes it should be dynamically generated$parameters=array_unique(explode('&',$file));// Getting the downloadable file name$file=array_pop($parameters);// The amount of non-filenames in the request$parameterCount=count($parameters);// Currently assumed MIME type$mimeType='';// Last-modified date$lastModified=filemtime($folder.$file);// No cache flagif(in_array('nocache',$parameters)){	$noCache=true;} else {	$noCache=false;}// Default cache timeout of one month, unless timeout is setif(!isset($config['resource-cache-timeout'])){	$config['resource-cache-timeout']=31536000; // A year}// If more than one parameter is set, it returns 404// 404 is also returned if file does not actually existif($parameterCount>1 || ($parameterCount==1 && !$noCache) || !file_exists($folder.$file)){	// Adding log entry		if(isset($logger)){		$logger->writeLog('404');	}	// Returning 404 header	header('HTTP/1.1 404 Not Found');	die();	}// Checking if file has been modified or notif(!$noCache){	// If the request timestamp is exactly the same, then we let the browser know of this	if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])==$lastModified){				// Adding log entry			if(isset($logger)){			$logger->cacheUsed=true;			$logger->writeLog('304');		}				// Cache headers (Last modified is never sent with 304 header)		header('Cache-Control: public,max-age='.($lastModified+$config['resource-cache-timeout']-$_SERVER['REQUEST_TIME']).',must-revalidate');		header('Expires: '.gmdate('D, d M Y H:i:s',($lastModified+$config['resource-cache-timeout'])).' GMT');				// Returning 304 header		header('HTTP/1.1 304 Not Modified');		die();			}}// Finding the proper MIME typeif(extension_loaded('fileinfo')){	// This opens MIME type 'magic' resource for use	$fileInfo=finfo_open(FILEINFO_MIME_TYPE);	if($fileInfo){		// Finding MIME type with magic resource		$mimeType=finfo_file($fileInfo,$folder.$file);		// Resourse is not needed further, so it is closed		finfo_close($fileInfo);	}		// x-ico header has average support in clients these days	if($mimeType=='image/x-ico'){		$mimeType='image/vnd.microsoft.icon;';	}	} else {	// Since Fileinfo was not available, we use extension-based detection as fallback	$extension=array_pop(explode('.',$file));	switch($extension){		case 'ico':			$mimeType='image/vnd.microsoft.icon;';			break;		case 'zip':			$mimeType='application/zip';			break;		case 'mp3':			$mimeType='audio/mpeg';			break;		case 'gif':			$mimeType='image/gif';			break;		case 'tif':			$mimeType='image/tiff';			break;	}		}// Assigning MIME type if it was foundif($mimeType && $mimeType!=''){	header('Content-Type: '.$mimeType.';');} else {	// Octet stream is a general-use 'download' resource, browser will simply attempt to download the file as a result	header('Content-Type: application/octet-stream;');	header('Content-Disposition: attachment; filename='.$file);}// If cache is used, then proper headers will be sentif($noCache){	// Client is told to cache these results for set duration	header('Cache-Control: public,max-age=0,must-revalidate');	header('Expires: '.gmdate('D, d M Y H:i:s',$_SERVER['REQUEST_TIME']).' GMT');	header('Last-Modified: '.$lastModified.' GMT');} else {		// Client is told to cache these results for set duration	header('Cache-Control: public,max-age='.($lastModified+$config['resource-cache-timeout']-$_SERVER['REQUEST_TIME']).',must-revalidate');	header('Expires: '.gmdate('D, d M Y H:i:s',($lastModified+$config['resource-cache-timeout'])).' GMT');	header('Last-Modified: '.gmdate('D, d M Y H:i:s',$lastModified).' GMT');	}// If robots setting is not defined in cache, then it is turned offif(!isset($config['robots'])){	header('X-Robots-Tag: noindex,nocache,nofollow,noarchive,noimageindex,nosnippet',true);} else {	header('X-Robots-Tag: '.$config['robots'],true);}// Pragma header removed should the server happen to set it automaticallyheader_remove('Pragma');// Getting current output length$contentLength=filesize($folder.$file);// Content length is defined that can speed up website requests, letting client to determine file sizeheader('Content-Length: '.$contentLength);  // Returning the file to clientreadfile($folder.$file);// If Logger is defined then request is logged and can be used for performance review laterif(isset($logger)){	// Returning data size to logger	$logger->contentLength=$contentLength;		// Writing log entry	$logger->writeLog('file');	}?>