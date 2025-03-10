<?php
/**
 * SitePress Template functions
 *
 * @package wpml-core
 */

use function WPML\Container\make;

/**
 * Returns true if the site uses ICanLocalize.
 *
 * @return bool
 */
function wpml_site_uses_icl() {
	global $wpdb;

	$setting = 'site_does_not_use_icl';

	if ( icl_get_setting( $setting, false ) ) {
		return false;
	}

	$cache = new WPML_WP_Cache( 'wpml-checks' );

	$found         = false;
	$site_uses_icl = $cache->get( 'site_uses_icl', $found );

	if ( ! $found ) {
		$site_uses_icl = false;

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_translation_status'" );

		if ( $table_exists ) {
			$icl_job_count_query = $wpdb->prepare(
				"SELECT rid
							FROM {$wpdb->prefix}icl_translation_status
							WHERE translation_service = %s
							LIMIT 1;",
				'icanlocalize'
			);

			$site_uses_icl = (bool) $wpdb->get_var( $icl_job_count_query );
		}
		$cache->set( 'site_uses_icl', $site_uses_icl );
	}

	if ( icl_get_setting( 'setup_complete', false ) && ! $site_uses_icl ) {
		icl_set_setting( $setting, true, true );
	}

	return $site_uses_icl;
}

/**
 * Returns the value of a given key setting.
 *
 * @param string      $key     The setting's key.
 * @param mixed|false $default The value to use if the setting does not exist.
 *
 * @return bool|mixed
 * @since      3.1
 * @deprecated 3.2 use `\wpml_setting` or 'wpml_get_setting_filter' filter instead
 */
function icl_get_setting( $key, $default = false ) {
	return wpml_get_setting( $key, $default );
}

/**
 * Get a WPML setting value.
 * If the Main SitePress Class cannot be accessed by the function it will read the setting from the database.
 * It will return `$default` if the requested key is not set.
 *
 * @param string     $key          The setting's key.
 * @param mixed|null $default      Required. The value to return if the settings key does not exist
 *                                 (typically it's false, but you may want to use something else).
 *
 * @return mixed The value of the requested setting, or `$default`
 * @since 4.1
 */
function wpml_get_setting( $key, $default = null ) {
	global $sitepress_settings;
	$sitepress_settings = isset( $sitepress_settings ) ? $sitepress_settings : get_option( 'icl_sitepress_settings' );

	return isset( $sitepress_settings[ $key ] ) ? $sitepress_settings[ $key ] : $default;
}

/**
 * Get a WPML setting value.
 * If the Main SitePress Class cannot be access to the function will read the setting from the database.
 * Will return false if the requested key is not set or.
 * the default value passed in the function's second parameter.
 *
 * @param mixed|false $default     Required. The value to return if the settings key does not exist
 *                                 (typically it's false, but you may want to use something else).
 * @param string      $key         The setting's key.
 *
 * @return mixed The value of the requested setting, or $default
 * @since 3.2
 * @uses  \SitePress::api_hooks
 */
function wpml_get_setting_filter( $default, $key ) {
	$args = func_get_args();
	if ( count( $args ) > 2 && $args[2] !== null ) {
		$default = $args[2];
	}

	return wpml_get_setting( $key, $default );
}

/**
 * Returns the value of a given key sub-setting.
 *
 * @param string      $key         The setting's key.
 * @param string      $sub_key     The settings name key to return the value of.
 * @param mixed|false $default     Required. The value to return if the settings key does not exist
 *                                 (typically it's false, but you may want to use something else).
 *
 * @return bool|mixed
 * @since      3.1
 * @deprecated 3.2 use 'wpml_sub_setting' filter instead
 */
function icl_get_sub_setting( $key, $sub_key, $default = false ) {
	$parent = icl_get_setting( $key, array() );

	return isset( $parent[ $sub_key ] ) ? $parent[ $sub_key ] : $default;
}

/**
 * Gets a WPML sub setting value.
 *
 * @param mixed|false $default     Required. The value to return if the settings key does not exist
 *                                 (typically it's false, but you may want to use something else).
 * @param string      $key         The settings name key the sub key belongs to.
 * @param string      $sub_key     The sub key to return the value of.
 * @param mixed       $deprecated  Deprecated param.
 *
 * @return mixed The value of the requested setting, or $default
 * @todo  [WPML 3.3] Remove deprecated argument
 *
 * @uses  \wpml_get_setting_filter
 *
 * @since 3.2
 * @uses  \SitePress::api_hooks
 */
