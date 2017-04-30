<?php

/**
 * xml_sitemaps_admin
 *
 * @package XML Sitemaps
 **/
class xml_sitemaps_admin {
	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = null;

	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_url = '';

	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_path = '';

	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @return  object of this class
	 */
	public static function get_instance() {
		null === self::$instance and self::$instance = new self;

		return self::$instance;
	}


	/**
	 * Constructor.
	 *
	 *
	 */

	public function __construct() {
		$this->plugin_url  = plugins_url( '/', __FILE__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );

		$this->init();
	}

	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		// more stuff: register actions and filters
		add_action( 'settings_page_xml-sitemaps', array( $this, 'save_options' ), 0 );
	}

	/**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( ! $_POST || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'xml_sitemaps' );

		foreach (
			array(
				'inc_archives',
				'inc_authors',
				'inc_categories',
				'inc_tags',
				'mobile_sitemap',
				'empty_author',
				'inc_custom_posts',
			) as $var
		) {
			$$var = isset( $_POST[ $var ] );
		}

		$exclude_pages = stripslashes( $_POST['exclude_pages'] );

		$exclude_pages = preg_replace( array(
			'/[^\d,]/',    // Matches anything that's not a comma or number.
			'/(?<=,),+/',  // Matches consecutive commas.
			'/^,+/',       // Matches leading commas.
			'/,+$/'        // Matches trailing commas.
		),
			'', (string) $exclude_pages );

		$version = xml_sitemaps_version;

		update_option( 'xml_sitemaps',
			compact( 'inc_archives', 'inc_authors', 'inc_categories', 'inc_tags',
				'exclude_pages', 'mobile_sitemap', 'version', 'empty_author', 'inc_custom_posts' ) );

		xml_sitemaps::clean( WP_CONTENT_DIR . '/sitemaps' );

		echo '<div class="updated fade">' . "\n"
		     . '<p>'
		     . '<strong>'
		     . __( 'Settings saved.', 'xml-sitemaps' )
		     . '</strong>'
		     . '</p>' . "\n"
		     . '</div>' . "\n";
	} # save_options()


	/**
	 * edit_options()
	 *
	 * @return void
	 **/

	static function edit_options() {
		$options = xml_sitemaps::get_options();

		echo '<div class="wrap">' . "\n"
		     . '<form method="post" action="">' . "\n";

		wp_nonce_field( 'xml_sitemaps' );

		echo '<h2>' . __( 'XML Sitemaps Settings', 'xml-sitemaps' ) . '</h2>' . "\n";

		echo '<table class="form-table">' . "\n";

		echo '<tr>' . "\n"
		     . '<th scope="row">'
		     . __( 'Include Archive Pages', 'xml-sitemaps' )
		     . '</th>' . "\n"
		     . '<td>'
		     . '<label>'
		     . '<input type="checkbox" name="inc_archives"'
		     . checked( (bool) $options['inc_archives'], true, false )
		     . ' />'
		     . '&nbsp;'
		     . __( 'Check to include date archive pages, such as YYYY or YYYY/MM, in your sitemap.', 'xml-sitemaps' )
		     . '</label>'
		     . '</td>' . "\n"
		     . '</tr>' . "\n";


		echo '<tr>' . "\n"
		     . '<th scope="row">'
		     . __( 'Include Author Pages', 'xml-sitemaps' )
		     . '</th>' . "\n"
		     . '<td>'
		     . '<label>'
		     . '<input type="checkbox" name="inc_authors"'
		     . checked( (bool) $options['inc_authors'], true, false )
		     . ' />'
		     . '&nbsp;'
		     . __( 'Check to include author pages in your sitemap.', 'xml-sitemaps' )
		     . '</label>'
		     . '</td>' . "\n"
		     . '</tr>' . "\n";

		echo '<tr>' . "\n"
		     . '<th scope="row">'
		     . __( 'Empty Author Pages', 'xml-sitemaps' )
		     . '</th>' . "\n"
		     . '<td>'
		     . '<label>'
		     . '<input type="checkbox" name="empty_author"'
		     . checked( (bool) $options['empty_author'], true, false )
		     . ' />'
		     . '&nbsp;'
		     . __( 'Check to include author page in your sitemap if the author(s) has not published any pages or posts yet.',
				'xml-sitemaps' )
		     . '</label>'
		     . '</td>' . "\n"
		     . '</tr>' . "\n";

		echo '<tr>' . "\n"
		     . '<th scope="row">'
		     . __( 'Include Category Pages', 'xml-sitemaps' )
		     . '</th>' . "\n"
		     . '<td>'
		     . '<label>'
		     . '<input type="checkbox" name="inc_categories"'
		     . checked( (bool) $options['inc_categories'], true, false )
		     . ' />'
		     . '&nbsp;'
		     . __( 'Check to include category pages in your sitemap.', 'xml-sitemaps' )
		     . '</label>'
		     . '</td>' . "\n"
		     . '</tr>' . "\n";

		echo '<tr>' . "\n"
		     . '<th scope="row">'
		     . __( 'Include Tag Pages', 'xml-sitemaps' )
		     . '</th>' . "\n"
		     . '<td>'
		     . '<label>'
		     . '<input type="checkbox" name="inc_tags"'
		     . checked( (bool) $options['inc_tags'], true, false )
		     . ' />'
		     . '&nbsp;'
		     . __( 'Check to include tag pages in your sitemap.', 'xml-sitemaps' )
		     . '</label>'
		     . '</td>' . "\n"
		     . '</tr>' . "\n";

		echo '<tr>' . "\n"
		     . '<th scope="row">'
		     . __( 'Include Custom Posts', 'xml-sitemaps' )
		     . '</th>' . "\n"
		     . '<td>'
		     . '<label>'
		     . '<input type="checkbox" name="inc_custom_posts"'
		     . checked( (bool) $options['inc_custom_posts'], true, false )
		     . ' />'
		     . '&nbsp;'
		     . __( 'Check to include custom posts in your sitemap.', 'xml-sitemaps' )
		     . '</label>'
		     . '</td>' . "\n"
		     . '</tr>' . "\n";

		echo '<tr valign="top">'
		     . '<th scope="row">'
		     . __( 'Exclude Pages &amp; Posts', 'xml-sitemaps' )
		     . '</th>'
		     . '<td>'
		     . '<p>'
		     . __( 'IDs of pages and/or posts you do not wish to include in your sitemap separated by commas (\',\'):',
				'xml-sitemaps' )
		     . '</p>' . "\n"
		     . '<input type="text" name="exclude_pages"'
		     . ' class="widefat code"'
		     . ' value="' . esc_attr( $options['exclude_pages'] ) . '"'
		     . ' />'
		     . '</td>'
		     . '</tr>';

		echo '<tr>' . "\n"
		     . '<th scope="row">'
		     . __( 'Generate Mobile Sitemap', 'xml-sitemaps' )
		     . '</th>' . "\n"
		     . '<td>'
		     . '<label>'
		     . '<input type="checkbox" name="mobile_sitemap"'
		     . checked( (bool) $options['mobile_sitemap'], true, false )
		     . ' />'
		     . '&nbsp;'
		     . __( 'Check if this plugin is installed on an mobile-only site, such as m.example.com or example.mobi.',
				'xml-sitemaps' )
		     . '</label>'
		     . '</td>' . "\n"
		     . '</tr>' . "\n";

		echo "</table>\n";

		echo '<div class="submit">'
		     . '<input type="submit"'
		     . ' value="' . esc_attr( __( 'Save Changes', 'xml-sitemaps' ) ) . '"'
		     . " />"
		     . "</div>\n";

		echo '</form>' . "\n";


		echo '</div>' . "\n";
	} # edit_options()

} # xml_sitemaps_admin

$xml_sitemaps_admin = xml_sitemaps_admin::get_instance();