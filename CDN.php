<?php
/**
 * CDN - a Content Delivery Network class
 *
 * Rewrite URL's for use with a CDN (Content Delivery Network).
 * It also takes the base-tag (<base href="http://www.mydomain.com/directory/"/>
 * into account and only rewrites relative URL's.
 * Absolute URL's (e.g. http://www.domain.com/image.jpg) won't be rewritten.
 *
 * In order for this to work, you need a wildcard domain.
 * This means that every subdomain is redirected to the root domain, e.g.:
 * static1.domain.com is redirected to www.domain.com
 * You might want to consider only allowing rewritten filetypes to be accessible
 * through these static domains, to minimize the risk of hurting your SEO campaign.
 *
 * Usage:
 * You can use this class to rewrite URL's directly through an ob-handler:
 * [PHP]
 * 	$cdn = new CDN();
 *	ob_start(array(&$cdn, 'apply'));
 * [/PHP]
 *
 * Rewrite URL's from a news article:
 * [PHP]
 *	$cdn = new CDN();
 *	$article = $cdn->apply($article);
 * [/PHP]
 *
 * @name	CDN
 * @version	1.0
 * @since	2010-02-10
 * @author	Vincent Verbruggen
 */
class CDN
{
	/**
	 * The maximum number of CDN-domains.
	 * @var int
	 */
	protected $_staticDomains = 4;

	/**
	 * The filetypes to retrieve through static domains
	 * @var array
	 */
	protected $_filetypes = array(
			'jpg'	=> 'media',
			'gif'	=> 'media',
			'png'	=> 'media',
			'ico'	=> 'media',
			'flv'	=> 'media',
			'css'	=> 'css',
			'js'	=> 'js',
			'swf'	=> 'media'
		);

	/**
	 * The index of the current CDN-domain.
	 * @var int
	 */
	protected $_currentStaticDomain;

	/**
	 * The domain name
	 * @var string
	 */
	protected $_domain = '';

	/**
	 * The base path
	 * @var string
	 */
	protected $_basePath = '';


	/**
	 * Class constructor
	 */
	public function __construct ()
	{
	}


	/**
	 * Initialize the URL replacement to use CDN-domains.
	 * This function is best used as an ob-handler.
	 * @param string $html		The HTML-code to apply the CDN to
	 * @return string			Returns the HTML-code with the modified urls
	 */
	public function apply ($html)
	{
		$this->initBasePath($html);

		return preg_replace_callback(
					'/(src|href)=\"([^"]+\.('.join('|', array_keys($this->_filetypes)).'))\"/ismU',
					array(&$this, 'replaceStaticLinks'),
					$html
				);
	}


	/**
	 * Add new filetypes to the list of filetypes to rewrite to a static domain
	 *
	 * Usage:
	 * [PHP]
	 * 	$cdn->addFileType('jpg', 'media');
	 *	$cdn->addFileType(array('jpg', 'gif'), 'media');
	 *	$cdn->addFileType('css', 'stylesheets');
	 * [/PHP]
	 * @param array|string $filetypes	A list of filetypes or a single filetype as a string to include in the CDN
	 * @param string $domain			The CDN-domain for this filetype. E.g. passing 'stylesheets' for the css filetype would result in rewriting urls for CSS-stylesheets to http://stylesheets.domain.com/myStyleSheet.css
	 * @return CDN						Returns this instance
	 */
	public function addFileType ($filetypes, $domain=false)
	{
		$currentFiletypes = $this->_filetypes;

		foreach ((array) $filetypes as $filetype)
		{
			$filetype = urlencode(strtolower($filetype));
			$currentFiletypes[$filetype] = $domain;
		}

		$this->_filetypes = $currentFiletypes;
		return $this;
	}


	/**
	 * Remove one or more filetypes from the list of filetypes to rewrite
	 *
	 * Usage:
	 * [PHP]
	 * 	$cdn->removeFileType('jpg');
	 *	$cdn->removeFileType(array('jpg', 'gif'));
	 * [/PHP]
	 * @param array|string $filetypes	A list of filetypes or a single filetype as a string to include in the CDN
	 * @return CDN						Returns this instance
	 */
	public function removeFileType ($filetypes)
	{
		$currentFiletypes = $this->_filetypes;

		foreach ((array) $filetypes as $filetype)
		{
			$filetype = urlencode(strtolower($filetype));
			unset($currentFiletypes[$filetype]);
		}

		$this->_filetypes = $currentFiletypes;
		return $this;
	}