function wpml_get_sub_setting_filter( $default, $key, $sub_key, $deprecated = null ) {
	$default = null !== $deprecated && ! $default ? $deprecated : $default;

	$parent = wpml_get_setting_filter( array(), $key );

	return isset( $parent[ $sub_key ] ) ? $parent[ $sub_key ] : $default;
}

/**
 * Saves the value of a given key.
 *
 * @param string $key      The settings name key the sub key belongs to.
 * @param mixed  $value    The value to assign to the given key.
 * @param bool   $save_now Must call icl_save_settings() to permanently store the value.
 *
 * @return bool Always True. If `$save_now === true`, it returns the result of `update_option`
 */
function icl_set_setting( $key, $value, $save_now = false ) {
	global $sitepress_settings;

	$result = true;

	$sitepress_settings[ $key ] = $value;

	if ( true === $save_now ) {
		/* We need to save settings anyway, in this case. */
		$result = update_option( 'icl_sitepress_settings', $sitepress_settings );
		do_action( 'icl_save_settings', $sitepress_settings );
	}

	return $result;
}

/**
 * Save the settings in the db.
 */
function icl_save_settings() {
	global $sitepress;
	$sitepress->save_settings();
}

/**
 * Gets all the settings.
 *
 * @return array|false
 */
function icl_get_settings() {
	global $sitepress;

	return isset( $sitepress ) ? $sitepress->get_settings() : false;
}

/**
 * Add settings link to plugin page.
 *
 * @param SitePress     $sitepress
 * @param array<string> $links
 * @param string        $file
 *
 * @return array
 */
function icl_plugin_action_links( SitePress $sitepress, $links, $file ) {
	if ( $file == WPML_PLUGIN_BASENAME ) {
		$endpoint = $sitepress->is_setup_complete() ? 'languages.php' : 'setup.php';
		$links[]  = '<a href="admin.php?page=' . WPML_PLUGIN_FOLDER . '/menu/' . $endpoint . '">' . __( 'Configure', 'sitepress' ) . '</a>';
	}

	return $links;
}

if ( defined( 'ICL_DEBUG_MODE' ) && ICL_DEBUG_MODE ) {
	add_action( 'admin_notices', '_icl_deprecated_icl_debug_mode' );
}

function _icl_deprecated_icl_debug_mode() {
	echo '<div class="updated"><p><strong>ICL_DEBUG_MODE</strong> no longer supported. Please use <strong>WP_DEBUG</strong> instead.</p></div>';
}

if ( ! function_exists( 'icl_js_escape' ) ) {
	function icl_js_escape( $str ) {
		$str = esc_js( $str );
		$str = htmlspecialchars_decode( $str );

		return $str;
	}
}

/**
 * Read and, if needed, generate the site ID based on the scope.
 *
 * @param string $scope      Defaults to "global".
 *                           Use a different value when the ID is used for specific scopes.
 *
 * @param bool   $create_new Forces the creation of a new ID.
 *
 * @return string|null The generated/stored ID or null if it wasn't possible to generate/store the value.
 */
function wpml_get_site_id( $scope = WPML_Site_ID::SITE_SCOPES_GLOBAL, $create_new = false ) {
	static $site_id;

	if ( ! $site_id ) {
		$site_id = new WPML_Site_ID();
	}

	return $site_id->get_site_id( $scope, $create_new );
}

function _icl_tax_has_objects_recursive( $id, $term_id = -1, $rec = 0 ) {
	// based on the case where two categories were one the parent of another
	// eliminating the chance of infinite loops by letting this function calling itself too many times
	// 100 is the default limit in most of teh php configuration
	//
	// this limit this function to work only with categories nested up to 60 levels
	// should enough for most cases
	if ( $rec > 60 ) {
		return false;
	}

	global $wpdb;

	if ( $term_id === -1 ) {
		$term_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id=%d", $id ) );
	}

	$children = $wpdb->get_results(
		$wpdb->prepare(
			"
        SELECT term_taxonomy_id, term_id, count FROM {$wpdb->term_taxonomy} WHERE parent = %d
    ",
			$term_id
		)
	);

	$count = 0;
	foreach ( $children as $ch ) {
		$count += $ch->count;
	}

	if ( $count ) {
		return true;
	} else {
		foreach ( $children as $ch ) {
			if ( _icl_tax_has_objects_recursive( $ch->term_taxonomy_id, $ch->term_id, $rec + 1 ) ) {
				return true;
			}
		}
	}

	return false;
}

