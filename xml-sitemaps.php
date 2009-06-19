<?php
/*
Plugin Name: XML Sitemaps
Plugin URI: http://www.semiologic.com/software/xml-sitemaps/
Description: Automatically generates XML Sitemaps for your site and notifies search engines when they're updated.
Version: 1.2.1 RC
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: xml-sitemaps-info
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts (http://www.mesoconcepts.com), and is distributed under the terms of the GPL license, v.2.

http://www.opensource.org/licenses/gpl-2.0.php
**/


load_plugin_textdomain('xml-sitemaps', null, dirname(__FILE__) . '/lang');


/**
 * xml_sitemaps
 *
 * @package XML Sitemaps
 **/

if ( !defined('xml_sitemaps_debug') )
	define('xml_sitemaps_debug', false);

register_activation_hook(__FILE__, array('xml_sitemaps', 'activate'));
register_deactivation_hook(__FILE__, array('xml_sitemaps', 'deactivate'));

if ( intval(get_option('xml_sitemaps')) ) {
	if ( !xml_sitemaps_debug )
		add_filter('mod_rewrite_rules', array('xml_sitemaps', 'rewrite_rules'));
	
	add_action('template_redirect', array('xml_sitemaps', 'template_redirect'));
	add_action('save_post', array('xml_sitemaps', 'save_post'));
	add_action('xml_sitemaps_ping', array('xml_sitemaps', 'ping'));
	
	add_action('do_robots', array('xml_sitemaps', 'do_robots'));
} else {
	add_action('admin_notices', array('xml_sitemaps', 'inactive_notice'));
}

add_action('update_option_permalink_structure', array('xml_sitemaps', 'reactivate'));
add_action('update_option_blog_public', array('xml_sitemaps', 'reactivate'));

class xml_sitemaps {
	/**
	 * do_robots()
	 *
	 * @return void
	 **/

	function do_robots() {
		if ( !intval(get_option('blog_public')) )
			return;
		
		$file = WP_CONTENT_DIR . '/sitemaps/sitemap.xml';
		
		if ( !file_exists($file) && !xml_sitemaps::generate() )
			return;
		
		if ( file_exists($file . '.gz') )
			$file = trailingslashit(get_option('home')) . 'sitemap.xml.gz';
		elseif ( file_exists($file) )
			$file = trailingslashit(get_option('home')) . 'sitemap.xml';
		else
			return;
		
		echo "\n\n" . 'Sitemap: ' . $file;
	} # do_robots()
	
	
	/**
	 * ping()
	 *
	 * @return void
	 **/

	function ping() {
		wp_clear_scheduled_hook('xml_sitemaps_ping');
		
		if ( $_SERVER['HTTP_HOST'] == 'localhost'
			|| !intval(get_option('blog_public'))
			|| !intval(get_option('xml_sitemaps_ping'))
			) return;
		
		$file = WP_CONTENT_DIR . '/sitemaps/sitemap.xml';
		
		if ( !xml_sitemap::generate() )
			return;
		
		if ( file_exists($file . '.gz') )
			$file = trailingslashit(get_option('home')) . 'sitemap.xml.gz';
		elseif ( file_exists($file) )
			$file = trailingslashit(get_option('home')) . 'sitemap.xml';
		else
			return;
		
		$file = urlencode($file);
		
		foreach ( array(
			'http://www.google.com/webmasters/sitemaps/ping?sitemap=',
			'http://webmaster.live.com/ping.aspx?siteMap=',
			'http://submissions.ask.com/ping?sitemap=',
			'http://search.yahooapis.com/SiteExplorerService/V1/updateNotification?appid=d8WFhrTV34HVHSrwjAUse9N43fR.S9DjtO5EvL3.xii4kc9tXFZc8yWf43k2XkHWMPs-&url='
			) as $service )
			wp_remote_fopen($file);
	} # ping()
	
	
	/**
	 * save_post()
	 *
	 * @return void
	 **/

	function save_post($post_ID) {
		$post = get_post($post_ID);
		
		# ignore revisions
		if ( $post->post_type == 'revision' )
			return;
		
		# ignore non-published data and password protected data
		if ( $post->post_status != 'publish' || $post->post_password != '' )
			return;
		
		xml_sitemaps::rm(WP_CONTENT_DIR . '/sitemaps');
		
		if ( !wp_next_scheduled('xml_sitemaps_ping') )
			wp_schedule_single_event(time() + 43200, 'xml_sitemaps_ping');
	} # save_post()
	
	
	/**
	 * generate()
	 *
	 * @return bool $success
	 **/

