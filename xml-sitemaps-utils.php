<?php
/**
 * sitemap_xml
 *
 * @package XML Sitemaps
 **/

class sitemap_xml {
	var $file;
	var $fp;
	
	var $front_page_id;
	var $blog_page_id;
	var $posts_per_page;
	
	
	/**
	 * sitemap_xml()
	 *
	 * @return void
	 **/
	
	function sitemap_xml() {
		$this->file = WP_CONTENT_DIR . '/sitemaps/' . uniqid(rand());
		
		register_shutdown_function(array(&$this, 'close'));
	} # sitemap_xml()
	
	
	/**
	 * generate()
	 *
	 * @return bool $success
	 **/
	
	function generate() {
		if ( !$this->open() )
			return false;
		
		# private site
		if ( !intval(get_option('blog_public')) ) {
			$this->close();
			return true;
		}
		
		# static front page
		if ( get_option('show_on_front') == 'page' ) {
			$this->front_page_id = intval(get_option('page_on_front'));
			$this->blog_page_id = intval(get_option('page_for_posts'));
		} else {
			$this->front_page_id = false;
			$this->blog_page_id = false;
		}
		
		$this->posts_per_page = get_option('posts_per_page');
		$this->stats = (object) null;
		
		$this->home();
		$this->blog();
		
		add_filter('posts_where_request', array('xml_sitemaps', 'kill_query'));
		$this->pages();
		$this->posts();
		$this->categories();
		$this->tags();
		$this->archives();
		remove_filter('posts_where_request', array('xml_sitemaps', 'kill_query'));
		
		$this->close();
		return true;
	} # generate()
	
	
	/**
	 * home()
	 *
	 * @return void
	 **/
	