function _icl_trash_restore_prompt() {
	if ( isset( $_GET['lang'] ) ) {
		$post = get_post( intval( $_GET['post'] ) );
		if ( isset( $post->post_status ) && $post->post_status == 'trash' ) {
			$post_type_object = get_post_type_object( $post->post_type );
			$delete_post_url  = get_delete_post_link( $post->ID, '', true );

			if ( is_string( $delete_post_url ) ) {
				$delete_post_link  = '<a href="' . esc_url( $delete_post_url ) . '">' . esc_html__( 'delete it permanently', 'sitepress' ) . '</a>';
				$restore_post_link = '<a href="' . wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ) . '">' . esc_html__( 'restore', 'sitepress' ) . '</a>';
				$ret               = '<p>' . sprintf( esc_html__( 'This translation is currently in the trash. You need to either %1$s or %2$s it in order to continue.' ), $delete_post_link, $restore_post_link );

				wp_die( $ret );
			}
		}
	}
}

/**
 * Build or update duplicated posts from a master post.
 *
 * @param  string $master_post_id The ID of the post to duplicate from. Master post doesn't need to be in the default language.
 *
 * @uses       SitePress
 * @uses       TranslationManagement
 * @since      unknown
 * @deprecated 3.2 use 'wpml_admin_make_duplicates' action instead
 */
function icl_makes_duplicates( $master_post_id ) {
	wpml_admin_make_post_duplicates_action( (int) $master_post_id );
}

/**
 * Build or update duplicated posts from a master post.
 * To be used only for admin backend actions
 *
 * @param int $master_post_id     The ID of the post to duplicate from.
 *                                The ID can be that of a post, page or custom post
 *                                Master post doesn't need to be in the default language.
 *
 * @see   $iclTranslationManagement in \SitePress:: __construct
 *
 * @uses  SitePress
 * @uses  TranslationManagement
 * @since 3.2
 * @uses  \SitePress::api_hooks
 */
function wpml_admin_make_post_duplicates_action( $master_post_id ) {
	$post      = get_post( $master_post_id );
	$post_type = $post->post_type;

	if ( $post->post_status == 'auto-draft' || $post->post_type == 'revision' ) {
		return;
	}

	global $sitepress;
	$iclTranslationManagement = wpml_load_core_tm();
	if ( $sitepress->is_translated_post_type( $post_type ) ) {
		$iclTranslationManagement->make_duplicates_all( $master_post_id );
	}
}

/**
 * Build duplicated posts from a master post only in case of the duplicate not being present at the time.
 *
 * @param  string $master_post_id The ID of the post to duplicate from. Master post doesn't need to be in the default language.
 *
 * @uses       SitePress
 * @since      unknown
 * @deprecated 3.2 use 'wpml_make_post_duplicates' action instead
 */
function icl_makes_duplicates_public( $master_post_id ) {
	wpml_make_post_duplicates_action( (int) $master_post_id );
}

/**
 * Build duplicated posts from a master post only in case of the duplicate not being present at the time.
 *
 * @param int $master_post_id     The ID of the post to duplicate from.
 *                                Master post doesn't need to be in the default language.
 *
 * @uses       SitePress
 * @since      3.2
 * @uses       \SitePress::api_hooks
 */
function wpml_make_post_duplicates_action( $master_post_id ) {

	global $sitepress;

	$master_post = get_post( $master_post_id );

	if ( 'auto-draft' === $master_post->post_status || 'revision' === $master_post->post_type ) {
		return;
	}

	$active_langs = $sitepress->get_active_languages();

	foreach ( $active_langs as $lang_to => $one ) {

		$trid      = $sitepress->get_element_trid( $master_post->ID, 'post_' . $master_post->post_type );
		$lang_from = $sitepress->get_source_language_by_trid( $trid );

		if ( $lang_from == $lang_to ) {
			continue;
		}

		$sitepress->make_duplicate( $master_post_id, $lang_to );
	}
}