	function generate() {
		if ( function_exists('memory_get_usage') && ( (int) @ini_get('memory_limit') < 128 ) )
			@ini_set('memory_limit', '128M');
		
		include_once dirname(__FILE__) . '/xml-sitemaps-utils.php';
		
		# dump wp cache
		wp_cache_flush();
		
		# only keep fields involved in permalinks
		add_filter('posts_fields_request', array('xml_sitemaps', 'kill_query_fields'));
		
		# sitemap.xml
		$sitemap = new sitemap_xml;
		$return = $sitemap->generate();
		
		# restore fields
		remove_filter('posts_fields_request', array('xml_sitemaps', 'kill_query_fields'));
		
		# dump wp cache
		wp_cache_flush();
		
		return $return;
	} # generate()
	
	
	/**
	 * template_redirect()
	 *
	 * @return void
	 **/

	function template_redirect() {
		$home_path = parse_url(get_option('home'));
		$home_path = isset($home_path['path']) ? rtrim($home_path['path'], '/') : '';
		
		if ( in_array(
				$_SERVER['REQUEST_URI'],
				array($home_path . '/sitemap.xml', $home_path . '/sitemap.xml.gz')
				)
			) {
			$dir = WP_CONTENT_DIR . '/sitemaps/';
			
			if ( !is_dir($dir) && !xml_sitemaps::activate() )
				return;
			
			$sitemap = basename($_SERVER['REQUEST_URI']);
			
			if ( xml_sitemaps_debug || !file_exists(WP_CONTENT_DIR . '/sitemaps/' . $sitemap) ) {
				if ( !xml_sitemaps::generate() )
					return;
			}
			
			# Reset WP
			$GLOBALS['wp_filter'] = array();
			while ( @ob_end_clean() );

			status_header(200);
			if ( strpos($sitemap, '.gz') !== false ) {
				header('Content-Type: application/x-gzip');
			} else {
				header('Content-Type:text/xml; charset=utf-8');
			}
			readfile(WP_CONTENT_DIR . '/sitemaps/' . $sitemap);
			die;
		}
	} # template_redirect()
	
	
	/**
	 * rewrite_rules()
	 *
	 * @param array $rules
	 * @return array $rules
	 **/
	
	function rewrite_rules($rules) {
		$home_path = parse_url(get_option('home'));
		$home_path = isset($home_path['path']) ? rtrim($home_path['path'], '/') : '';

		$site_path = parse_url(get_option('siteurl'));
		$site_path = isset($site_path['path']) ? rtrim($site_path['path'], '/') : '';
		
		$extra = <<<EOS
RewriteRule ^(sitemap\.xml|sitemap\.xml\.gz)$ $site_path/wp-content/sitemaps/$1 [L]
EOS;
		
		if ( preg_match("/RewriteBase.+\n*/i", $rules, $rewrite_base) ) {
			$rewrite_base = end($rewrite_base);
			$new_rewrite_base = trim($rewrite_base) . "\n\n" . trim($extra) . "\n\n";
			$rules = str_replace($rewrite_base, $new_rewrite_base, $rules);
		}
		
		return $rules;
	} # rewrite_rules()
	
	
	/**
	 * save_rewrite_rules()
	 *
	 * @return bool $success
	 **/
	
