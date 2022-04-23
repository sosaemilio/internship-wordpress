<?php
/**
 * Plugin Name: Panorama - WordPress Project Management Plugin
 * Plugin URI: https://www.projectpanorama.com
 * Description: WordPress Project Management and Client Dashboard Plugin
 * Version: 1.4.6
 * Author: 37 MEDIA
 * Author URI: https://www.projectpanorama.com
 * License: GPL2
 * Text Domain: psp_projects
 */

/**
 * If Panorama Pro isn't enabled...
 */


 $constants = array(
     'PROJECT_PANORAMA_URI'   => plugins_url( '', __FILE__ ),
     'PROJECT_PANORAMA_DIR'   => __DIR__,
     'PSP_VER'                => '1.4.6',
     'PSP_LITE_USE_TASKS'     => true,
 );

 foreach( $constants as $constant => $val ) {
     if( !defined( $constant ) ) define( $constant, $val );
 }

if( !function_exists( 'psp_initalize_application' ) ) {

    include_once( 'lib/psp-init.php' );
    include_once( 'psp-license.php' );

}


// ================
// = Localization =
// ================
add_action('plugins_loaded', 'psp_localize_init');
function psp_localize_init() {
    load_plugin_textdomain('psp_projects', false, dirname(plugin_basename(__FILE__)) . '/languages');
}


// ============================
// = Plugin Update Management =
// ============================
function psp_check_database() {

    $psp_database_version = get_option('psp_database_version');

    if( $psp_database_version != '2' ) {
        psp_database_notice();
    }

}

/**
 * Nag to pay the bills
 *
 *
 * @param
 * @return NULL
 **/

// add_action('admin_notices', 'psp_lite_notice');
function psp_lite_notice() {

    if( get_option( 'psp_lite_notice' ) != 1 ) { ?>
        <div class="updated">

            <p><img src="<?php echo PROJECT_PANORAMA_URI; ?>/assets/images/panorama-logo.png" alt="Project Panorama"></p>
            <p><?php esc_html_e( 'Like Project Panorama Lite? We have a full featured premium version with front end task completion, automatic notifications and alot more features!', 'psp_projects' ); ?> <a href="https://www.projectpanorama.com/?utm=admin-notice" target="_new"><?php _e('Check it out here.','psp_projects'); ?> | <a href="<?php echo site_url(); ?>/wp-admin/index.php?psp_no_lite_notice=0"><?php esc_html_e( 'No thanks!', 'psp_projects' ); ?></a>.</p>

        </div>
    <?php
	}

}

add_action( 'admin_init', 'psp_check_lite_notice' );
function psp_check_lite_notice() {

    if ( isset($_GET[ 'psp_no_lite_notice' ] ) && '0' == $_GET[ 'panorama_ignore_db'] ) {
        update_option( 'psp_no_lite_notice', 1 );
    }

}