/**
 * Wrapper function for deprecated like_escape() and recommended wpdb::esc_like()
 *
 * @global wpdb  $wpdb
 *
 * @param string $text
 *
 * @return string
 */
function wpml_like_escape( $text ) {
	global $wpdb;

	if ( method_exists( $wpdb, 'esc_like' ) ) {
		return $wpdb->esc_like( $text );
	}

	/** @noinspection PhpDeprecationInspection */

	return like_escape( $text );
}

function icl_do_not_promote() {
	return defined( 'ICL_DONT_PROMOTE' ) && ICL_DONT_PROMOTE;
}

/**
 * @param string $time
 *
 * @return string
 */
function icl_convert_to_user_time( $time ) {

	// offset between server time and user time in seconds
	$time_offset = get_option( 'gmt_offset' ) * 3600;
	$local_time  = __( 'Last Update Time could not be determined', 'sitepress' );

	try {
		// unix time stamp in server time
		$creation_time = strtotime( $time );
		// creating dates before 2014 are impossible
		if ( $creation_time !== false ) {
			$local_time = date( 'Y-m-d H:i:s', $creation_time + $time_offset );
		}
	} catch ( Exception $e ) {
		// Ignoring the exception, as we already set the default value in $local_time
	}

	return $local_time;
}

/**
 * Check if given language is activated
 *
 * @global sitepress $sitepress
 *
 * @param string $language 2 letters language code
 *
 * @return boolean
 * @since      unknown
 * @deprecated 3.2 use 'wpml_language_is_active' filter instead
 */
function icl_is_language_active( $language ) {
	global $sitepress;

	$active_languages = $sitepress->get_active_languages();

	return isset( $active_languages[ $language ] );
}

/**
 * Checks if given language is enabled
 *
 * @param mixed      $empty_value   This is normally the value the filter will be modifying.
 *                                  We are not filtering anything here therefore the NULL value
 *                                  This for the filter function to actually receive the full argument list:
 *                                  apply_filters('wpml_language_is_active', '', $language_code);
 * @param string     $language_code The language code to check Accepts a 2-letter language code
 *
 * @return boolean
 * @global sitepress $sitepress
 *
 * @since 3.2
 * @uses  \SitePress::api_hooks
 */
function wpml_language_is_active_filter( $empty_value, $language_code ) {
	global $sitepress;

	return $sitepress->is_active_language( $language_code );
}

/**
 * @param string $url url either with or without schema
 *                    Removes the subdirectory in which WordPress is installed from a url.
 *                    If WordPress is not installed in a subdirectory, then the input is returned unaltered.
 *
 * @return string the url input without the blog's subdirectory. Potentially existing schemata on the input are kept intact.
 */
function wpml_strip_subdir_from_url( $url ) {
	/** @var WPML_URL_Converter $wpml_url_converter */
	global $wpml_url_converter;

	$subdir       = wpml_parse_url( $wpml_url_converter->get_abs_home(), PHP_URL_PATH );
	$subdir_slugs = ! empty( $subdir ) ? array_values( array_filter( explode( '/', $subdir ) ) ) : [''];

	$url_path_expl = explode( '/', preg_replace( '#^(http|https)://#', '', $url ) );
	array_shift( $url_path_expl );
	$url_slugs        = array_values( array_filter( $url_path_expl ) );
	$url_slugs_before = $url_slugs;
	$url_slugs        = array_diff_assoc( $url_slugs, $subdir_slugs );
	$url              = str_replace( '/' . join( '/', $url_slugs_before ), '/' . join( '/', $url_slugs ), $url );

	return untrailingslashit( $url );
}

/**
 * Changes array of items into string of items, separated by comma and sql-escaped
 *
 * @see https://coderwall.com/p/zepnaw
 * @global wpdb       $wpdb
 *
 * @param mixed|array $items  item(s) to be joined into string
 * @param string      $format %s or %d
 *
 * @return string Items separated by comma and sql-escaped
 */
