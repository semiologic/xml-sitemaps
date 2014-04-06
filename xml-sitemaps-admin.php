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
	protected static $instance = NULL;

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
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}


	/**
	 * Constructor.
	 *
	 *
	 */

	public function __construct() {
		$this->plugin_url    = plugins_url( '/', __FILE__ );
		$this->plugin_path   = plugin_dir_path( __FILE__ );

		$this->init();
    } 

	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		// more stuff: register actions and filters
		add_action('settings_page_xml-sitemaps', array($this, 'save_options'), 0);
	}

    /**
	 * save_options()
	 *
	 * @return void
	 **/

	function save_options() {
		if ( !$_POST || !current_user_can('manage_options') )
			return;
		
		check_admin_referer('xml_sitemaps');

      	$inc_archives = isset($_POST['inc_archives']) ? $_POST['inc_archives'] : false;
		$inc_authors = isset($_POST['inc_authors']) ? $_POST['inc_authors'] : false;
		$inc_categories = isset($_POST['inc_categories']) ? $_POST['inc_categories'] : false;
		$inc_tags = isset($_POST['inc_tags']) ? $_POST['inc_tags'] : false;

		$exclude_pages = stripslashes($_POST['exclude_pages']);

		$mobile_sitemap = isset($_POST['mobile_sitemap']) ? $_POST['mobile_sitemap'] : false;

		$exclude_pages = preg_replace( array(
		    '/[^\d,]/',    // Matches anything that's not a comma or number.
		    '/(?<=,),+/',  // Matches consecutive commas.
		    '/^,+/',       // Matches leading commas.
		    '/,+$/'        // Matches trailing commas.
		  ),
		  '', (string) $exclude_pages);

      	update_option('xml_sitemaps',
	        compact('inc_archives', 'inc_authors', 'inc_categories', 'inc_tags', 'exclude_pages', 'mobile_sitemap'));

		echo '<div class="updated fade">' . "\n"
			. '<p>'
				. '<strong>'
				. __('Settings saved.', 'xml-sitemaps')
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
		
		wp_nonce_field('xml_sitemaps');
		
		echo '<h2>' . __('XML Sitemaps Settings', 'xml-sitemaps') . '</h2>' . "\n";

		echo '<table class="form-table">' . "\n";

        echo '<tr>' . "\n"
            . '<th scope="row">'
            . __('Include Archive Pages', 'xml-sitemaps')
            . '</th>' . "\n"
            . '<td>'
            . '<label>'
            . '<input type="checkbox" name="inc_archives"'
                . checked((bool) $options['inc_archives'], true, false)
                . ' />'
            . '&nbsp;'
            . __('Check to include archive pages in your sitemap.', 'xml-sitemaps')
            . '</label>'
            . '</td>' . "\n"
            . '</tr>' . "\n";


		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('Include Author Pages', 'xml-sitemaps')
			. '</th>' . "\n"
			. '<td>'
			. '<label>'
			. '<input type="checkbox" name="inc_authors"'
				. checked((bool) $options['inc_authors'], true, false)
				. ' />'
			. '&nbsp;'
			. __('Check to include author pages in your sitemap.', 'xml-sitemaps')
			. '</label>'
			. '</td>' . "\n"
			. '</tr>' . "\n";

		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('Include Category Pages', 'xml-sitemaps')
			. '</th>' . "\n"
			. '<td>'
			. '<label>'
			. '<input type="checkbox" name="inc_categories"'
				. checked((bool) $options['inc_categories'], true, false)
				. ' />'
			. '&nbsp;'
			. __('Check to include category pages in your sitemap.', 'xml-sitemaps')
			. '</label>'
			. '</td>' . "\n"
			. '</tr>' . "\n";

		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('Include Tag Pages', 'xml-sitemaps')
			. '</th>' . "\n"
			. '<td>'
			. '<label>'
			. '<input type="checkbox" name="inc_tags"'
				. checked((bool) $options['inc_tags'], true, false)
				. ' />'
			. '&nbsp;'
			. __('Check to include tag pages in your sitemap.', 'xml-sitemaps')
			. '</label>'
			. '</td>' . "\n"
			. '</tr>' . "\n";

		echo '<tr valign="top">'
			. '<th scope="row">'
			. __('Exclude Pages', 'xml-sitemaps')
			. '</th>'
			. '<td>'
			. '<p>'
			. __('IDs of pages you do not wish to include in your sitemap separated by commas (\',\'):', 'xml-sitemaps')
			. '</p>' ."\n"
			. '<input type="text" name="exclude_pages"'
                . ' class="widefat code"'
                . ' value="' . esc_attr($options['exclude_pages']) . '"'
                . ' />'
			. '</td>'
			. '</tr>';

		echo '<tr>' . "\n"
			. '<th scope="row">'
			. __('Generate Mobile Sitemap', 'xml-sitemaps')
			. '</th>' . "\n"
			. '<td>'
			. '<label>'
			. '<input type="checkbox" name="mobile_sitemap"'
				. checked((bool) $options['mobile_sitemap'], true, false)
				. ' />'
			. '&nbsp;'
			. __('Check if this plugin is installed on an mobile-only site, such as m.example.com or example.mobi.', 'xml-sitemaps')
			. '</label>'
			. '</td>' . "\n"
			. '</tr>' . "\n";

		echo "</table>\n";
		
		echo '<div class="submit">'
			. '<input type="submit"'
				. ' value="' . esc_attr(__('Save Changes', 'xml-sitemaps')) . '"'
				. " />"
			. "</div>\n";
		
		echo '</form>' . "\n";

		
		echo '</div>' . "\n";
	} # edit_options()

} # xml_sitemaps_admin

$xml_sitemaps_admin = xml_sitemaps_admin::get_instance();