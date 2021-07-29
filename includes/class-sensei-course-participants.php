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
	 * Set the default properties and hooks methods.
	 *
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct() {
		$this->_version      = SENSEI_COURSE_PARTICIPANTS_VERSION;
		$this->_token        = 'sensei_course_participants';
		$this->assets_dir    = trailingslashit( dirname( SENSEI_COURSE_PARTICIPANTS_PLUGIN_FILE ) ) . 'assets';
		$this->assets_url    = esc_url( trailingslashit( plugins_url( '/assets/dist/', SENSEI_COURSE_PARTICIPANTS_PLUGIN_FILE ) ) );

		$this->load_plugin_textdomain();

		register_activation_hook( SENSEI_COURSE_PARTICIPANTS_PLUGIN_FILE, array( $this, 'install' ) );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_eol_style' ] );
		add_action( 'after_plugin_row_' . SENSEI_COURSE_PARTICIPANTS_PLUGIN_BASENAME, [ $this, 'add_eol_message' ] );
	}

	/**
	 * Add the custom styling to hide the box shadow of the plugin row.
	 */
	public function enqueue_eol_style() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, [ 'plugins', 'plugins-network' ], true ) ) {
			return;
		}

		wp_register_style( 'sensei-course-participants-eol-style', false );
		wp_enqueue_style( 'sensei-course-participants-eol-style' );
		wp_add_inline_style(
			'sensei-course-participants-eol-style',
			sprintf( '[data-plugin="%1$s"] td, [data-plugin="%1$s"] th { box-shadow: none!important;  }', SENSEI_COURSE_PARTICIPANTS_PLUGIN_BASENAME )
		);
	}

	/**
	 * Adds the end of maintenance message on the plugin listing.
	 */
	public function add_eol_message() {

		if ( is_network_admin() ) {
			$active_class = is_plugin_active_for_network( SENSEI_COURSE_PARTICIPANTS_PLUGIN_BASENAME ) ? ' active' : '';
		} else {
			$active_class = is_plugin_active( SENSEI_COURSE_PARTICIPANTS_PLUGIN_BASENAME ) ? ' active' : '';
		}

		/** @var WP_Plugins_List_Table $wp_list_table */
		$wp_list_table = _get_list_table(
			'WP_Plugins_List_Table',
			array(
				'screen' => get_current_screen(),
			)
		);

		printf(
			'<tr class="plugin-update-tr%s">' .
			'<td colspan="%s" class="plugin-update colspanchange">' .
			'<div class="notice inline notice-warning notice-alt"><p>',
			$active_class,
			esc_attr( $wp_list_table->get_column_count() )
		);

		echo esc_html__( 'This plugin is no longer being maintained.', 'sensei-course-participants' );
		echo ' <a href="https://senseilms.com/2021/07/29/retiring-two-sensei-lms-extensions/" rel="noreferrer noopener">' . esc_html__( 'More information', 'sensei-course-participants' ) . '</a>';

		echo '</p></div></td></tr>';

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
		add_filter( 'the_content', array( $instance, 'single_course_prepend_course_participant_count' ), 15 );
		add_action( 'sensei_single_course_content_inside_before', array( $instance, 'display_course_participant_count' ), 14 );
		add_action( 'sensei_course_content_inside_before', array( $instance, 'display_course_participant_count' ), 15, 1 );

		// Include Widget
		add_action( 'widgets_init', array( $instance, 'include_widgets' ) );
	}

	/**
	 * Display course participants on course loop and single course
	 *
	 * @since  1.0.0
	 * @param  WP_Post|int $post_item Post object or ID.
	 * @return void
	 */
	public function display_course_participant_count( $post_item = 0 ) {
		global $post, $wp_the_query;

		// Do not use this method if we are prepending to `the_content`.
		if ( $this->should_prepend_to_the_content() ) {
			return;
		}

		if ( isset( $wp_the_query->queried_object->ID ) && 'page' === get_post_type( $wp_the_query->queried_object->ID ) ){
			return;
		}

		echo $this->get_course_participant_count_html( $post_item );
	}

	/**
	 * Prepend the course participant count to the content for the Single
	 * Course page. To be used with the `the_content` filter.
	 *
	 * @access private
	 * @since 2.0.2
	 *
	 * @param string $content The original post content.
	 *
	 * @return string The new post content with the course participants count prepended.
	 */
	public function single_course_prepend_course_participant_count( $content ) {
		global $post;

		if ( ! $this->should_prepend_to_the_content() ) {
			return $content;
		}

		// Ensure that this is only done once on unsupported themes.
		remove_filter( 'the_content', array( $this, 'single_course_prepend_course_participant_count' ), 15 );

		$course_participants_content = $this->get_course_participant_count_html( $post );

		return $course_participants_content . "\n" . $content;
	}

	/**
	 * Get the HTML to display the course participant count.
	 *
	 * @since 2.0.2
	 *
	 * @param  WP_Post|int $post_item Post object or ID.
	 * @return string
	 */
	private function get_course_participant_count_html( $post_item = 0 ) {
		global $post;

		if ( is_singular( 'course' ) ) {
			$post_id = $post->ID;
		} elseif ( isset( $post_item ) && is_object( $post_item ) ) {
			$post_id = absint( $post_item->ID );
		} elseif ( is_numeric( $post_item ) && $post_item > 0 ) {
			$post_id = absint( $post_item );
		} else {
			return '';
		}

		$learner_count = $this->get_course_participant_count( $post_id );

		$content = '<p class="sensei-course-participants">';

		$content .= sprintf(
			_n(
				/* translators: %d is replaced with the number of learners in the course */
				'<strong>%d</strong> learner taking this course',
				'<strong>%d</strong> learners taking this course',
				intval( $learner_count ),
				'sensei-course-participants'
			),
			esc_html( intval( $learner_count ) )
		);

		$content .= "</p>\n";
		return $content;
	}

	/**
	 * Check whether we should prepend the participant count to the content
	 * using the `the_content` filter instead of the custom hook within Sensei's
	 * Single Course template.
	 *
	 * @since 2.0.2
	 *
	 * @return bool
	 */
	private function should_prepend_to_the_content() {
		global $post;

		$is_legacy_course = true;
		if ( method_exists( Sensei()->course, 'is_legacy_course' ) ) {
			$is_legacy_course = Sensei()->course->is_legacy_course( $post );
		}

		$should_prepend_to_the_content = is_singular( 'course' ) && ! $is_legacy_course;

		/**
		 * Change whether the course participant count HTML should be prepended
		 * to `the_content` rather than using Sensei's custom hooks.
		 *
		 * @since 2.0.2
		 *
		 * @param bool    $should_prepend_to_the_content Whether we should prepend the HTML to `the_content`.
		 * @param WP_Post $post                          The current post.
		 */
		return apply_filters( 'sensei_course_participants_prepend_to_the_content', $should_prepend_to_the_content, $post );
	}

	/**
	 * Exclude participants who have completed the course.
	 *
	 * @param int $post_id Post ID for the course.
	 * @return bool
	 */
	private function exclude_completed_participants( $post_id ) {
		/**
		 * Change whether or not completed participants are to be included in participant counts and lists.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $exclude_completed_participants True if we should exclude completed participants.
		 * @param int  $post_id                        Post ID for the course.
		 */
		return apply_filters( 'sensei_course_participants_exclude_completed_participants', true, $post_id );
	}

	/**
	 * If the new enrolment provider method is not available, return true.
	 *
	 * @since 2.0.1
	 *
	 * @return bool
	 */
	private function use_legacy_enrolment_method() {
		if ( ! interface_exists( '\Sensei_Course_Enrolment_Provider_Interface' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the number of learners taking the current course.
	 *
	 * @since 1.0.0
	 * @since 2.0.1 Changed to count enrolled users instead of learners in progress.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return integer
	 */
	public function get_course_participant_count( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			return 0;
		}

		$exclude_completed = $this->exclude_completed_participants( $post_id );

		return $this->get_enrolled_participants_ids( $post_id, $exclude_completed, true );
	}

	/**
	 * Get an array of learners taking the course.
	 *
	 * @since 1.0.0
	 * @since 2.0.1 Changed to get enrolled users instead of learners in progress.
	 *
	 * @param  string $order    Order direction.
	 * @param  string $orderby  How to determine the order of learners.
	 *
	 * @return array|false  $learners The array of learners.
	 *                                If there are no users taking the course, returns `false`.
	 */
	public function get_course_learners( $order, $orderby ) {
		$post_id           = $this->get_course_id();
		$exclude_completed = $this->exclude_completed_participants( $post_id );
		$user_ids          = $this->get_enrolled_participants_ids( $post_id, $exclude_completed );

		// Don't run the query if there are no users taking this course.
		if ( empty( $user_ids ) ) {
			return false;
		}

		// 'rand' can't be used in WP_User_Query, so save the setting and change it to 'user_registered'
		// We can randomize the array after running the query
		if ( isset( $orderby ) && 'rand' === $orderby ) {
			$orderwas = 'rand';
			$orderby  = 'user_registered';
		}

		$args_array = array(
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
	 * Get enrolled participants IDs.
	 *
	 * @since 2.0.1
	 *
	 * @param int  $course_id         Course ID.
	 * @param bool $exclude_completed Flag if should exclude the completed participants.
	 * @param bool $count             Flag we want to count (true) or get the user ids (false).
	 *
	 * @return int[]|int
	 */
	private function get_enrolled_participants_ids( $course_id, $exclude_completed, $count = false ) {
		if ( $this->use_legacy_enrolment_method() ) {
			return $this->get_enrolled_participants_ids_legacy( $course_id, $exclude_completed, $count );
		}

		$user_ids = Sensei_Course_Enrolment::get_course_instance( $course_id )->get_enrolled_user_ids();

		if ( $exclude_completed ) {
			$user_ids = array_filter(
				$user_ids,
				function( $user_id ) use ($course_id) {
					return ! Sensei_Utils::user_completed_course( $course_id, $user_id );
				}
			);
		}

		if ( $count ) {
			return count( $user_ids );
		}

		return $user_ids;
	}

	/**
	 * Get legacy enrolled participants IDs.
	 *
	 * @since 2.0.1
	 *
	 * @param int  $course_id         Course ID.
	 * @param bool $exclude_completed Flag if should exclude the completed participants.
	 * @param bool $count             Flag we want to count (true) or get the user ids (false).
	 *
	 * @return int[]|int
	 */
	private function get_enrolled_participants_ids_legacy( $course_id, $exclude_completed, $count = false ) {
		$activity_args = array(
			'post_id' => absint( $course_id ),
			'type'    => 'sensei_course_status',
			'count'   => $count,
			'number'  => 0,
			'offset'  => 0,
			'status'  => $exclude_completed ? 'in-progress' : 'any',
		);

		$course_learners = Sensei_Utils::sensei_check_for_activity( $activity_args, ! $count );

		if ( $count ) {
			return $course_learners;
		}

		if ( ! is_array( $course_learners ) ) {
			$course_learners = array( $course_learners );
		}

		$user_ids = array();
		foreach ( $course_learners as $user ) {
			$user_ids[] = absint( $user->user_id );
		}

		return $user_ids;
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
		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend.js', array( 'jquery' ), $this->_version );

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