function wpml_prepare_in( $items, $format = '%s' ) {
	global $wpdb;

	$items    = (array) $items;
	$how_many = count( $items );
	if ( $how_many > 0 ) {
		$placeholders    = array_fill( 0, $how_many, $format );
		$prepared_format = implode( ',', $placeholders );
		$prepared_in     = $wpdb->prepare( $prepared_format, $items );
	} else {
		$prepared_in = '';
	}

	return $prepared_in;
}

function is_not_installing_plugins() {
	global $sitepress;

	$checked = isset( $_REQUEST['checked'] ) ? (array) $_REQUEST['checked'] : array();

	if ( ! isset( $_REQUEST['action'] ) ) {
		return true;
	} elseif ( $_REQUEST['action'] != 'activate' && $_REQUEST['action'] != 'activate-selected' ) {
		return true;
	} elseif ( ( ! isset( $_REQUEST['plugin'] ) || $_REQUEST['plugin'] != WPML_PLUGIN_FOLDER . '/' . basename( __FILE__ ) ) && ! in_array( WPML_PLUGIN_FOLDER . '/' . basename( __FILE__ ), $checked ) ) {
		return true;
	} elseif ( in_array( WPML_PLUGIN_FOLDER . '/' . basename( __FILE__ ), $checked ) && ! isset( $sitepress ) ) {
		return true;
	}

	return false;
}

function wpml_mb_strtolower( $string ) {
	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $string );
	}

	return strtolower( $string );
}

function wpml_mb_strpos( $haystack, $needle, $offset = 0 ) {
	if ( function_exists( 'mb_strpos' ) ) {
		return mb_strpos( $haystack, $needle, $offset );
	}

	return strpos( $haystack, $needle, $offset );
}

function wpml_set_plugin_as_inactive() {
	global $icl_plugin_inactive;
	if ( ! defined( 'ICL_PLUGIN_INACTIVE' ) ) {
		define( 'ICL_PLUGIN_INACTIVE', true );
	}
	$icl_plugin_inactive = true;
}

function wpml_version_is( $version_to_check, $comparison = '==' ) {
	return version_compare( ICL_SITEPRESS_VERSION, $version_to_check, $comparison ) && function_exists( 'wpml_site_uses_icl' );
}

/**
 * Interrupts the plugin activation process if the WPML Core Plugin could not be activated
 */
function icl_suppress_activation() {
	$active_plugins    = get_option( 'active_plugins' );
	$icl_sitepress_idx = array_search( WPML_PLUGIN_BASENAME, $active_plugins );
	if ( false !== $icl_sitepress_idx ) {
		unset( $active_plugins[ $icl_sitepress_idx ] );
		update_option( 'active_plugins', $active_plugins );
		unset( $_GET['activate'] );
		$recently_activated = get_option( 'recently_activated' );
		if ( ! isset( $recently_activated[ WPML_PLUGIN_BASENAME ] ) ) {
			$recently_activated[ WPML_PLUGIN_BASENAME ] = time();
			update_option( 'recently_activated', $recently_activated );
		}
	}
}

/**
 * @param SitePress $sitepress
 */
function activate_installer( $sitepress = null ) {
	// installer hook - start
	include_once WPML_PLUGIN_PATH . '/vendor/otgs/installer/loader.php'; // produces global variable $wp_installer_instance
	$args = array(
		'plugins_install_tab' => 1,
	);

	if ( $sitepress ) {
		$args['site_key_nags'] = array(
			array(
				'repository_id' => 'wpml',
				'product_name'  => 'WPML',
				'condition_cb'  => array( $sitepress, 'setup' ),
			),
		);
	}
	/**
	 * @var WP_Installer $wp_installer_instance
	 */
	/** @phpstan-ignore-next-line */
	WP_Installer_Setup( $wp_installer_instance, $args );
	// installer hook - end
}

