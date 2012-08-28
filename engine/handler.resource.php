<?php/* Wave FrameworkResource HandlerResource Handler is used to return files that are considered web resources that are not media. This includes things like JavaScript, CSS stylesheets, XML, HTML and other file formats (this is based on configuration). Resource Handler uses Wave Frameworks on-demand resource loader, which allows to combine multiple files to a single resource file or minify contents of the scripts. It also checks for files from overrides folder, which can be returned instead of the actual file.Author and support: Kristo Vaher - kristo@waher.netLicense: GNU Lesser General Public License Version 3*/// INITIALIZATION	// Stopping all requests that did not come from Index Gateway	if(!isset($resourceAddress)){		header('HTTP/1.1 403 Forbidden');		die();	}	// System returns proper content type based on file extension    if(isset($resourceExtension)){        switch($resourceExtension){            case 'js':                header('Content-Type: application/javascript;charset=utf-8;');                break;            case 'css':                header('Content-Type: text/css;charset=utf-8;');                break;            case 'xml':                header('Content-Type: text/xml;charset=utf-8;');                break;            case 'txt':                header('Content-Type: text/plain;charset=utf-8;');                break;            case 'csv':                header('Content-Type: text/csv;charset=utf-8;');                break;            case 'html':                header('Content-Type: text/html;charset=utf-8;');                break;            case 'htm':                header('Content-Type: text/html;charset=utf-8;');                break;            case 'rss':                header('Content-Type: application/rss+xml;charset=utf-8;');                break;            case 'vcard':                header('Content-Type: text/vcard;charset=utf-8;');                break;            default:                header('Content-Type: text/plain;charset=utf-8;');                break;        }    }	// Dynamic resource loading can be turned off in configuration	if(!isset($config['dynamic-resource-loading']) || $config['dynamic-resource-loading']==true){		// Comma separated filenames will mean that the result will be unified		$parameters=array_unique(explode('&',$resourceFile));	} else {		// If dynamic resource loading was turned off, then the entire 'first parameter' is considered to be the full string for parsing purposes		$parameters=array();		$parameters[0]=$resourceFile;	}	// Storing last modified time here	$lastModified=false;		// This flag stores whether cache was used	$cacheUsed=false;	// No cache flag	if(in_array('nocache',$parameters)){		$noCache=true;	} else {		$noCache=false;	}	// If cache is not defined in configuration file then pre-set is used	if(!isset($config['resource-cache-timeout'])){		$config['resource-cache-timeout']=31536000; // A year	}	// Web root is the subfolder on public site	$webRoot=str_replace('index.php','',$_SERVER['SCRIPT_NAME']);	// If minification is used for CSS and JS	$minify=false;	// Checking if the file might be loaded from overrides folder	$overridesFolder=false;	if(preg_match('/^'.str_replace('/','\/',$webRoot).'resources/',$_SERVER['REQUEST_URI'])){		// Solving possible overrides folder		$overridesFolder=str_replace($webRoot.'resources'.DIRECTORY_SEPARATOR,$webRoot.'overrides'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR,$resourceFolder);	}	// GETTING RESOURCE CONTENTS	// If file does not carry any additional parameters, then there is no need to dynamically generate it	if(!isset($parameters[1])){			// FILE EXISTENCE CHECK			// If file does not exist in regular nor overrides folder			if(file_exists($overridesFolder.$parameters[0])){							// Getting the last modified time from overrides folder				$lastModified=filemtime($overridesFolder.$parameters[0]);				// This is the loaded resource				$parameters[0]=$overridesFolder.$resourceFile;						} elseif(file_exists($resourceFolder.$parameters[0])){							// Getting the last modified time from the expected resource folder				$lastModified=filemtime($resourceFolder.$parameters[0]);				// This is the loaded resource				$parameters[0]=$resourceFolder.$resourceFile;							} else {							// Adding log entry					if(isset($logger)){					$logger->setCustomLogData(array('category'=>'resource','response-code'=>'404'));					$logger->writeLog();				}				// Returning 404 header				header('HTTP/1.1 404 Not Found');				die();						}					// NOT MODIFIED CHECK			// If the request timestamp is exactly the same, then we let the browser know of this			if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])>=$lastModified){							// Adding log entry					if(isset($logger)){					$logger->setCustomLogData(array('category'=>'resource','cache-used'=>true,'response-code'=>'304'));					$logger->writeLog();				}				// Cache headers (Last modified is never sent with 304 header)				header('Cache-Control: public,max-age='.$config['resource-cache-timeout']);				header('Expires: '.gmdate('D, d M Y H:i:s',($_SERVER['REQUEST_TIME']+$config['resource-cache-timeout'])).' GMT');				// Returning 304 header				header('HTTP/1.1 304 Not Modified');				die();							}	} else {		// Newest last-modified file is considered for the last modified time		foreach($parameters as $key=>$parameter){					// PARAMETER CONDITIONS				// Cache can be turned off and minification can be turned on with parameters.				// Full stop including parameters are also entirely ignored for parameter considerations				if($parameter!='nocache' && $parameter!='minify' && strpos($parameter,'.')!==false){										// MULTIPLE REQUESTED FILES											// Making sure that parent folders are not requested						$parameters[$key]=str_replace('..','',$parameter);												// Testing file name						$fileName=explode('.',$parameters[$key]);										// Overrides can be used if file with the same name is stored in same folder under /overrides/ folder						if($overridesFolder && file_exists($overridesFolder.$parameter) && in_array(array_pop($fileName),$config['resource-extensions'])){													// File was found and the filename will be replaced by file location for later processing							$parameters[$key]=$overridesFolder.$parameter;							// Last modified time of the file stored in overrides folder							$thisLastModified=filemtime($overridesFolder.$parameter);							// Only the newest last modified time will be used for output headers							if($lastModified==false || $lastModified<$thisLastModified){								$lastModified=$thisLastModified;							}												} elseif(file_exists($resourceFolder.$parameter) && in_array(array_pop($fileName),$config['resource-extensions'])){													// File was found and the filename will be replaced by file location for later processing							$parameters[$key]=$resourceFolder.$parameter;							// Last modified time of the file stored in overrides folder							$thisLastModified=filemtime($resourceFolder.$parameter);							// Only the newest last modified time will be used for output headers							if($lastModified==false || $lastModified<$thisLastModified){								$lastModified=$thisLastModified;							}												} elseif(!is_numeric($parameter)){													// Adding log entry								if(isset($logger)){								$logger->setCustomLogData(array('category'=>'resource','response-code'=>'404'));								$logger->writeLog();							}							// Returning 404 header							header('HTTP/1.1 404 Not Found');							die();													} else {													// This is probably just a version number							unset($parameters[$key]);													}									} elseif($parameter=='minify'){									// This will use minify for CSS and JS files					$minify=true;					// Unsetting the parameter as it will not be used later					unset($parameters[$key]);									} elseif($parameter=='nocache'){									// Unsetting the parameter as it will not be used later					unset($parameters[$key]);									} else {									// Adding log entry						if(isset($logger)){						$logger->setCustomLogData(array('category'=>'resource','response-code'=>'404'));						$logger->writeLog();					}					// Returning 404 header					header('HTTP/1.1 404 Not Found');					die();									}					}	}	// COMPRESSION SETTINGS	// This stores currently used compression mode	$compression='';	// If output compression is turned on then the content is compressed	if((isset($config['output-compression']) && $config['output-compression']!=false) && extension_loaded('Zlib')){		// Different compression options can be used		switch($config['output-compression']){			case 'deflate':				$compression='deflate';				break;			case 'gzip':				$compression='gzip';				break;		}	} elseif(extension_loaded('Zlib')){		// User agent accepted methods are checked when compression is not set in configuration itself		if(in_array('deflate',explode(',',$_SERVER['HTTP_ACCEPT_ENCODING']))){			// This tells proxies to store both compressed and uncompressed version			header('Vary: Accept-Encoding');			$compression='deflate';		} elseif(in_array('gzip',explode(',',$_SERVER['HTTP_ACCEPT_ENCODING']))){			// This tells proxies to store both compressed and uncompressed version			header('Vary: Accept-Encoding');			$compression='gzip';		}	}// CACHE AND NOT MODIFIED SETTINGS	// Solving cache folders and directory	$cacheFilename=md5($lastModified.$_SERVER['REQUEST_URI']).(($compression!='')?'_'.$compression:'').'.tmp';	$cacheDirectory=__ROOT__.'filesystem'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.substr($cacheFilename,0,2).DIRECTORY_SEPARATOR;	// If cache file exists then cache modified is considered that time	if(file_exists($cacheDirectory.$cacheFilename)){		$lastModified=filemtime($cacheDirectory.$cacheFilename);	} else {		// Otherwise it is server request time		$lastModified=$_SERVER['REQUEST_TIME'];	}		// If the request timestamp is exactly the same, then we let the browser know of this	if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])>=$lastModified){		// Adding log entry			if(isset($logger)){			$logger->setCustomLogData(array('category'=>'resource','cache-used'=>true,'response-code'=>'304'));			$logger->writeLog();		}		// Cache headers (Last modified is never sent with 304 header)		header('Cache-Control: public,max-age='.$config['resource-cache-timeout']);		header('Expires: '.gmdate('D, d M Y H:i:s',($_SERVER['REQUEST_TIME']+$config['resource-cache-timeout'])).' GMT');		// Returning 304 header		header('HTTP/1.1 304 Not Modified');		die();	}	// GENERATING RESOURCE	// If resource cannot be found from cache, it is generated	if($noCache || ($lastModified==$_SERVER['REQUEST_TIME'] || $lastModified<($_SERVER['REQUEST_TIME']-$config['resource-cache-timeout']))){			// LOADING CONTENTS			// Resource data is stored as a string			$data='';			// All requested files are appended			foreach($parameters as $parameter){				// Loading data into string.				$data.=file_get_contents($parameter)."\n";			}					// MINIFICATION AND COMPRESSION									// Minification of data for smaller filesize and less clutter.			if($minify){				// Including minification class				require(__ROOT__.'engine'.DIRECTORY_SEPARATOR.'class.www-minifier.php');				// Minification is based on the type of class				switch($resourceExtension){					case 'js':						$data=WWW_Minifier::minifyJS($data);						break;					case 'css':						$data=WWW_Minifier::minifyCSS($data);						break;					case 'xml':						$data=WWW_Minifier::minifyXML($data);						break;					case 'htm':						$data=WWW_Minifier::minifyHTML($data);						break;					case 'html':						$data=WWW_Minifier::minifyHTML($data);						break;					case 'rss':						$data=WWW_Minifier::minifyXML($data);						break;				}			}			// Data is compressed based on current compression settings			switch($compression){				case 'deflate':					$data=gzdeflate($data,9);					break;				case 'gzip':					$data=gzencode($data,9);					break;			}					// STORING IN CACHE					// Resource cache is cached in subdirectories, if directory does not exist then it is created			if(!is_dir($cacheDirectory)){				if(!mkdir($cacheDirectory,0755)){					trigger_error('Cannot create cache folder',E_USER_ERROR);				}			}						// Data is written to cache file			if(!file_put_contents($cacheDirectory.$cacheFilename,$data)){				trigger_error('Cannot create resource cache',E_USER_ERROR);			}				// Unsetting the variable due to memory reasons		unset($data);	} else {		// Logger is notified that cache was used		$cacheUsed=true;	}	// HEADERS	// If cache is used, then proper headers will be sent	if($noCache){		// User agent is told to cache these results for set duration		header('Cache-Control: public,max-age=0');		header('Expires: '.gmdate('D, d M Y H:i:s',$_SERVER['REQUEST_TIME']).' GMT');		header('Last-Modified: '.gmdate('D, d M Y H:i:s',$lastModified).' GMT');	} else {		// User agent is told to cache these results for set duration		header('Cache-Control: public,max-age='.$config['resource-cache-timeout']);		header('Expires: '.gmdate('D, d M Y H:i:s',($_SERVER['REQUEST_TIME']+$config['resource-cache-timeout'])).' GMT');		header('Last-Modified: '.gmdate('D, d M Y H:i:s',$lastModified).' GMT');	}	// Pragma header removed should the server happen to set it automatically	header_remove('Pragma');	// Proper compression header	if($compression!=''){		header('Content-Encoding: '.$compression);	}	// Robots header	if(isset($config['resource-robots'])){		// If resource-specific robots setting is defined		header('X-Robots-Tag: '.$config['resource-robots'],true);	} elseif(isset($config['robots'])){		// This sets general robots setting, if it is defined in configuration file		header('X-Robots-Tag: '.$config['robots'],true);	} else {		// If robots setting is not configured, system tells user agent not to cache the file		header('X-Robots-Tag: noindex,nocache,nofollow,noarchive,noimageindex,nosnippet',true);	}// OUTPUT		// Getting current output length	$contentLength=filesize($cacheDirectory.$cacheFilename);	// Content length is defined that can speed up website requests, letting user agent to determine file size	header('Content-Length: '.$contentLength);  	// Returning the file contents to user agent	readfile($cacheDirectory.$cacheFilename);	// File is deleted if cache was requested to be off	if($noCache){		unlink($cacheDirectory.$cacheFilename);	}	// WRITING TO LOG	// If Logger is defined then request is logged and can be used for performance review later	if(isset($logger)){		// Assigning custom log data to logger		$logger->setCustomLogData(array('cache-used'=>$cacheUsed,'category'=>'resource','content-length'=>$contentLength));		// Writing log entry		$logger->writeLog();	}?>