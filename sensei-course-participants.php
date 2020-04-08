<?php
/*
 * Plugin Name: Sensei Course Participants
 * Version: 2.0.1
 * Plugin URI: https://woocommerce.com/products/sensei-course-participants/
 * Description: Increase course enrolments by showing site visitors just how popular your courses are.
 * Author: Automattic
 * Author URI: https://automattic.com/
 * Requires at least: 4.1
 * Tested up to: 5.4
 * Requires PHP: 5.6
 * Text Domain: sensei-course-participants
 * Domain Path: /languages/
 * Woo: 435834:f6479a8a3a01ac11794f32be22b0682f
 *
 * @package WordPress
 * @author Automattic
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SENSEI_COURSE_PARTICIPANTS_VERSION', '2.0.1' );
define( 'SENSEI_COURSE_PARTICIPANTS_PLUGIN_FILE', __FILE__ );
define( 'SENSEI_COURSE_PARTICIPANTS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once dirname( __FILE__ ) . '/includes/class-sensei-course-participants-dependency-checker.php';

if ( ! Sensei_Course_Participants_Dependency_Checker::are_system_dependencies_met() ) {
	return;
}

require_once dirname( __FILE__ ) . '/includes/class-sensei-course-participants.php';

// Load the plugin after all the other plugins have loaded.
add_action( 'plugins_loaded', array( 'Sensei_Course_Participants', 'init' ), 5 ) ;

Sensei_Course_Participants::instance();