function wpml_missing_filter_input_notice() {
	?>
	<div class="message error">
		<h3><?php esc_html_e( "WPML can't be functional because it requires a disabled PHP extension!", 'sitepress' ); ?></h3>

		<p><?php esc_html_e( 'To ensure and improve the security of your website, WPML makes use of the ', 'sitepress' ); ?><a href="http://php.net/manual/en/book.filter.php">PHP Data Filtering</a> extension.<br><br>
			<?php
			esc_html_e(
				'The filter extension is enabled by default as of PHP 5.2.0. Before this time an experimental PECL extension was
            used, however, the PECL version is no longer recommended to be used or updated. (source: ',
				'sitepress'
			);
			?>
			<a href="http://php.net/manual/en/filter.installation.php">PHP Manual Function Reference Variable and
				Type Related Extensions Filter
				Installing/Configuring</a>)<br>
			<br>
			<?php esc_html_e( 'The filter extension is enabled by default as of PHP 5.2, therefore it must have been disabled by either you or your host.', 'sitepress' ); ?>
			<br><?php esc_html_e( "To enable it, either you or your host will need to open your website's php.ini file and either:", 'sitepress' ); ?><br>
		<ol>
			<li><?php esc_html_e( "Remove the 'filter_var' string from the 'disable_functions' directive or...", 'sitepress' ); ?>
			</li>
			<li><?php esc_html_e( 'Add the following line:', 'sitepress' ); ?> <code class="inline-code">extension=filter.so</code></li>
		</ol>
		<?php
		$ini_location = php_ini_loaded_file();
		if ( $ini_location !== false ) {
			?>
			<strong><?php esc_html_e( 'Your php.ini file is located at', 'sitepress' ) . ' ' . esc_html( $ini_location ); ?>.</strong>
			<?php
		}
		?>
	</div>
	<?php
}

function repair_el_type_collate() {
	global $wpdb;

	$correct_collate = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT collation_name
	          FROM information_schema.COLUMNS
	          WHERE TABLE_NAME = '%s'
	                AND COLUMN_NAME = 'post_type'
	                    AND table_schema = (SELECT DATABASE())
	          LIMIT 1",
			$wpdb->posts
		)
	);

	// translations
	$table_name = $wpdb->prefix . 'icl_translations';
	$sql        = $wpdb->prepare(
		"ALTER TABLE `$table_name` CHANGE `element_type` `element_type` VARCHAR( 36 ) NOT NULL DEFAULT 'post_post' COLLATE %s",
		$correct_collate
	);

	if ( $wpdb->query( $sql ) === false ) {
		throw new Exception( $wpdb->last_error );
	}
}

/**
 * Wrapper for `parse_url` using `wp_parse_url`
 *
 * @param string $url
 * @param int    $component
 *
 * @return array|string|int|null
 *
 * @phpstan-return ($component is -1
 *  ? array|null
 *  : ($component is PHP_URL_PORT ? int|null : string|null)
 *  )
 */
function wpml_parse_url( $url, $component = -1 ) {
	$ret = null;

	$component_map = array(
		PHP_URL_SCHEME   => 'scheme',
		PHP_URL_HOST     => 'host',
		PHP_URL_PORT     => 'port',
		PHP_URL_USER     => 'user',
		PHP_URL_PASS     => 'pass',
		PHP_URL_PATH     => 'path',
		PHP_URL_QUERY    => 'query',
		PHP_URL_FRAGMENT => 'fragment',
	);

	if ( $component === -1 ) {
		$ret = wp_parse_url( $url );
	} elseif ( isset( $component_map[ $component ] ) ) {
		$key    = $component_map[ $component ];
		$parsed = wp_parse_url( $url );
		$ret    = isset( $parsed[ $key ] ) ? $parsed[ $key ] : null;
	}

	return $ret;
}

/**
 * Wrapper function to prevent ampersand to be encoded (depending on some PHP versions)
 *
 * @link http://php.net/manual/en/function.http-build-query.php#102324
 *
 * @param array|object $query_data
 *
 * @return string
 */
function wpml_http_build_query( $query_data ) {
	return http_build_query( $query_data, '', '&' );
}

/**
 * @param array $array
 * @param int   $sort_flags
 *
 * @uses \wpml_array_unique_fallback
 *
 * @return array
 */
function wpml_array_unique( $array, $sort_flags = SORT_REGULAR ) {
	if ( version_compare( phpversion(), '5.2.9', '>=' ) ) {
		// phpcs:disable PHPCompatibility.FunctionUse.NewFunctionParameters.array_unique_sort_flagsFound -- This statement is preceded by a version check
		return array_unique( $array, $sort_flags );
		// phpcs:enable PHPCompatibility.FunctionUse.NewFunctionParameters.array_unique_sort_flagsFound
	}

	return wpml_array_unique_fallback( $array, true );
}

