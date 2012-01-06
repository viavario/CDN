# CDN

This class rewrites URL's for use with a CDN (Content Delivery Network).  
It takes the base-tag (\<base href="http://www.mydomain.com/directory/" />) into account and only rewrites relative URL's.  
Absolute URL's (e.g. http://www.mydomain.com/image.jpg) won't be rewritten.


## REQUIREMENTS

-	PHP 5

## USAGE ##

	$cdn = new CDN();
	// Rewrite URL's on the entire page
	ob_start(array(&$cdn, 'apply'));
	
	// Rewrite URL's in, for example, an article
	$article = $cdn->apply($article);

### Filetypes and rewrites

There are two ways to rewrite URL's:

*	Define a subdomain for each filetype, e.g. media.mydomain.com for images and video, js.mydomain.com for JavaScript, ...
*	Use a random subdomain for each file, e.g. static1.mydomain.com, static2.mydomain.com, static3.mydomain.com, ...  
	In this case you'll need to add those subdomains in your control panel or use a wildcard subdomain.
	The default number of static domains is 4. You can change that number by using the method __setMaxNumberOfStaticDomains__

The URL's of the following filetypes are rewritten to where mydomain.com is your domain name:

*	jpg -> media.mydomain.com
*	gif -> media.mydomain.com
*	png -> media.mydomain.com
*	ico -> media.mydomain.com
*	flv -> media.mydomain.com
*	swf -> media.mydomain.com
*	css -> css.mydomain.com
*	js  -> js.mydomain.com

Example of how to set, add and remove filetypes to be rewritten:

	$cdn->addFileType('mp4', 'media'); // rewrite the URL's of mp4 files to media.mydomain.com
	$cdn->removeFileType('swf'); // don't rewrite the URL's of swf files to media.mydomain.com anymore
	$cdn->removeFileType(array('swf', 'flv'); // don't rewrite the URL's of swf and flv files to media.mydomain.com anymore
	
	// Remove all the existing filetypes to be rewritten and set a new array of filetypes
	$cdn->setFileTypes(array(
		'mp4'	=> 'media',	// rewrite the URL's of mp4 files to media.mydomain.com
		'mp3'	=> 'media', // rewrite the URL's of mp3 files to media.mydomain.com
		'js'	=> false, // rewrite the URL's of js files to static[0-9].mydomain.com
		'css'	=> false // rewrite the URL's of js files to static[0-9].mydomain.com
	));

## CONTACT
http://www.tse-webdesign.be/  
info@tse-webdesign.be (bugs, questions or comments)