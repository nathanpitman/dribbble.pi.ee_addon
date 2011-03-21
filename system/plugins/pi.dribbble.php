<?php

$plugin_info = array(
	'pi_name'			=> 'Dribbble',
	'pi_version'		=> '0.3',
	'pi_author'			=> 'Nathan Pitman',
	'pi_author_url'		=> 'http://ninefour.co.uk/labs/',
	'pi_description'	=> 'Return shots by a specific player from Dribbble',
	'pi_usage'			=> dribbble::usage()
);


class dribbble {

	var $return_data;
	var $base_url 		= "http://api.dribbble.com/";
	var $cache_name		= 'dribbble_cache';
	var $cache_expired	= FALSE;
	var $refresh		= 15; // Period between cache refreshes, in minutes

	function player_shots()
	{
		
		global $FNS, $TMPL;

		$this->refresh = (($refresh = $TMPL->fetch_param('refresh')) === FALSE) ? $this->refresh : $refresh;
		$this->player_username = $TMPL->fetch_param('player_username');
		$this->per_page = $TMPL->fetch_param('per_page');
		
		if ($this->player_username) {
			
			if (!empty($this->per_page)) {
				$shots_call = $this->base_url."players/".$this->player_username."/shots/?per_page=".$this->per_page;
			} else {
				$shots_call = $this->base_url."players/".$this->player_username."/shots";
			}

			// caching
			if (($rawjson = $this->_check_cache($shots_call.$this->player_username)) === FALSE) {
				$this->cache_expired = TRUE;
				$TMPL->log_item("Fetching Dribbble shots remotely");
		    	$rawjson = file_get_contents($shots_call);
			}
			
			if ($rawjson == '') {
				$TMPL->log_item("Dribbble error: Unable to retrieve shots from dribbble.com");
				$this->return_data = '';
				return;
			} else {
				if ($this->cache_expired == TRUE) {
					$TMPL->log_item("Writing Dribbble shots to local cache");
					$this->_write_cache($rawjson, $shots_call.$this->player_username);			
				}
				$response = json_decode($rawjson);
			}
			
			//parse template
			$swap = array();
			$count = 1;
			$tagdata = $TMPL->tagdata;
			foreach ($TMPL->var_pair as $key=>$val) {
				if (ereg("^shots", $key)) {
					$s = '';
					preg_match("/".LD."$key".RD."(.*?)".LD.SLASH.'shots'.RD."/s", $TMPL->tagdata, $matches);
					foreach ($response->shots as $shot) {
						$temp = $matches[1];

						//counts
						$swap['count'] = $count;

						$swap['id'] = $shot->id;
						$swap['title'] = $shot->title;
						$swap['url'] = $shot->url;
						$swap['image_url'] = $shot->image_url;
						$swap['image_teaser_url'] = $shot->image_teaser_url;
						$swap['width'] = $shot->width;
						$swap['height'] = $shot->height;
						$swap['views_count'] = $shot->views_count;
						$swap['likes_count'] = $shot->likes_count;
						$swap['comments_count'] = $shot->comments_count;
						$swap['rebounds_count'] = $shot->rebounds_count;
						$swap['created_at'] = $shot->created_at;
	
						//manipulate
						$s .= $FNS->var_swap($temp, $swap);		  			
			  			$count++;
					}
					
					//do loop swap
					$tagdata = preg_replace("/".LD.'shots'.RD."(.*?)".LD.SLASH.'shots'.RD."/s", $s, $tagdata);
				}
			}
	
			//finish up
			$this->return_data = $tagdata;
			
			//return
			return $this->return_data;
			
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Check Cache
	 *
	 * Check for cached data
	 *
	 * @access	public
	 * @param	string
	 * @return	mixed - string if pulling from cache, FALSE if not
	 */

	function _check_cache($url)
	{	
		global $TMPL;
			
		/** ---------------------------------------
		/**  Check for cache directory
		/** ---------------------------------------*/
		
		$dir = PATH_CACHE.$this->cache_name.'/';
		
		if ( ! @is_dir($dir))
		{
			return FALSE;
		}

        $file = $dir.md5($url);
		
		if ( ! file_exists($file) OR ! ($fp = @fopen($file, 'rb')))
		{
			return FALSE;
		}
		       
		flock($fp, LOCK_SH);
                    
		$cache = @fread($fp, filesize($file));
                    
		flock($fp, LOCK_UN);
        
		fclose($fp);
        
		$eol = strpos($cache, "\n");
		
		$timestamp = substr($cache, 0, $eol);
		$cache = trim((substr($cache, $eol)));
		
		if (time() > ($timestamp + ($this->refresh * 60)))
		{
			return FALSE;
		}
		
		$TMPL->log_item("Dribbble shots retrieved from cache");
		
        return $cache;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Write Cache
	 *
	 * Write the cached data
	 *
	 * @access	public
	 * @param	string
	 * @return	void
	 */
	function _write_cache($data, $url)
	{
		/** ---------------------------------------
		/**  Check for cache directory
		/** ---------------------------------------*/
		
		$dir = PATH_CACHE.$this->cache_name.'/';

		if ( ! @is_dir($dir))
		{
			if ( ! @mkdir($dir, 0777))
			{
				return FALSE;
			}
			
			@chmod($dir, 0777);            
		}
		
		// add a timestamp to the top of the file
		$data = time()."\n".$data;
		
		/** ---------------------------------------
		/**  Write the cached data
		/** ---------------------------------------*/
		
		$file = $dir.md5($url);
	
		if ( ! $fp = @fopen($file, 'wb'))
		{
			return FALSE;
		}

		flock($fp, LOCK_EX);
		fwrite($fp, $data);
		flock($fp, LOCK_UN);
		fclose($fp);
        
		@chmod($file, 0777);		
	}


	function usage()
	{
	ob_start(); 
	?>
	The dribbble plug-in allows you to pull shots from any player into your page templates:

LATEST - {exp:dribbble:latest}

Parameters:
-------------------------------------
player_username= (required) the username of the player you want to display shots for.

per_page= the number of shots to return from the API.

refresh= the number of minutes to cache the results of the API call for.

Single variables:
-------------------------------------

{id} The id of the shot.
{title} The title of the shot.
{url} The URL of the shot detail page on Dribbble.
{image_url} The image URL of the shot on Dribbble (400x300).
{image_teaser_url} The teaser image URL of the shot on Dribbble (200x150).
{width} The width of the image (eg: 400).
{height} The height of the image (eg: 300).
{views_count} The number of views this shot has received (eg: 1693).
{likes_count} The number of likes this shot has received (eg: 15).
{comments_count} The number of comments this shot has received (eg: 4).
{rebounds_count} The number of rebounds this shot has received (eg: 0).
{created_at} The date and time at which the shot was posted (eg: 2010/05/21 16:34:42 -0400)

Example usage:
-------------------------------------

{exp:dribbble:player_shots player_username="nathanpitman" per_page="3" refresh="60"}
{shots}
<a href="{url}" title="{title}" class="item_{count}"><img src="{image_teaser_url} or {image_url}" id="{id}"></a>
{/shots}
{/exp:dribbble:player_shots}

CHANGE LOG
0.3 - Added support for view_count, likes_count, comments_count and rebounds_count.
0.2 - Added caching
0.1 - Initial release

	<?php
	$buffer = ob_get_contents();
		
	ob_end_clean(); 
	
	return $buffer;
	}


} // END CLASS