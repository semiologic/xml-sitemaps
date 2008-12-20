<?php
/*
Plugin Name: XML Sitemaps
Plugin URI: http://www.semiologic.com/software/marketing/xml-sitemaps/
Description: Automatically generate XML Sitemaps for your site and notifies search engines when they're updated.
Author: Denis de Bernardy
Version: 0.1 alpha
Author URI: http://www.getsemiologic.com
Update Service: http://version.semiologic.com/plugins
Update Tag: xml_sitemaps
Update Package: http://www.semiologic.com/media/software/marketing/xml-sitemaps/xml-sitemaps.zip
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/

@define('xml_sitemaps_debug', true);


class xml_sitemaps
{
	#
	# init()
	#
	
	function init()
	{
		register_activation_hook(__FILE__, array('xml_sitemaps', 'activate'));
		register_deactivation_hook(__FILE__, array('xml_sitemaps', 'deactivate'));
		
		if ( get_option('xml_sitemaps') )
		{
			if ( !xml_sitemaps_debug )
			{
				add_filter('mod_rewrite_rules', array('xml_sitemaps', 'rewrite_rules'));
			}
			
			add_action('template_redirect', array('xml_sitemaps', 'template_redirect'));
		}
		else
		{
			add_action('admin_notices', array('xml_sitemaps', 'inactive_notice'));
		}
		
		add_action('update_option_permalink_structure', array('xml_sitemaps', 'reactivate'));
	} # init()
	
	
	#
	# generate()
	#
	
	function generate()
	{
		include_once dirname(__FILE__) . '/xml-sitemaps-utils.php';
		
		# only keep fields involved in permalinks
		wp_cache_flush();
		add_filter('posts_fields_request', array('xml_sitemaps', 'kill_query_fields'));
		
		# sitemap.xml
		$sitemap = new sitemap_xml;
		$sitemap->generate();
		
		# restore fields
		remove_filter('posts_fields_request', array('xml_sitemaps', 'kill_query_fields'));
	} # generate()
	
	
	#
	# template_redirect()
	#
	
	function template_redirect()
	{
		$home_path = parse_url(get_option('home'));
		$home_path = isset($home_path['path']) ? rtrim($home_path['path'], '/') : '';
		
		if ( in_array(
				$_SERVER['REQUEST_URI'],
				array($home_path . '/sitemap.xml', $home_path . '/sitemap.xml.gz'))
			)
		{
			$sitemap = basename($_SERVER['REQUEST_URI']);

			if ( !file_exists($sitemap) )
			{
				xml_sitemaps::generate();
			}
			
			# Reset WP
			$GLOBALS['wp_filter'] = array();
			while ( @ob_end_clean() );

			ob_start();

			header('Content-Type:text/xml; charset=utf-8');
			readfile(WP_CONTENT_DIR . '/sitemaps/' . $sitemap);
			die;
		}
	} # template_redirect()
	
	
	#
	# rewrite_rules()
	#
	
	function rewrite_rules($rules)
	{
		$home_path = parse_url(get_option('home'));
		$home_path = isset($home_path['path']) ? rtrim($home_path['path'], '/') : '';

		$site_path = parse_url(get_option('siteurl'));
		$site_path = isset($site_path['path']) ? rtrim($site_path['path'], '/') : '';
		
		$extra = <<<EOF
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase $home_path
RewriteRule ^(sitemap\.xml|sitemap\.xml\.gz)$ $site_path/wp-content/sitemaps/$1 [L]
</IfModule>
EOF;
		$rules = $extra . "\n\n" . $rules;
		
		return $rules;
	} # rewrite_rules()
	
	
	#
	# save_rewrite_rules()
	#
	
	function save_rewrite_rules()
	{
		global $wp_rewrite;
		
		if ( !isset($wp_rewrite) )
		{
			$wp_rewrite =& new WP_Rewrite;
		}
		
		if ( !get_option('permalink_structure') ) return false;
		
		if ( !function_exists('save_mod_rewrite_rules') )
		{
			include_once ABSPATH . 'wp-admin/misc.php';
		}
		
		return save_mod_rewrite_rules();
	} # save_rewrite_rules()
	
	
	#
	# inactive_notice()
	#
	
	function inactive_notice()
	{
		if ( !xml_sitemaps::activate() )
		{
			if ( version_compare(mysql_get_server_info(), '4.1.1', '<') )
			{
				echo '<div class="error">'
					. '<p>'
					. 'XML Sitemaps requires MySQL 4.1.1 or later. It\'s time to <a href="http://www.semiologic.com/resources/wp-basics/wordpress-server-requirements/">change hosts</a> if yours doesn\'t want to upgrade.'
					. '</p>' . "\n"
					. '</div>' . "\n\n";
			}
			elseif ( !get_option('permalink_structure') )
			{
				if ( strpos($_SERVER['REQUEST_URI'], 'wp-admin/options-permalink.php') === false )
				{
					echo '<div class="error">'
						. '<p>'
						. 'XML Sitemaps requires that you enable a fancy urls structure, under Settings / Permalinks.'
						. '</p>' . "\n"
						. '</div>' . "\n\n";
				}
			}
			else
			{
				echo '<div class="error">'
					. '<p>'
					. 'XML Sitemaps is not active on your site. Please make the following file and folder writable by the server:'
					. '</p>' . "\n"
					. '<ul style="margin-left: 1.5em; list-style: square;">' . "\n"
					. '<li>' . '.htaccess (chmod 666)' . '</li>' . "\n"
					. '<li>' . 'wp-content (chmod 777)' . '</li>' . "\n"
					. '</ul>' . "\n"
					. '</div>' . "\n\n";
			}
		}
	} # inactive_notice()
	
	
	#
	# activate()
	#
	
	function activate()
	{
		# reset status
		$active = get_option('xml_sitemaps');
		$active = true;
		
		# check mysql version
		if ( version_compare(mysql_get_server_info(), '4.1.1', '<') )
		{
			$active = false;
		}
		else
		{
			# clean up
			foreach ( array(
				ABSPATH . 'sitemap.xml',
				ABSPATH . 'sitemap.xml.gz',
				WP_CONTENT_DIR . '/sitemaps',
					) as $file )
			{
				if ( file_exists($file) )
				{
					$active &= xml_sitemaps::rm($file);
				}
			}

			# create folder
			$active &= xml_sitemaps::mkdir(WP_CONTENT_DIR . '/sitemaps');

			# insert rewrite rules
			if ( !xml_sitemaps_debug )
			{
				add_filter('mod_rewrite_rules', array('xml_sitemaps', 'rewrite_rules'));
			}
			
			$active &= xml_sitemaps::save_rewrite_rules();
		}
		
		if ( !$active )
		{
			remove_filter('mod_rewrite_rules', array('xml_sitemaps', 'rewrite_rules'));
		}
		
		# save status
		update_option('xml_sitemaps', intval($active));
		
		return $active;
	} # activate()
	
	
	#
	# reactivate()
	#
	
	function reactivate($in = null)
	{
		xml_sitemaps::activate();
		
		return $in;
	} # reactivate()
	
	
	#
	# deactivate()
	#
	
	function deactivate()
	{
		# clean up
		xml_sitemaps::rm(WP_CONTENT_DIR . '/sitemaps');
		
		# drop rewrite rules
		remove_filter('mod_rewrite_rules', array('xml_sitemaps', 'rewrite_rules'));
		xml_sitemaps::save_rewrite_rules();
		
		# reset status
		update_option('xml_sitemaps', 0);
	} # deactivate()
	
	
	#
	# mkdir()
	#
	
	function mkdir($dir)
	{
		if ( ! @mkdir($dir) )
		{
			return false;
		}
		else
		{
			@chmod($dir, 0777);
			
			return true;
		}
	} # mkdir()
	
	
	#
	# rm()
	#
	
	function rm($dir)
	{
		if ( is_file($dir) )
		{
			return @unlink($dir);
		}
		else
		{
			foreach ( glob("$dir/*") as $file )
			{
				if ( !xml_sitemaps::rm($file) )
				{
					return false;
				}
			}
			
			return @rmdir($dir);
		}
	} # rm()
	
	
	#
	# kill_query_fields()
	#
	
	function kill_query_fields($in)
	{
		global $wpdb;
		
		return "$wpdb->posts.ID, $wpdb->posts.post_author, $wpdb->posts.post_name, $wpdb->posts.post_type, $wpdb->posts.post_status, $wpdb->posts.post_parent, $wpdb->posts.post_date, $wpdb->posts.post_modified";
	} # kill_query_fields()
	
	
	#
	# kill_query()
	#
	
	function kill_query($in)
	{
		return ' AND ( 1 = 0 ) ';
	}
} # xml_sitemaps

xml_sitemaps::init();
?>