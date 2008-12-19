<?php
class sitemap_xml
{
	var $file;
	var $fp;
	
	var $front_page_id = false;
	var $blog_page_id = false;
	var $posts_per_page;
	
	
	#
	# sitemap_xml()
	#
	
	function sitemap_xml()
	{
		$this->file = WP_CONTENT_DIR . '/sitemaps/sitemap.xml';
		
		register_shutdown_function(array(&$this, 'close'));
		
		# static front page
		if ( get_option('show_on_front') == 'page' )
		{
			$this->front_page_id = intval(get_option('page_on_front'));
			$this->blog_page_id = intval(get_option('page_for_posts'));
		}
		
		# default posts per page
		$this->posts_per_page = get_option('posts_per_page');
	} # sitemap_xml()
	
	
	#
	# generate()
	#
	
	function generate()
	{
		if ( !$this->open() ) return;
		
		# private site
		if ( !get_option('blog_public') )
		{
			return $this->close();
		}
		
		$this->home();
		$this->blog();
		#$this->pages();
		#$this->posts();
		#$this->archives();
		
		return $this->close();
	} # generate()
	
	
	#
	# home()
	#
	
	function home()
	{
		if ( !$this->front_page_id ) return;
		
		global $wpdb;
		
		$loc = user_trailingslashit(get_option('home'));
		$priority = .8;
		
		$stats = $wpdb->get_row("
			SELECT	CAST(posts.post_modified AS DATE) as lastmod,
					CASE COUNT(DISTINCT CAST(revisions.post_date AS DATE))
					WHEN 0
					THEN
						DATEDIFF(CAST(NOW() AS DATE), CAST(posts.post_date AS DATE))
					ELSE
						DATEDIFF(CAST(NOW() AS DATE), CAST(posts.post_date AS DATE))
						/ COUNT(DISTINCT CAST(revisions.post_date AS DATE))
					END as changefreq
			FROM	$wpdb->posts as posts
			LEFT JOIN	$wpdb->posts as revisions
			ON		revisions.post_parent = posts.ID
			AND		revisions.post_type = 'revision'
			WHERE	posts.ID = $this->front_page_id
			GROUP BY posts.ID
			");
		
		$this->write(
			$loc,
			$stats->lastmod,
			$stats->changefreq,
			$priority
			);
	} # home()
	
	
	#
	# blog()
	#
	
	function blog()
	{
		global $wpdb;
		
		#$wp_query = new WP_Query;
		
		if ( !$this->blog_page_id )
		{
			if ( $this->front_page_id ) return; # no blog page
			
			$loc = user_trailingslashit(get_option('home'));
			$priority = .8;
		}
		else
		{
			$post = get_post($this->blog_page_id);
			$loc = get_permalink($post->ID);
			$priority = .8;
		}
		
		$stats = $wpdb->get_row("
			SELECT	MAX(CAST(posts.post_modified AS DATE)) as lastmod,
					CASE COUNT(DISTINCT CAST(posts.post_date AS DATE))
					WHEN 0
					THEN
						0
					ELSE
						DATEDIFF(CAST(NOW() AS DATE), CAST(posts.post_date AS DATE))
						/ COUNT(DISTINCT CAST(posts.post_date AS DATE))
					END as changefreq
			FROM	$wpdb->posts as posts
			WHERE	posts.post_type = 'post'
			AND		posts.post_status = 'publish'
			");
		
		$this->write(
			$loc,
			$stats->lastmod,
			$stats->changefreq,
			$priority
			);
		
		# run things through wp a bit
		
		$this->do_query($this->blog_page_id ? array('page_id' => $this->blog_page_id) : null, $loc);
		
		global $wp_query;
		
		if ( $wp_query->max_num_pages > 1 )
		{
			$priority = .5;
			
			for ( $i = 2; $i <= $wp_query->max_num_pages; $i++ )
			{
				$this->write(
					get_pagenum_link($i),
					$stats->lastmod,
					$stats->changefreq,
					$priority
					);
			}
		}
	} # blog()
	
	
	#
	# open()
	#
	
	function open()
	{
		if ( isset($this->fp) ) fclose($this->fp);
		
		if ( !( $this->fp = fopen($this->file, 'w+') ) ) return false;
		
		# get exclusive lock
		$locked = false;
		$now = microtime();
		
		do
		{
			$locked = flock($this->fp, LOCK_EX);
			if ( !$locked ) usleep(round(rand(0, 100)*1000));
		} while ( !$locked && ( ( microtime() - $now ) < 1000 ) );
		
		if ( !$locked )
		{
			fclose($this->fp);
			
			return false;
		}
		
		$o = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. ( xml_sitemaps_debug
				? '<!-- Generator: XML Sitemaps in Debug Mode -->'
				: '<!-- Generator: XML Sitemaps -->'
				) . "\n"
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		
		fwrite($this->fp, $o);
		
		return true;
	} # open()
	
	
	#
	# close()
	#
	
	function close()
	{
		if ( isset($this->fp) )
		{
			$o = '</urlset>';
			
			fwrite($this->fp, $o);
			
			fclose($this->fp);
			
			$this->fp = null;
		}
	} # close()
	
	
	#
	# write()
	#
	
	function write($loc, $lastmod = null, $changefreq = null, $priority = null)
	{
		$o = '<url>' . "\n";
		
		foreach ( array('loc', 'lastmod', 'changefreq', 'priority') as $var )
		{
			if ( isset($$var) )
			{
				if ( $var == 'changefreq' && is_numeric($changefreq) )
				{
					if ( $changefreq > 91 )
					{
						$changefreq = 'yearly';
					}
					elseif ( $changefreq > 14)
					{
						$changefreq = 'monthly';
					}
					elseif ( $changefreq > 3.5 )
					{
						$changefreq = 'weekly';
					}
					else
					{
						$changefreq = 'daily';
					}
				}
				
				$o .= "<$var>" . htmlentities($$var, ENT_COMPAT, 'UTF-8') . "</$var>\n";
			}
		}
		
		$o .= '</url>' . "\n";
		
		fwrite($this->fp, $o);
	} # write()
	
	
	#
	# do_query()
	#
	
	function do_query($query_vars = array(), $loc)
	{
		global $wp_the_query;
		global $wp_query;
		
		# reset user
		if ( is_user_logged_in() )
		{
			wp_set_current_user(0);
		}
		
		# create new wp_query
		$wp_query = new WP_Query();
		
		$query_vars = apply_filters('request', $query_vars);
		$query_string = '';
		foreach ( (array) array_keys($query_vars) as $wpvar) {
			if ( '' != $query_vars[$wpvar] ) {
				$query_string .= (strlen($query_string) < 1) ? '' : '&';
				if ( !is_scalar($query_vars[$wpvar]) ) // Discard non-scalars.
					continue;
				$query_string .= $wpvar . '=' . rawurlencode($query_vars[$wpvar]);
			}
		}

		if ( has_filter('query_string') ) {
			$query_string = apply_filters('query_string', $query_string);
			parse_str($query_string, $query_vars);
		}
		
		# only keep fields involved in permalinks
		add_filter('posts_fields_request', array('xml_sitemaps', 'kill_query_fields'));
		
		$wp_query->query($query_vars);
		
		$_SERVER['REQUEST_URI'] = parse_url($loc);
		$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI']['path'];
	} # do_query()
} # sitemap_xml
?>