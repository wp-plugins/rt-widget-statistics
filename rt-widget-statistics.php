<?php
/*
	Plugin Name: RT Widget Statistics
	Plugin URI:  http://www.this-play.nl
	Description: Displays widget configuration statistics for a multisite network
	Version:     1.0
	Author:      Roy Tanck
	Author URI:  http://www.this-play.nl
	Text Domain: rt-widget-stats
	License:     GPLv2 or later
	Network:     true
*/

// if called without WordPress, exit
if( !defined('ABSPATH') ){ exit; }


if( !class_exists('RT_Widget_Stats') && is_multisite() ){

	class RT_Widget_Stats {

		private $textdomain = 'rt-widget-stats';

		/**
		 * Constructor
		 */
		function __construct() {
			// load the plugin's text domain			
			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			// hook for the admin page
			add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
			// hook for the admin js
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_js' ) );
		}


		/**
		 * Load the translated strings
		 */
		function load_textdomain(){
			load_plugin_textdomain( $this->textdomain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}


		/**
		 * Add a new options page to the network admin
		 */
		function admin_menu() {
			add_submenu_page( 'settings.php', __( 'Widget Statistics', $this->textdomain ), __( 'Widget Statistics', $this->textdomain ), 'manage_options', 'rt_widget_stats', array( $this, 'settings_page' ) );
		}


		/**
		 * Render the options page
		 */
		function settings_page() {

			// start a timer to keep track of processing time
			$starttime = microtime( true );

			// create a new array to keep the stats in
			$results = array();

			// get all currently published sites
			$args = array(
				'archived'   => 0,
				'mature'     => 0,
				'spam'       => 0,
				'deleted'    => 0,
				'limit'      => 9999,
				'offset'     => 0,
			);
			$sites = wp_get_sites( $args );

			// start the page's output
			echo '<div class="wrap">';
			echo '<h2>' . __( 'Widget Statistics', $this->textdomain ) . '</h2>';
			echo '<p>';

			// gather the data by looping through the sites and getting the sidebars_widgets option
			foreach( $sites as $site ){
				
				$sidebars = get_blog_option( $site['blog_id'], 'sidebars_widgets', null );
				
				foreach( $sidebars as $sidebarname=>$widgets ){
					if( !empty( $widgets ) && $this->is_valid_sidebar( $sidebarname ) ){
						foreach( $widgets as $widget_id ){
							// get the widget's id by chopping the end off the instance id
							$widgetname = $this->get_widget_name( $widget_id );
							// make sure there's an array for this type of widget
							if( !isset($results[$widgetname]) || !is_array( $results[$widgetname] ) ){
								$results[$widgetname] = array();
							}
							// add the instance's data to the array
							$results[$widgetname][] = $site['path'] . ' <em>(' . $sidebarname . ')</em>';
						}
					}
				}
			}

			// sort the results array alphabetically
			ksort( $results );

			// render the html table
			$this->render_table( $results );
			
			// wrap up
			echo '</p>';
			echo '<p><em>';
			printf( __('Page render time: %1$s seconds, sites queried: %2$s', $this->textdomain ), round( microtime( true ) - $starttime, 3 ), count( $sites ) );
			echo '</em></p>';
			echo '</div>';

			// add the inline js
			$this->render_inline_js();
		}


		/**
		 * Gets passed the results array, renders a nice HTML table
		 */
		function render_table( $results ){
			$html = '<table class="widefat fixed" cellspacing="0">';
			$html .= '<thead>';
			$html .= '<tr>';
			$html .= '<th class="manage-column column-columnname">' . __( 'Widget name', $this->textdomain ) . '</th>';
			$html .= '<th class="manage-column column-columnname num">' . __( 'Instance count', $this->textdomain ) . '</th>';
			$html .= '<th class="manage-column column-columnname">' . __( 'Sidebars', $this->textdomain ) . '</th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			$count = 0;

			foreach( $results as $name=>$inst ){
				$html .= '<tr' . ( ( $count % 2 == 0 ) ? ' class="alternate"' : '' ) . '>';
				$html .= '<td class="column-columnname"><strong>' . $name . '</strong></td>';
				$html .= '<td class="column-columnname num">' . count( $inst ) . '</td>';

				$html .= '<td class="column-columnname">';
				$html .= '<div class="rt_widget_stats_details" style="display: none;">';
				foreach( $inst as $i ){
					$html .= $i . '<br />';
				}
				$html .= '</div>';
				$html .= '<a class="rt_widget_stats_toggle_details" href="#">' . __( 'show', $this->textdomain ) . '</a>';
				$html .= '</td>';
				
				$html .= '</tr>';

				$count++;
			}

			$html .= '</tbody>';
			$html .= '</table>';

			echo $html;
		}


		/**
		 * A little bit of inline JS to fold/unfold the sidebar info
		 */
		function render_inline_js(){
			$html = '<script type="text/javascript">';
			$html .= 'jQuery(document).ready(function( $ ) {';
			$html .= '$(".rt_widget_stats_toggle_details").click( function( e ){';
			$html .= 'e.preventDefault();';
			$html .= '$(this).closest("td").find(".rt_widget_stats_details").slideToggle(500,function(){';
			$html .= 'if( $(this).css("display") == "none" ){';
			$html .= '$(this).closest("td").find(".rt_widget_stats_toggle_details").html("' . __( 'show', $this->textdomain ) . '")';
			$html .= '} else {';
			$html .= '$(this).closest("td").find(".rt_widget_stats_toggle_details").html("' . __( 'hide', $this->textdomain ) . '")';
			$html .= '}';
			$html .= '});';
			$html .= '});';
			$html .= '});';
			$html .= '</script>';
			echo $html;
		}


		/**
		 * Check sidebar names agains a couple the WP uses internally
		 */
		function is_valid_sidebar( $name ){
			$reserved_names = array( 'wp_inactive_widgets', 'array_version', 'orphaned_widgets' );
			foreach( $reserved_names as $r ){
				if( substr( $name, 0, strlen( $r ) ) == $r ){
					return false;
				}
			}
			return true;
		}


		/**
		 * Strip the instance number from a widget id to get the "real" name
		 */
		function get_widget_name( $id_str ){
			if( strpos( $id_str, '-' ) !== false ){
				return substr( $id_str, 0, strrpos( $id_str, '-' ) );	
			}
			return $id_str;
		}


		/**
		 * Enqueue javascript (just depenencies for now)
		 */
		function enqueue_js( $hook ){
			if ( 'settings_page_rt_widget_stats' != $hook ) {
				return;
			}
			wp_enqueue_script( 'jquery' );
		}

	}

	// create an instance of the class
	$rt_widget_stats = new RT_Widget_Stats();

}

?>