<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sensei_Course_Participants {
	/**
	 * The single instance of Sensei_Course_Participants
	 *
	 * @var    object
	 * @access private
	 * @static
	 * @since  1.0.0
	 */
	private static $_instance = null;

	/**
	 * The version number
	 *
	 * @var    string
	 * @access private
	 * @since  1.0.0
	 */
	private $_version;

	/**
	 * The token.
	 *
	 * @var    string
	 * @access private
	 * @since  1.0.0
	 */
	private $_token;

	/**
	 * The plugin assets directory.
	 *
	 * @var    string
	 * @access private
	 * @since  1.0.0
	 */
	private $assets_dir;

	/**
	 * The plugin assets URL.
	 *
	 * @var    string
	 * @access private
	 * @since  1.0.0
	 */
	private $assets_url;

	/**
	 * Suffix for Javascripts.
	 *
	 * @var    string
	 * @access private
	 * @since  1.0.0
	 */
	private $script_suffix;

	/**
	 * Set the default properties and hooks methods.
	 *
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct() {
		$this->_version      = SENSEI_COURSE_PARTICIPANTS_VERSION;
		$this->_token        = 'sensei_course_participants';
		$this->assets_dir    = trailingslashit( dirname( SENSEI_COURSE_PARTICIPANTS_PLUGIN_FILE ) ) . 'assets';
		$this->assets_url    = esc_url( trailingslashit( plugins_url( '/assets/', SENSEI_COURSE_PARTICIPANTS_PLUGIN_FILE ) ) );
		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$this->load_plugin_textdomain();

		register_activation_hook( SENSEI_COURSE_PARTICIPANTS_PLUGIN_FILE, array( $this, 'install' ) );

	}

	/**
	 * Set up all actions and filters.
	 */
	public static function init() {
		$instance = self::instance();
		add_action( 'init', array( $instance, 'load_localisation' ), 0 );

		if ( ! Sensei_Course_Participants_Dependency_Checker::are_plugin_dependencies_met() ) {
			return;
		}

		/**
		 * Returns the main instance of Sensei_Course_Participants to prevent the need to use globals.
		 *
		 * @since  1.0.0
		 * @return object Sensei_Course_Participants
		 */
		function Sensei_Course_Participants() {
			return Sensei_Course_Participants::instance();
		}

		// Load frontend JS & CSS
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_scripts' ), 10 );


		// Display course participants on course loop and single course
		add_action( 'sensei_single_course_content_inside_before', array( $instance, 'display_course_participant_count' ), 15 );
		add_action( 'sensei_course_content_inside_before', array( $instance, 'display_course_participant_count' ), 15, 1 );

		// Include Widget
		add_action( 'widgets_init', array( $instance, 'include_widgets' ) );
	}

	/**
	 * Display course participants on course loop and single course
	 *
	 * @since  1.0.0
	 * @param  WP_Post | integer $post_item
	 * @return void
	 */
	public function display_course_participant_count( $post_item = 0 ) {
		global $post, $wp_the_query;

		if ( isset( $wp_the_query->queried_object->ID ) && 'page' === get_post_type( $wp_the_query->queried_object->ID ) ){
			return;
		}

		$post_id = 0;

		if ( is_singular( 'course' ) ) {
			$post_id = $post->ID;
		} elseif ( isset( $post_item ) && is_object( $post_item ) ) {
			$post_id = absint( $post_item->ID );
		} elseif ( is_numeric( $post_item ) && $post_item > 0 ) {
			$post_id = absint( $post_item );
		} else {
			return;
		}

		$learner_count = $this->get_course_participant_count( $post_id );

		echo '<p class="sensei-course-participants">';

		printf(
			esc_html__( '%s %s taking this course', 'sensei-course-participants' ),
			'<strong>' . intval( $learner_count ) . '</strong>',
			esc_html( _n( 'learner', 'learners', intval( $learner_count ), 'sensei-course-participants' ) )
		);

		echo "</p>\n";
	}

	/**
	 * Get the number of learners taking the current course
	 *
	 * @since  1.0.0
	 * @return integer
	 */
	public function get_course_participant_count( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			return 0;
		}

		$activity_args = array(
			'post_id' => absint( $post_id ),
			'type'    => 'sensei_course_status',
			'count'   => true,
			'number'  => 0,
			'offset'  => 0,
			'status'  => 'any',
		);

		$course_learners = WooThemes_Sensei_Utils::sensei_check_for_activity( $activity_args, false );

		return $course_learners;
	}

	/**
	 * Get an array of learners taking the course
	 *
	 * @since  1.0.0
	 * @param  string $order    Order direction
	 * @param  string $orderby  How to determine the order of learners
	 * @return array  $learners The array of learners
	 */
	public function get_course_learners( $order, $orderby ) {
		$activity_args = array(
			'post_id' => absint( $this->get_course_id() ),
			'type'    => 'sensei_course_status',
			'number'  => 0,
			'offset'  => 0,
			'status'  => 'any',
		);

		$users = WooThemes_Sensei_Utils::sensei_check_for_activity( $activity_args, true );

		if ( ! is_array( $users ) ) {
			$users = array( $users );
		}

		$total = count( $users );

		// Don't run the query if there are no users taking this course.
		if ( empty( $users ) ) {
			return false;
		}

		// 'rand' can't be used in WP_User_Query, so save the setting and change it to 'user_registered'
		// We can randomize the array after running the query
		if ( isset( $orderby ) && 'rand' === $orderby ) {
			$orderwas = 'rand';
			$orderby  = 'user_registered';
		}

		$user_ids = array();
		foreach ( $users as $user ) {
			$user_ids[] = absint( $user->user_id );
		}

		$args_array = array(
			'number'  => $total,
			'include' => $user_ids,
			'orderby' => $orderby,
			'order'   => $order,
			'fields'  => 'all_with_meta',
		);

		$learners_search = new WP_User_Query( $args_array );
		$learners        = $learners_search->get_results();

		// Shuffle the learners if the selected order was random
		if ( isset( $orderwas ) && 'rand' === $orderwas ) {
			shuffle( $learners );
		}

		return $learners;
	}

	/**
	 * Get the id of the course being viewed from a course, module, lesson or quiz page.
	 *
	 * @since  1.0.0
	 * @return int   The course ID
	 */
	public function get_course_id() {
		global $post;

		$course_id = 0;

		if ( is_singular( 'lesson' ) || is_singular( 'quiz' ) ) {
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
	}

	/**
	 * Include widgets
	 */
	public function include_widgets() {
		include_once( __DIR__ . '/class-sensei-course-participants-widget.php' );
		register_widget( 'Sensei_Course_Participants_Widget' );
	}

	/**
	 * Load frontend CSS.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	}

	/**
	 * Load frontend Javascript.
	 *
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {

		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery' ), $this->_version );

		wp_localize_script( $this->_token . '-frontend', $this->_token . '_frontend', array(
			'view_all' => esc_html__( 'View All', 'sensei-course-participants' ),
			'close'    => esc_html__( 'Close', 'sensei-course-participants' ),
		) );

		wp_enqueue_script( $this->_token . '-frontend' );

	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$domain = 'sensei-course-participants';

		$locale = apply_filters( 'plugin_locale' , get_locale() , $domain );

		load_textdomain( $domain , WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
	}

	/**
	 * Load plugin localisation.
	 *
	 * @since   2.0.0
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'sensei-course-participants', false , dirname( SENSEI_COURSE_PARTICIPANTS_PLUGIN_BASENAME ) . '/languages/' );
	}

	/**
	 * Main Sensei_Course_Participants Instance
	 *
	 * Ensures only one instance of Sensei_Course_Participants is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @return Sensei_Course_Participants
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	}

	/**
	 * Installation. Runs on activation.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function install() {
		$this->_log_version_number();
	}

	/**
	 * Log the plugin version number.
	 *
	 * @access private
	 * @since  1.0.0
	 * @return void
	 */
	private function _log_version_number() {
		update_option( $this->_token . '_version', $this->_version );
	}
}