	function home() {
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
						DATEDIFF(CAST(NOW() AS DATE), MIN(CAST(posts.post_date AS DATE)))
						/ COUNT(DISTINCT CAST(revisions.post_date AS DATE))
					END as changefreq
			FROM	$wpdb->posts as posts
			LEFT JOIN	$wpdb->posts as revisions
			ON		revisions.post_parent = posts.ID
			AND		revisions.post_type = 'revision'
			AND		DATEDIFF(CAST(revisions.post_date AS DATE), CAST(posts.post_date AS DATE)) > 2
			AND		DATE_SUB(CAST(NOW() AS DATE), INTERVAL 1 YEAR) < CAST(revisions.post_date AS DATE)
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
	
	
	/**
	 * blog()
	 *
	 * @return void
	 **/
	
	function blog() {
		global $wpdb;
		global $wp_query;
		
		if ( !$this->blog_page_id ) {
			if ( $this->front_page_id ) # no blog page
				return;
			
			$loc = user_trailingslashit(get_option('home'));
		} else {
			$loc = get_permalink($this->blog_page_id);
		}
		
		$stats = $wpdb->get_row("
			SELECT	MAX(CAST(posts.post_modified AS DATE)) as lastmod,
					CASE COUNT(posts.ID)
					WHEN 0
					THEN
						0
					ELSE
						DATEDIFF(CAST(NOW() AS DATE), MIN(CAST(posts.post_date AS DATE)))
						/ COUNT(posts.ID)
					END as changefreq
			FROM	$wpdb->posts as posts
			WHERE	posts.post_type = 'post'
			AND		posts.post_status = 'publish'
			AND		posts.password = ''
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
		$query_vars = array();
		if ( $this->blog_page_id )
			$query_vars['page_id'] = $this->blog_page_id;
		$this->query($query_vars);
		
		if ( $wp_query->max_num_pages > 1 ) {
			$this->set_location($loc);
			
			for ( $i = 2; $i <= $wp_query->max_num_pages; $i++ ) {
				$this->write(
					get_pagenum_link($i),
					$stats->lastmod,
					$stats->changefreq,
					.4
					);
			}
		}
	} # blog()
	
	
	/**
	 * pages()
	 *
	 * @return void
	 **/
	
	function pages() {
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
		
		if ( class_exists('redirect_manager') )
			$exclude_sql .="
			UNION ALL
			SELECT	redirect.post_id
			FROM	$wpdb->postmeta as redirect
			WHERE	redirect.meta_key = '_redirect_url'
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
			AND		DATE_SUB(CAST(NOW() AS DATE), INTERVAL 1 YEAR) < CAST(revisions.post_date AS DATE)
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
		
		foreach ( $posts as $post ) {
			$this->write(
				get_permalink($post->ID),
				$post->lastmod,
				$post->changefreq,
				$post->priority
				);
		}
	} # pages()
	
	
	/**
	 * posts()
	 *
	 * @return void
	 **/
	
	function posts() {
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
		
		if ( class_exists('redirect_manager') )
			$exclude_sql .="
			UNION ALL
			SELECT	redirect.post_id
			FROM	$wpdb->postmeta as redirect
			WHERE	redirect.meta_key = '_redirect_url'
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
			AND		DATE_SUB(CAST(NOW() AS DATE), INTERVAL 1 YEAR) < CAST(revisions.post_date AS DATE)
			WHERE	posts.post_type = 'post'
			AND		posts.post_status = 'publish'
			AND		posts.post_password = ''
			AND		posts.ID NOT IN ( $exclude_sql )
			GROUP BY posts.ID
			ORDER BY posts.post_parent, posts.ID
			";
		
		#dump($sql);
		
		$posts = $wpdb->get_results($sql);
		
		update_post_cache($posts);
		
		foreach ( $posts as $post ) {
			$this->write(
				get_permalink($post->ID),
				$post->lastmod,
				$post->changefreq,
				.6
				);
		}
	} # posts()
	
	
	/**
	 * categories()
	 *
	 * @return void
	 **/
	
	function categories() {
		global $wpdb;
		global $wp_query;
		
		$terms = get_terms('category', array('hide_empty' => true, 'exclude' => defined('main_cat_id') ? main_cat_id : ''));
		
		if ( !$terms ) return; # no cats

		foreach ( $terms as $term ) {
			$sql = "
				SELECT	MAX(CAST(posts.post_date AS DATE)) as lastmod,
						CASE 
						WHEN ( COUNT(posts.ID) = 0 )
						THEN
							0
						ELSE
							DATEDIFF(CAST(NOW() AS DATE), MIN(CAST(posts.post_date AS DATE)))
							/ COUNT(posts.ID)
						END as changefreq,
						COUNT(posts.ID) as num_posts
				FROM	$wpdb->posts as posts
				INNER JOIN $wpdb->term_relationships as term_relationships
				ON		term_relationships.object_id = posts.ID
				INNER JOIN $wpdb->term_taxonomy as term_taxonomy
				ON		term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
				AND		term_taxonomy.taxonomy = 'category'
				AND		term_taxonomy.term_id = $term->term_id
				WHERE	posts.post_type = 'post'
				AND		posts.post_status = 'publish'
				AND		posts.post_password = ''
				";

			#dump($sql);

			$stats = $wpdb->get_row($sql);
			
			$loc = get_category_link($term->term_id);
			
			$this->write(
				$loc,
				$stats->lastmod,
				$stats->changefreq,
				.2
				);
			
			$query_vars = array('taxonomy' => 'category', 'term' => $term->slug);
			
			$this->query($query_vars);
			
			$posts_per_page = $wp_query->query_vars['posts_per_page'];
			
			if ( $posts_per_page > 0 && $stats->num_posts > $posts_per_page ) {
				$this->set_location($loc);

				for ( $i = 2; $i <= ceil($stats->num_posts / $posts_per_page); $i++ ) {
					$this->write(
						get_pagenum_link($i),
						$stats->lastmod,
						$stats->changefreq,
						.2
						);
				}
			}
		}
	} # categories()
	
	
	/**
	 * tags()
	 *
	 * @return void
	 **/
	
	function tags() {
		global $wpdb;
		global $wp_query;
		
		$terms = get_terms('post_tag', array('hide_empty' => true));
		
		if ( !$terms ) # no tags
			return;

		$query_vars = array('taxonomy' => 'post_tag', 'term' => $terms[0]->slug);
		
		$this->query($query_vars);

		$posts_per_page = $wp_query->query_vars['posts_per_page'];
		
		foreach ( $terms as $term ) {
			$sql = "
				SELECT	MAX(CAST(posts.post_modified AS DATE)) as lastmod,
						CASE 
						WHEN ( COUNT(posts.ID) = 0 )
						THEN
							0
						ELSE
							DATEDIFF(CAST(NOW() AS DATE), MIN(CAST(posts.post_date AS DATE)))
							/ COUNT(posts.ID)
						END as changefreq,
						COUNT(posts.ID) as num_posts
				FROM	$wpdb->posts as posts
				INNER JOIN $wpdb->term_relationships as term_relationships
				ON		term_relationships.object_id = posts.ID
				INNER JOIN $wpdb->term_taxonomy as term_taxonomy
				ON		term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id
				AND		term_taxonomy.taxonomy = 'post_tag'
				AND		term_taxonomy.term_id = $term->term_id
				WHERE	posts.post_type = 'post'
				AND		posts.post_status = 'publish'
				AND		posts.post_password = ''
				";

			#dump($sql);

			$stats = $wpdb->get_row($sql);
			
			$loc = get_tag_link($term->term_id);
			
			$this->write(
				$loc,
				$stats->lastmod,
				$stats->changefreq,
				.2
				);
			
			if ( $posts_per_page > 0 && $stats->num_posts > $posts_per_page ) {
				$this->set_location($loc);

				for ( $i = 2; $i <= ceil($stats->num_posts / $posts_per_page); $i++ ) {
					$this->write(
						get_pagenum_link($i),
						$stats->lastmod,
						$stats->changefreq,
						.2
						);
				}
			}
		}
	} # tags()
	
	
	/**
	 * archives()
	 *
	 * @return void
	 **/
	
	function archives() {
		global $wpdb;
		global $wp_query;
		
		foreach ( array('yearly', 'monthly', 'daily') as $archive_type ) {
			switch ( $archive_type ) {
			case 'yearly':
				$post_date = "CAST(DATE_FORMAT(posts.post_date, '%Y-00-00') AS DATE)";
				$post_date_stop = "DATEDIFF(CAST(NOW() AS DATE), $post_date) > 450";
				break;
			case 'monthly':
				$post_date = "CAST(DATE_FORMAT(posts.post_date, '%Y-%m-00') AS DATE)";
				$post_date_stop = "DATEDIFF(CAST(NOW() AS DATE), $post_date) > 45";
				break;
			case 'daily':
				$post_date = "CAST(posts.post_date AS DATE)";
				$post_date_stop = "DATEDIFF(CAST(NOW() AS DATE), $post_date) > 10";
				break;
			}
			
			$sql = "
				SELECT	$post_date as post_date,
						MAX(CAST(posts.post_modified AS DATE)) as lastmod,
						CASE
						WHEN ( COUNT(posts.ID) = 0 )
						THEN
							0
						WHEN $post_date_stop
						THEN
							0
						ELSE
							DATEDIFF(CAST(NOW() AS DATE), MIN(CAST(posts.post_date AS DATE)))
							/ COUNT(posts.ID)
						END as changefreq,
						COUNT(posts.ID) as num_posts
				FROM	$wpdb->posts as posts
				WHERE	posts.post_type = 'post'
				AND		posts.post_status = 'publish'
				AND		posts.post_password = ''
				GROUP BY $post_date
				ORDER BY $post_date
				";

			#dump($sql);
			
			$dates = $wpdb->get_results($sql);

			if ( !$dates ) # empty blog
				return;
			
			# fetch num posts per day
			$date = $dates[0];
			$day = split('-', $date->post_date);
			
			$query_vars = array();
			
			switch ( $archive_type ) {
			case 'daily':
				$query_vars['day'] = $day[2];
			case 'monthly':
				$query_vars['monthnum'] = $day[1];
			case 'yearly':
				$query_vars['year'] = $day[0];
			}
			
			$this->query($query_vars);
			
			$posts_per_page = $wp_query->query_vars['posts_per_page'];
			
			foreach ( $dates as $date ) {
				$day = split('-', $date->post_date);
				switch ( $archive_type ) {
				case 'yearly':
					$loc = get_year_link($day[0]);
					break;
				case 'monthly':
					$loc = get_month_link($day[0], $day[1]);
					break;
				case 'daily':
					$loc = get_day_link($day[0], $day[1], $day[2]);
					break;
				}
				
				$this->write(
					$loc,
					$date->lastmod,
					$date->changefreq,
					.1
					);
				
				if ( $posts_per_page > 0 && $date->num_posts > $posts_per_page ) {
					$this->set_location($loc);

					for ( $i = 2; $i <= ceil($date->num_posts / $posts_per_page); $i++ ) {
						$this->write(
							get_pagenum_link($i),
							$date->lastmod,
							$date->changefreq,
							.1
							);
					}
				}
			}
		}
	} # archives()
	
	
	/**
	 * open()
	 *
	 * @return bool $success
	 **/
	
	function open() {
		if ( isset($this->fp) )
			$this->close($this->fp);
		
		if ( !( $this->fp = fopen($this->file, 'w+') ) )
			return false;
		
		$o = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. ( xml_sitemaps_debug
				? '<!-- Debug: XML Sitemaps -->'
				: '<!-- Generator: XML Sitemaps -->'
				) . "\n"
			. '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		
		fwrite($this->fp, $o);
		
		return true;
	} # open()
	
	
	/**
	 * close()
	 *
	 * @return void
	 **/

	function close() {
		if ( isset($this->fp) && $this->fp ) {
			$o = '</urlset>';
			
			fwrite($this->fp, $o);
			
			fclose($this->fp);
			
			$this->fp = null;
			
			# compress
			if ( function_exists('gzencode') ) {
				$gzdata = gzencode(file_get_contents($this->file), 9);
				$fp = fopen($this->file . '.gz', 'w+');
				fwrite($fp, $gzdata);
				fclose($fp);
			}
			
			# move
			$file = WP_CONTENT_DIR . '/sitemaps/sitemap.xml';
			
			if ( !xml_sitemaps::rm($file)
				|| !xml_sitemaps::rm($file . '.gz')
				)
			{
				unlink($this->file);
				unlink($this->file . '.gz');
			} else {
				rename($this->file, $file);
				$stat = stat(dirname($file));
				$perms = $stat['mode'] & 0000666;
				@chmod($file, $perms);
				
				if ( function_exists('gzencode') ) {
					rename($this->file . '.gz', $file . '.gz');
					@chmod($file . '.gz', $perms);
				}
			}
		}
	} # close()
	
	
	/**
	 * write()
	 *
	 * @return void
	 **/
	
	function write($loc, $lastmod = null, $changefreq = null, $priority = null) {
		$o = '<url>' . "\n";
		
		foreach ( array('loc', 'lastmod', 'changefreq', 'priority') as $var ) {
			if ( isset($$var) ) {
				if ( $var == 'changefreq' && is_numeric($changefreq) ) {
					if ( !$changefreq || $changefreq > 91 ) {
						$changefreq = 'yearly';
					} elseif ( $changefreq > 14) {
						$changefreq = 'monthly';
					} elseif ( $changefreq > 3.5 ) {
						$changefreq = 'weekly';
					} else {
						$changefreq = 'daily';
					}
				}
				
				$o .= "<$var>" . htmlentities($$var, ENT_COMPAT, 'UTF-8') . "</$var>\n";
			}
		}
		
		$o .= '</url>' . "\n";
		
		fwrite($this->fp, $o);
	} # write()
	
	
	/**
	 * query()
	 *
	 * @param array $query_vars
	 * @return void
	 **/
	
	function query($query_vars = array()) {
		global $wp_query;
		
		# reset user
		if ( is_user_logged_in() )
			wp_set_current_user(0);
		
		# create new wp_query
		if ( isset($wp_query) )
			unset($wp_query);
		$wp_query = new WP_Query();
		
		$query_vars = apply_filters('request', (array) $query_vars);
		$query_string = '';
		foreach ( array_keys((array) $query_vars) as $wpvar) {
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
		
		$wp_query->parse_query($query_vars);
		$wp_query->get_posts();
	} # query()
	
	
	/**
	 * set_location()
	 *
	 * @param string $location
	 * @return void
	 **/
	
	function set_location($location) {
		$_SERVER['REQUEST_URI'] = parse_url($location);
		$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI']['path'];
	} # set_location()
} # sitemap_xml
?>