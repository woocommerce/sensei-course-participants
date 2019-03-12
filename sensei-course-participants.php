<?php
/*
 * Plugin Name: Sensei Course Participants
 * Version: 1.1.3
 * Plugin URI: https://woocommerce.com/products/sensei-course-participants/
 * Description: Displays the number of learners taking a course, and a widget with a list of those learners.
 * Author: Automattic
 * Author URI: https://automattic.com/
 * Requires at least: 3.8
 * Tested up to: 4.1
 * Text Domain: sensei-course-participants
 * Domain Path: /languages/
 *
 * @package WordPress
 * @author Automattic
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( __DIR__ . '/woo-includes/woo-functions.php' );
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'f6479a8a3a01ac11794f32be22b0682f', 435834 );

require_once dirname( __FILE__ ) . '/includes/class-sensei-course-participants-dependency-checker.php';

if ( ! Sensei_Course_Participants_Dependency_Checker::are_dependencies_met() ) {
	return;
}

require_once( dirname( __FILE__ ) . '/includes/class-sensei-course-participants.php' );

/**
 * Returns the main instance of Sensei_Course_Participants to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Sensei_Course_Participants
 */
function Sensei_Course_Participants() {
	return Sensei_Course_Participants::instance( __FILE__, '1.1.3' );
}

Sensei_Course_Participants();
