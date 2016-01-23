<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class Sensei_Course_Participants {

	/**
	 * The single instance of Sensei_Course_Participants.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The version number.
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $_version;

	/**
	 * The token.
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $_token;

	/**
	 * The main plugin file.
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $file;

	/**
	 * The main plugin directory.
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $dir;

	/**
	 * The plugin assets directory.
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $assets_dir;

	/**
	 * The plugin assets URL.
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $assets_url;

	/**
	 * Suffix for Javascripts.
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $script_suffix;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct ( $file, $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token = 'sensei_course_participants';

		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Load frontend JS & CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Handle localisation
		$this->load_plugin_textdomain ();
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		// Display course participants on course loop and single course
		add_action( 'sensei_single_course_content_inside_before', array( $this, 'display_course_participant_count' ), 15 );
		add_action( 'sensei_course_content_inside_before', array( $this, 'display_course_participant_count' ), 15, 1 );

		// Include Widget
		add_action( 'widgets_init', array( $this, 'include_widgets' ) );
	} // End __construct()

	/**
	 * Display course participants on course loop and single course
	 * @access  public
	 * @since   1.0.0
     * @param WP_Post | integer $post_item
     * @return void
	 */
	public function display_course_participant_count( $post_item = 0 ) {

        global $post, $wp_the_query;

        if( isset( $wp_the_query->queried_object->ID )
            && 'page' == get_post_type( $wp_the_query->queried_object->ID) ){

            return;

        }

		$post_id = 0;

		if( is_singular( 'course' ) ) {

            $post_id = $post->ID;

		} else if( isset( $post_item ) && is_object( $post_item ) ) {

			$post_id = absint( $post_item->ID );

        } elseif( is_numeric( $post_item ) && $post_item > 0 ){

            $post_id = $post_item;

        }else{

            return;

        }


		$learner_count = $this->get_course_participant_count( $post_id );

		echo '<p class="sensei-course-participants">' . sprintf( __( '%s %s taking this course', 'sensei-course-participants' ), '<strong>' . intval( $learner_count ) . '</strong>', _n( 'learner', 'learners', intval( $learner_count ), 'sensei-course-participants' ) ) . '</p>' . "\n";
	}

	/**
	 * Get the number of learners taking the current course
	 * @access  public
	 * @since   1.0.0
	 * @return integer
	 */
	public function get_course_participant_count( $post_id = 0 ) {

		if( ! $post_id ) {
			return 0;
		}

		$activity_args = array(
			'post_id' => $post_id,
			'type' => 'sensei_course_status',
			'count' => true,
			'number' => 0,
			'offset' => 0,
			'status' => 'any',
		);

		$course_learners = WooThemes_Sensei_Utils::sensei_check_for_activity( $activity_args, false );

		return $course_learners;
	}

	/**
	 * Get an array of learners taking the course
	 * @since  1.0.0
	 * @param  string 	$order 		Order direction
	 * @param  string 	$orderby 	How to determine the order of learners
	 * @return array 	$learners 	The array of learners
	 */
	public function get_course_learners ( $order, $orderby ) {

		$activity_args = array(
			'post_id' => $this->get_course_id(),
			'type' => 'sensei_course_status',
			'number' => 0,
			'offset' => 0,
			'status' => 'any',
		);

		$users = WooThemes_Sensei_Utils::sensei_check_for_activity( $activity_args, true );
		if ( !is_array($users) ) {
			$users = array( $users );
		}
		$total = count( $users );

		// Don't run the query if there are no users taking this course.
		if ( empty( $users ) ) {
			return false;
		}

		// 'rand' can't be used in WP_User_Query, so save the setting and change it to 'user_registered'
		// We can randomize the array after running the query
		if ( isset( $orderby ) && 'rand' == $orderby ) {
			$orderwas = 'rand';
			$orderby = 'user_registered';
		}

		$user_ids = array();
		foreach( $users as $user ) {
			$user_ids[] = $user->user_id;
		}

		$args_array = array(
			'number' => $total,
			'include' => $user_ids,
			'orderby' => $orderby,
			'order' => $order,
			'fields' => 'all_with_meta'
		);

		$learners_search = new WP_User_Query( $args_array );
		$learners = $learners_search->get_results();

		// Shuffle the learners if the selected order was random
		if( isset( $orderwas ) && 'rand' == $orderwas ) {
			shuffle( $learners );
		}

		return $learners;
	} // End get_course_learners()

	/**
	 * Get the id of the course being viewed from a course, module, lesson or quiz page.
	 * @since  1.0.0
	 * @return Integer 	The course ID
	 */
	public function get_course_id () {
		global $post;

		$course_id = 0;

		if( is_singular( 'lesson' ) || is_singular( 'quiz' ) ) {
			$course_id = get_post_meta( $post->ID, '_lesson_course', true );
		} elseif ( is_singular( 'course' ) ) {
			$course_id = $post->ID;
		} elseif ( is_tax( 'module' ) ) {
			// Find the course ID for the current module from the GET variable
			if ( isset( $_GET['course_id'] ) && 0 < intval( $_GET['course_id'] ) ) {
    			$course_id = intval( $_GET['course_id'] );
    		}
		}

		$course_id = intval( $course_id );
		return $course_id;
	} // End get_course_id()

	/**
	 * Include widgets
	 */
	public function include_widgets() {
		include_once( 'class-sensei-course-participants-widget.php' );
		register_widget( 'Sensei_Course_Participants_Widget' );
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_styles () {

		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );

	} // End enqueue_styles()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_scripts () {

		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );
		wp_enqueue_script( $this->_token . '-frontend' );

	} // End enqueue_scripts()

	/**
	 * Load plugin localisation.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'sensei-course-participants' , false , dirname( plugin_basename( $this->file ) ) . '/languages/' );
	} // End load_localisation()

	/**
	 * Load plugin textdomain.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function load_plugin_textdomain () {
	    $domain = 'sensei-course-participants';

	    $locale = apply_filters( 'plugin_locale' , get_locale() , $domain );

	    load_textdomain( $domain , WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	    load_plugin_textdomain( $domain , FALSE , dirname( plugin_basename( $this->file ) ) . '/languages/' );
	} // End load_plugin_textdomain

	/**
	 * Main Sensei_Course_Participants Instance
	 *
	 * Ensures only one instance of Sensei_Course_Participants is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see Sensei_Course_Participants()
	 * @return Main Sensei_Course_Participants instance
	 */
	public static function instance ( $file, $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) )
			self::$_instance = new self( $file, $version );
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup()

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	}

}