	function save_rewrite_rules() {
		global $wp_rewrite;
		
		if ( !isset($wp_rewrite) )
			$wp_rewrite =& new WP_Rewrite;
		
		if ( !function_exists('save_mod_rewrite_rules') || !function_exists('get_home_path') ) {
			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		
		if ( !get_option('permalink_structure') || !intval(get_option('blog_public')) )
			remove_filter('mod_rewrite_rules', array('xml_sitemaps', 'rewrite_rules'));
		
		return save_mod_rewrite_rules()
			&& get_option('permalink_structure')
			&& intval(get_option('blog_public'));
	} # save_rewrite_rules()
	
	
	/**
	 * inactive_notice()
	 *
	 * @return void
	 **/
	
	function inactive_notice() {
		if ( !xml_sitemaps::activate() ) {
			global $wpdb;
			if ( version_compare($wpdb->db_version(), '4.1.1', '<') ) {
				echo '<div class="error">'
					. '<p>'
					. __('XML Sitemaps requires MySQL 4.1.1 or later. It\'s time to <a href="http://www.semiologic.com/resources/wp-basics/wordpress-server-requirements/">change hosts</a> if yours doesn\'t want to upgrade.', 'xml-sitemaps')
					. '</p>' . "\n"
					. '</div>' . "\n\n";
			} elseif ( !get_option('permalink_structure') ) {
				if ( strpos($_SERVER['REQUEST_URI'], 'wp-admin/options-permalink.php') === false ) {
					echo '<div class="error">'
						. '<p>'
						. __('XML Sitemaps requires that you enable a fancy url structure. under Settings / Permalinks.', 'xml-sitemaps')
						. '</p>' . "\n"
						. '</div>' . "\n\n";
				}
			} elseif ( !intval(get_option('blog_public')) ) {
				echo '<div class="error">'
					. '<p>'
					. __('XML Sitemaps is not active on your site because of your site\'s privacy settings (Settings / Privacy).', 'xml-sitemaps')
					. '</p>' . "\n"
					. '</div>' . "\n\n";
			} elseif ( !xml_sitemaps::rm(WP_CONTENT_DIR . '/sitemaps')
				|| !xml_sitemaps::mkdir(WP_CONTENT_DIR . '/sitemaps')
				|| !is_writable('.htaccess') ){
				echo '<div class="error">'
					. '<p>'
					. __('XML Sitemaps is not active on your site. Please make the following file and folder writable by the server:', 'xml-sitemaps')
					. '</p>' . "\n"
					. '<ul style="margin-left: 1.5em; list-style: square;">' . "\n"
					. '<li>' . '.htaccess (chmod 666)' . '</li>' . "\n"
					. '<li>' . 'wp-content (chmod 777)' . '</li>' . "\n"
					. '</ul>' . "\n"
					. '</div>' . "\n\n";
			}
		}
	} # inactive_notice()
	
	
	/**
	 * activate()
	 *
	 * @return bool $success
	 **/
	
	function activate() {
		# reset status
		$active = get_option('xml_sitemaps');
		$active = true;
		
		# check mysql version
		global $wpdb;
		if ( version_compare($wpdb->db_version(), '4.1.1', '<') ) {
			$active = false;
		} else {
			# clean up
			$active &= xml_sitemaps::rm(WP_CONTENT_DIR . '/sitemaps');
			
			# create folder
			if ( $active )
				$active &= xml_sitemaps::mkdir(WP_CONTENT_DIR . '/sitemaps');
			
			# insert rewrite rules
			if ( $active && !xml_sitemaps_debug )
				add_filter('mod_rewrite_rules', array('xml_sitemaps', 'rewrite_rules'));
			
			$active &= xml_sitemaps::save_rewrite_rules();
		}
		
		if ( !$active )
			remove_filter('mod_rewrite_rules', array('xml_sitemaps', 'rewrite_rules'));
		
		# save status
		update_option('xml_sitemaps', intval($active));
		
		return $active;
	} # activate()
	
	
	/**
	 * reactivate()
	 *
	 * @param mixed $in
	 * @return mixed $in
	 **/
	
	function reactivate($in = null) {
		xml_sitemaps::activate();
		
		return $in;
	} # reactivate()
	
	
	/**
	 * deactivate()
	 *
	 * @return void
	 **/
	
	function deactivate() {
		# clean up
		xml_sitemaps::rm(WP_CONTENT_DIR . '/sitemaps');
		
		# drop rewrite rules
		remove_filter('mod_rewrite_rules', array('xml_sitemaps', 'rewrite_rules'));
		xml_sitemaps::save_rewrite_rules();
		
		# reset status
		update_option('xml_sitemaps', 0);
	} # deactivate()
	
	
	/**
	 * mkdir()
	 *
	 * @return bool $success
	 **/
	
	function mkdir($dir) {
		return wp_mkdir_p($dir);
	} # mkdir()
	
	
	/**
	 * rm()
	 *
	 * @return bool $success
	 **/
	
	function rm($dir) {
		if ( !file_exists($dir) )
			return true;
		
		if ( is_file($dir) )
			return @unlink($dir);
		
		if ( !( $handle = @opendir($dir) ) )
			return false;
		
		while ( ( $file = readdir($handle) ) !== false ) {
			if ( in_array($file, array('.', '..')) )
				continue;
			
			if ( !xml_sitemaps::rm("$dir/$file") ) {
				closedir($handle);
				return false;
			}
		}
		
		closedir($handle);
		
		return @rmdir($dir);
	} # rm()
	
	
	/**
	 * kill_query_fields()
	 *
	 * @param string $fields
	 * @return string $fields
	 **/
	
	function kill_query_fields($fields) {
		global $wpdb;
		
		return "$wpdb->posts.ID, $wpdb->posts.post_author, $wpdb->posts.post_name, $wpdb->posts.post_type, $wpdb->posts.post_status, $wpdb->posts.post_parent, $wpdb->posts.post_date, $wpdb->posts.post_modified";
	} # kill_query_fields()
	
	
	/**
	 * kill_query()
	 *
	 * @param string $where
	 * @return string $where
	 **/
	
	function kill_query($in) {
		return ' AND ( 1 = 0 ) ';
	}
} # xml_sitemaps
?>