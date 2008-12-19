<?php
class sitemap_xml
{
	var $file;
	var $fp;
	
	var $front_page_id;
	var $blog_page_id;
	var $posts_per_page;
	
	
	#
	# sitemap_xml()
	#
	
	function sitemap_xml()
	{
		$this->file = WP_CONTENT_DIR . '/sitemaps/sitemap.xml';
		
		register_shutdown_function(array(&$this, 'close'));
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
		
		# static front page
		if ( get_option('show_on_front') == 'page' )
		{
			$this->front_page_id = intval(get_option('page_on_front'));
			$this->blog_page_id = intval(get_option('page_for_posts'));
		}
		else
		{
			$this->front_page_id = false;
			$this->blog_page_id = false;
		}
		
		$this->posts_per_page = get_option('posts_per_page');
		$this->stats = (object) null;
		
		$this->home();
		$this->blog();
		$this->pages();
		$this->attachments();
		$this->posts();
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
		
		$stats = $wpdb->get_row("
			SELECT	CAST(posts.post_modified AS DATE) as lastmod,
					CASE COUNT(DISTINCT CAST(revisions.post_date AS DATE))
					WHEN 0
					THEN
						0
					ELSE
						DATEDIFF(CAST(NOW() AS DATE), CAST(posts.post_date AS DATE))
						/ COUNT(DISTINCT CAST(revisions.post_date AS DATE))
					END as changefreq
			FROM	$wpdb->posts as posts
			LEFT JOIN	$wpdb->posts as revisions
			ON		revisions.post_parent = posts.ID
			AND		revisions.post_type = 'revision'
			AND		DATEDIFF(CAST(revisions.post_date AS DATE), CAST(posts.post_date AS DATE)) > 2
			WHERE	posts.ID = $this->front_page_id
			GROUP BY posts.ID
			");
		
		$this->write(
			$loc,
			$stats->lastmod,
			$stats->changefreq,
			.8
			);
	} # home()
	
	
	#
	# blog()
	#
	
	function blog()
	{
		global $wpdb;
		
		if ( !$this->blog_page_id )
		{
			if ( $this->front_page_id ) return; # no blog page
			
			$loc = user_trailingslashit(get_option('home'));
		}
		else
		{
			$loc = get_permalink($this->blog_page_id);
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
		
		# this will be re-used in archives
		$this->stats = $stats;
		
		$this->write(
			$loc,
			$stats->lastmod,
			$stats->changefreq,
			.8
			);
		
		# run things through wp a bit
		
		$this->query($this->blog_page_id ? array('page_id' => $this->blog_page_id) : null, $loc);
		
		global $wp_query;
		
		if ( $wp_query->max_num_pages > 1 )
		{
			for ( $i = 2; $i <= $wp_query->max_num_pages; $i++ )
			{
				$this->write(
					get_pagenum_link($i),
					$stats->lastmod,
					$stats->changefreq,
					.4
					);
			}
		}
	} # blog()
	
	
	#
	# pages()
	#
	
	function pages()
	{
		global $wpdb;
		
		$exclude_sql = "
			SELECT	exclude.post_id
			FROM	$wpdb->postmeta as exclude
			LEFT JOIN $wpdb->postmeta as exception
			ON		exception.post_id = exclude.post_id
			AND		exception.meta_key = '_widgets_exception'
			WHERE	exclude.meta_key = '_widgets_exclude'
			AND		exception.post_id IS NULL
			";
		
		$sql = "
			SELECT	posts.ID,
					posts.post_author,
					posts.post_name,
					posts.post_type,
					posts.post_status,
					posts.post_parent,
					posts.post_date,
					posts.post_modified,
					CAST(posts.post_modified AS DATE) as lastmod,
					CASE COUNT(DISTINCT CAST(revisions.post_date AS DATE))
					WHEN 0
					THEN
						0
					ELSE
						DATEDIFF(CAST(NOW() AS DATE), CAST(posts.post_date AS DATE))
						/ COUNT(DISTINCT CAST(revisions.post_date AS DATE))
					END as changefreq,
					CASE
					WHEN posts.post_parent = 0 OR COALESCE(COUNT(DISTINCT children.ID), 0) <> 0
					THEN
						.4
					ELSE
						.8
					END as priority
			FROM	$wpdb->posts as posts
			LEFT JOIN $wpdb->posts as revisions
			ON		revisions.post_parent = posts.ID
			AND		revisions.post_type = 'revision'
			AND		DATEDIFF(CAST(revisions.post_date AS DATE), CAST(posts.post_date AS DATE)) > 2
			LEFT JOIN $wpdb->posts as children
			ON		children.post_parent = posts.ID
			AND		children.post_type = 'page'
			AND		children.post_status = 'publish'
			WHERE	posts.post_type = 'page'
			AND		posts.post_status = 'publish'
			AND		posts.post_password = ''
			AND		posts.ID NOT IN ( $exclude_sql )"
			. ( $this->front_page_id
				? "
			AND		posts.ID <> $this->front_page_id
				"
				: ''
				)
			. ( $this->blog_page_id
				? "
			AND		posts.ID <> $this->blog_page_id
				"
				: ''
				) . "
			GROUP BY posts.ID
			ORDER BY posts.post_parent, posts.ID
			";
		#dump($sql);
		$posts = $wpdb->get_results($sql);
		
		update_post_cache($posts);
		
		foreach ( $posts as $post )
		{
			$this->write(
				get_permalink($post->ID),
				$post->lastmod,
				$post->changefreq,
				$post->priority
				);
		}
	} # pages()
	
	
	#
	# attachments()
	#
	
	function attachments()
	{
		global $wpdb;
		
		$exclude_sql = "
			SELECT	exclude.post_id
			FROM	$wpdb->postmeta as exclude
			LEFT JOIN $wpdb->postmeta as exception
			ON		exception.post_id = exclude.post_id
			AND		exception.meta_key = '_widgets_exception'
			WHERE	exclude.meta_key = '_widgets_exclude'
			AND		exception.post_id IS NULL
			";
		
		$sql = "
			SELECT	posts.ID,
					posts.post_author,
					posts.post_name,
					posts.post_type,
					posts.post_status,
					posts.post_parent,
					posts.post_date,
					posts.post_modified,
					CAST(posts.post_modified AS DATE) as lastmod,
					0 as changefreq
			FROM	$wpdb->posts as posts
			JOIN	$wpdb->posts as parents
			ON		parents.ID = posts.post_parent
			AND		parents.post_type IN ( 'post', 'page' )
			AND		parents.post_status = 'publish'
			AND		parents.post_password = ''
			AND		parents.ID NOT IN ( $exclude_sql )
			WHERE	posts.post_type = 'attachment'
			ORDER BY posts.post_parent, posts.ID
			";
		#dump($sql);
		$posts = $wpdb->get_results($sql);
		
		update_post_cache($posts);
		
		foreach ( $posts as $post )
		{
			$this->write(
				get_permalink($post->ID),
				$post->lastmod,
				$post->changefreq,
				.3
				);
		}
	} # attachments()
	
	
	#
	# posts()
	#
	
	function posts()
	{
		global $wpdb;
		
		$exclude_sql = "
			SELECT	exclude.post_id
			FROM	$wpdb->postmeta as exclude
			LEFT JOIN $wpdb->postmeta as exception
			ON		exception.post_id = exclude.post_id
			AND		exception.meta_key = '_widgets_exception'
			WHERE	exclude.meta_key = '_widgets_exclude'
			AND		exception.post_id IS NULL
			";
		
		$sql = "
			SELECT	posts.ID,
					posts.post_author,
					posts.post_name,
					posts.post_type,
					posts.post_status,
					posts.post_parent,
					posts.post_date,
					posts.post_modified,
					CAST(posts.post_modified AS DATE) as lastmod,
					CASE COUNT(DISTINCT CAST(revisions.post_date AS DATE))
					WHEN 0
					THEN
						0
					ELSE
						DATEDIFF(CAST(NOW() AS DATE), CAST(posts.post_date AS DATE))
						/ COUNT(DISTINCT CAST(revisions.post_date AS DATE))
					END as changefreq
			FROM	$wpdb->posts as posts
			LEFT JOIN $wpdb->posts as revisions
			ON		revisions.post_parent = posts.ID
			AND		revisions.post_type = 'revision'
			AND		DATEDIFF(CAST(revisions.post_date AS DATE), CAST(posts.post_date AS DATE)) > 2
			WHERE	posts.post_type = 'post'
			AND		posts.post_status = 'publish'
			AND		posts.post_password = ''
			AND		posts.ID NOT IN ( $exclude_sql )"
			. ( $this->front_page_id
				? "
			AND		posts.ID <> $this->front_page_id
				"
				: ''
				)
			. ( $this->blog_page_id
				? "
			AND		posts.ID <> $this->blog_page_id
				"
				: ''
				) . "
			GROUP BY posts.ID
			ORDER BY posts.post_parent, posts.ID
			";
		#dump($sql);
		$posts = $wpdb->get_results($sql);
		
		update_post_cache($posts);
		
		foreach ( $posts as $post )
		{
			$this->write(
				get_permalink($post->ID),
				$post->lastmod,
				$post->changefreq,
				.6
				);
		}
	} # posts()
	
	
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
		
		do {
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
				? '<!-- Debug: XML Sitemaps -->'
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
					if ( !$changefreq )
					{
						$changefreq = 'never';
					}
					elseif ( $changefreq > 91 )
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
	# query()
	#
	
	function query($query_vars = array(), $loc = null)
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
		
		$wp_query->query($query_vars);
		
		if ( isset($loc) )
		{
			$_SERVER['REQUEST_URI'] = parse_url($loc);
			$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI']['path'];
		}
	} # query()
} # sitemap_xml
?>