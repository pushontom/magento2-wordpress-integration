<?php
/**
 * 
**/
namespace FishPig\WordPress\Model\App;

/**
 * Generate all WordPress URLs 
**/
class Url
{
	/**
	 * @var 
	**/
	protected $_config = null;
	
	/**
	 * @var 
	**/
	protected $_storeManager = null;

	/**
	 * @var array
	**/
	protected $_cache = array();
	
	/**
	 * Constructor
	**/
	public function __construct(\FishPig\WordPress\Model\Config $config, \Magento\Store\Model\StoreManagerInterface $storeManager)
	{
		$this->_config = $config;
		$this->_storeManager = $storeManager;
	}

	/**
	 * Get the Magento base URL
	 *
	 * @return string
	 **/
	public function getMagentoUrl()
	{
		if (!isset($this->_cache[__METHOD__])) {
			$this->_cache[__METHOD__] = rtrim(
				$this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK), 
				'/'
			);
		}
		
		return $this->_cache[__METHOD__];
	}
	
	/**
	 * Get the blog route
	 *
	 * @return string
	 **/
	public function getBlogRoute()
	{
		if (!isset($this->_cache[__METHOD__])) {
			$this->_cache[__METHOD__] = trim(substr($this->getHomeUrl(), strlen($this->getMagentoUrl())), '/');
		}

		return $this->_cache[__METHOD__];
	}

	/**
	 * Generate a WordPress frontend URL
	 *
	 * @param string $uri = ''
	 * @return string
	 **/
	public function getUrl($uri = '')
	{
		return $this->getHomeUrl()	. '/' . $uri;
	}
	
	/**
	 * Get the Site URL
	 *
	 * @return string
	 **/
	public function getSiteurl()
	{
		return $this->_config->getOption('siteurl');
	}
	
	/**
	 * Get the Home URL
	 *
	 * @return string
	 **/
	public function getHomeUrl()
	{
		return $this->_config->getOption('home');
	}

	/**
	 * Retrieve the upload URL
	 *
	 * @return string
	 */
	public function getFileUploadUrl()
	{
		$url = $this->_config->getOption('fileupload_url');
		
		if (!$url) {
			foreach(array('upload_url_path', 'upload_path') as $config) {
				if ($value = $this->_config->getOption($config)) {
					if (strpos($value, 'http') === false) {
						if (substr($value, 0, 1) !== '/') {
							$url = $this->getSiteurl() . $value;
						}
					}
					else {
						$url = $value;
					}

					break;
				}
			}
			
			if (!$url) {
				$url = $this->getBaseFileUploadUrl();
			}
		}
		
		return rtrim($url, '/') . '/';
	}
	
	/**
	 *
	 *
	 * @return 
	**/
	public function getBaseFileUploadUrl()
	{
		return $this->getSiteUrl() . '/wp-content/uploads/';
	}

	/**
	 *
	 *
	 * @return 
	**/
	public function getUrlAlias(\Magento\Framework\App\RequestInterface $request)
	{
		$pathInfo = strtolower(trim($request->getPathInfo(), '/'));	
		$blogRoute = $this->getBlogRoute();
		
		if ($blogRoute && strpos($pathInfo, $blogRoute) !== 0) {
			return false;
		}

		if (trim(substr($pathInfo, strlen($blogRoute)), '/') === '') {
			return $pathInfo;
		}		
		
		$pathInfo = explode('/', $pathInfo);
		
		// Clean off pager and feed parts
		if (($key = array_search('page', $pathInfo)) !== false) {
			if (isset($pathInfo[($key+1)]) && preg_match("/[0-9]{1,}/", $pathInfo[($key+1)])) {
				$request->setParam('page', $pathInfo[($key+1)]);
				unset($pathInfo[($key+1)]);
				unset($pathInfo[$key]);
				
				$pathInfo = array_values($pathInfo);
			}
		}
		
		/*
		// Clean off feed and trackback variable
		if (($key = array_search('feed', $pathInfo)) !== false) {
			unset($pathInfo[$key]);
			
			if (isset($pathInfo[$key+1])) {
				unset($pathInfo[$key+1]);
			}

			$request->setParam('feed', 'rss2');
			$request->setParam('feed_type', 'rss2');
		}
		*/
		
		// Remove comments pager variable
		foreach($pathInfo as $i => $part) {
			$results = array();
			if (preg_match("/" . sprintf('^comment-page-%s$', '([0-9]{1,})') . "/", $part, $results)) {
				if (isset($results[1])) {
					unset($pathInfo[$i]);
				}
			}
		}
		
		if (count($pathInfo) == 1 && preg_match("/^[0-9]{1,8}$/", $pathInfo[0])) {
			$request->setParam('p', $pathInfo[0]);
			
			array_shift($pathInfo);
		}

		$uri = urldecode(implode('/', $pathInfo));

		return $uri;
	}
	
	/**
	 * Retrieve the blog URI
	 * This is the whole URI after blog route
	 *
	 * @return string
	 */
	public function getRouterRequestUri(\Magento\Framework\App\RequestInterface $request)
	{
		if (($alias = $this->getUrlAlias($request)) !== false) {
			if ($this->getBlogRoute()) {
				return strpos($alias . '/', $this->getBlogRoute() .'/') === 0 ? ltrim(substr($alias, strlen($this->getBlogRoute())), '/') : false;
			}
			
			return $alias;
		}
		
		return false;
	}
}