/**
 * @param array<mixed> $array
 * @param bool         $keep_key_assoc
 *
 * @return array
 * @see \wpml_array_unique
 */
function wpml_array_unique_fallback( $array, $keep_key_assoc ) {
	$duplicate_keys = array();
	$tmp            = array();

	foreach ( $array as $key => $val ) {
		// convert objects to arrays, in_array() does not support objects
		if ( is_object( $val ) ) {
			$val = (array) $val;
		}

		if ( ! in_array( $val, $tmp ) ) {
			$tmp[] = $val;
		} else {
			$duplicate_keys[] = $key;
		}
	}

	foreach ( $duplicate_keys as $key ) {
		unset( $array[ $key ] );
	}

	return $keep_key_assoc ? $array : array_values( $array );
}

/**
 * @return bool
 */
function wpml_is_rest_request() {
	return make( WPML_REST_Request_Analyze::class )->is_rest_request();
}

/**
 * @return bool
 */
function wpml_is_rest_enabled() {
	return make( \WPML\Core\REST\Status::class )->isEnabled();
}

function wpml_is_cli() {
	return defined( 'WP_CLI' ) && WP_CLI;
}

function wpml_sticky_post_sync( SitePress $sitepress = null ) {
	static $instance;

	if ( ! $instance ) {
		global $wpml_post_translations;

		if ( ! $sitepress ) {
			global $sitepress;
		}

		$instance = new WPML_Sticky_Posts_Sync(
			$sitepress,
			$wpml_post_translations,
			new WPML_Sticky_Posts_Lang_Filter(
				$sitepress,
				$wpml_post_translations
			)
		);
	}

	return $instance;
}

/**
 * @return WP_Filesystem_Direct
 */
function wpml_get_filesystem_direct() {
	static $instance;

	if ( ! $instance ) {
		$wp_api   = new WPML_WP_API();
		$instance = $wp_api->get_wp_filesystem_direct();
	}

	return $instance;
}

/**
 * @param array       $postarray It will be escaped inside the function
 * @param string|null $lang
 * @param bool        $wp_error
 *
 * @return int|\WP_Error
 */
function wpml_update_escaped_post( array $postarray, $lang = null, $wp_error = false ) {
	return wpml_get_create_post_helper()->insert_post( $postarray, $lang, $wp_error );
}

/**
 * @param string $group
 *
 * @return WPML_WP_Cache
 */
function wpml_get_cache( $group = '' ) {
	return new WPML_WP_Cache( $group );
}


if ( ! function_exists( 'wpml_is_ajax' ) ) {
	/**
	 * wpml_is_ajax - Returns true when the page is loaded via ajax.
	 *
	 * @since  3.1.5
	 *
	 * @return bool
	 */
	function wpml_is_ajax() {
		if ( defined( 'DOING_AJAX' ) ) {
			return true;
		}

		return ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && wpml_mb_strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' ) ? true : false;
	}
}

if ( ! function_exists( 'wpml_get_flag_file_name' ) ) {
	/**
	 * wpml_get_flag_file_name - Returns the SVG or PNG flag file name based on language code if it exists in '/res/flags/' dir.
	 *
	 * @param $lang_code
	 *
	 * @return string
	 * @since 4.6.0
	 *
	 */
	function wpml_get_flag_file_name( $lang_code ) {
		if ( file_exists( WPML_PLUGIN_PATH . '/res/flags/' . $lang_code . '.svg' ) ) {
			$file = $lang_code . '.svg';
		} elseif ( file_exists( WPML_PLUGIN_PATH . '/res/flags/' . $lang_code . '.png' ) ) {
			$file = $lang_code . '.png';
		} elseif ( file_exists( WPML_PLUGIN_PATH . '/res/flags/nil.svg' ) ) {
			$file = 'nil.svg';
		} else {
			$file = 'nil.png';
		}

		return $file;
	}
}


function wpml_is_st_loaded(): bool {
	return defined( 'WPML_ST_VERSION' );
}