	/**
	 * Set the list of filetypes to rewrite to a static domain
	 * @param array|string $filetypes	Example: array('jpg'=>'media', 'gif'=>'media') or 'jpg'
	 * @return CDN						Returns this instance
	 */
	public function setFileTypes ($filetypes)
	{
		$this->_filetypes = array();
		
		foreach ($filetypes as $filetype=>$domain)
		{
			$this->addFileType($filetype, $domain);
		}

		return true;
	}


	/**
	 * Set the maximum number of static domains to use.
	 * @param int $int			The maximum number of domains to use
	 * @return CDN				Returns this instance
	 */
	public function setMaxNumberOfStaticDomains ($int)
	{
		$this->_staticDomains = (int) $int;
		return $this;
	}


	/**
	 * Rewrite urls to use a CDN-domain.
	 * This function is used as a preg_replace callback.
	 * @param array $match		The match returned by preg_replace_callback
	 * @return string			Returns the modified url
	 */
	public function replaceStaticLinks ($match)
	{
		$filePath = $match[2];

		// How does the file path relate to the base path?
		// Is the file path an url path?
		if (strtolower(substr($filePath, 0, 7))=='http://')
		{
			return $match[1] . '="' . $filePath . '"';
		}

		// Is the file path an absolute path?
		if (substr($filePath, 0, 1)=='/')
		{
			$filePath = $this->_domain . $filePath;
		}
		// The file path is relative to the base path.
		else
		{
			$filePath = trim(self::calculatePath($this->_basePath . $filePath), '\\/');
		}

		$filePath = preg_replace('/http\:\/\/(www\.)?/i', 'http://' . $this->getStaticDomain($filePath) . '.', $filePath);

		return $match[1] . '="' . $filePath . '"';
	}


	/**
	 * Get the number or name of the current CDN-domain.
	 * @param string $filePath		The path of the file to get the static domain for
	 * @return string				Returns the static domain for the given file path.
	 */
	protected function getStaticDomain ($filePath)
	{
		$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

		if (isset($this->_filetypes[$extension]) && $this->_filetypes[$extension] != false)
		{
			return $this->_filetypes[$extension];
		}

		if (++$this->_currentStaticDomain > $this->_staticDomains)
		{
			$this->_currentStaticDomain = 1;
		}

		return $this->_currentStaticDomain;
	}


	/**
	 * Parse the base path of the current page.
	 * @param string $html		The HTML-code to get the base path from
	 */
	protected function initBasePath ($html)
	{
		// The default base path is directory where the current page is located.
		$domain = 'http://' . $_SERVER['HTTP_HOST'];
		$this->_domain = $domain;
		$baseDirectory = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : '/';
		if (substr($baseDirectory, -1)!='/')
		{
			$baseDirectory = dirname($baseDirectory) . '/';
		}

		$basePath = $defaultBasePath = $domain . $baseDirectory;

		// Base-tag defined?
		if (preg_match('/<head>.*<base\s+(target=\"([^\"]*)\"\s+)?href=\"([^\"]*)\"(\s+target=\"([^\"]*)\")?\s*\/?>.*<\/head>/ism', $html, $matches))
		{
			$definedBasePath = $matches[3];

			// How does the base path relate to the default base path?
			// Is the defined base path an url path?
			if (strtolower(substr($definedBasePath, 0, 7))=='http://')
			{
				$basePath = $definedBasePath;
			}
			// Is the defined base path an absolute path?
			else if (substr($definedBasePath, 0, 1)=='/')
			{
				$basePath = $domain . $definedBasePath;
			}
			// The defined base path is relative to the current base path.
			else
			{
				$path = self::calculatePath( rtrim($baseDirectory, '\\/') . '/' . $definedBasePath);
				$basePath = $domain . $path;
			}
		}

		$this->_basePath = rtrim($basePath, '\\/') . '/';
	}


	/**
	 * Recalculate a path with symbolic links (e.g. ./hello/world/../foo/bar)
	 * @param string $path		The path to calculate symbolic links in
	 * @return string			Returns the calculated path
	 */
	public static function calculatePath ($path)
	{
		$pathParts = explode('/', trim($path, '\\/'));
		foreach ($pathParts as $partIndex => $pathPart)
		{
			// The current directory can be removed
			if ($pathPart=='.')
			{
				unset($pathParts[$partIndex]);
			}
			// Go to the parent directory
			else if ($pathPart=='..')
			{
				$removePartIndex = $partIndex - 1;

				while ($removePartIndex >= 0 && !isset($pathParts[$removePartIndex]))
				{
					$removePartIndex--;
				}

				if (isset($pathParts[$removePartIndex]))
				{
					unset($pathParts[$removePartIndex]);
				}
				unset($pathParts[$partIndex]);
			}
		}

		return '/' . join('/', $pathParts) . '/';
	}
}
?>