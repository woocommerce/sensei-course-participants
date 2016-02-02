<?php
/*
 * Plugin Name: Sensei Course Participants
 * Version: 1.1.3
 * Plugin URI: http://www.woothemes.com/products/sensei-course-participants/
 * Description: Displays the number of learners taking a course, and a widget with a list of those learners.
 * Author: WooThemes
 * Author URI: http://www.woothemes.com/
 * Requires at least: 3.8
 * Tested up to: 4.1
 * Text Domain: sensei-course-participants
 * Domain Path: /languages/
 *
 * @package WordPress
 * @author WooThemes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'f6479a8a3a01ac11794f32be22b0682f', 435834 );

/**
 * Functions used by plugins
 */
if ( ! class_exists( 'WooThemes_Sensei_Dependencies' ) ) {
	require_once 'woo-includes/class-woothemes-sensei-dependencies.php';
}

/**
 * Sensei Detection
 */
if ( ! function_exists( 'is_sensei_active' ) ) {
  function is_sensei_active() {
    return WooThemes_Sensei_Dependencies::sensei_active_check();
  }
}

if( is_sensei_active() ) {

	require_once( 'includes/class-sensei-course-participants.php' );

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
}
