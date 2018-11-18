<?php

if (!class_exists('VG_Points')) {

	class VG_Points {

		static private $instance = false;
		var $levels = null;
		var $table_name = 'points';
		var $enable_arenas = true;
		var $enable_rankings = true;
		var $enable_limits = true;
		var $starting_points = 1;

		private function __construct() {
			
		}

		function create_table() {
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE `{$wpdb->prefix}{$this->table_name}` (
 `ID` int(11) NOT NULL AUTO_INCREMENT,
 `user_id` bigint(20) unsigned NOT NULL,
 `{$this->table_name}` bigint(20) unsigned NOT NULL,
 `type` VARCHAR(50) NULL DEFAULT NULL,
 `source` VARCHAR(50) NULL DEFAULT NULL,
`date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (`ID`)
) ENGINE=InnoDB $charset_collate";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}

		function init() {
			$this->levels = $this->get_levels();
			add_action('user_register', array($this, 'set_initial_points'), 10, 1);
		}

		function set_initial_points($user_id) {
			$this->set_user_level($user_id);
			$this->add_points_to_user($user_id);
		}

		function maybe_move_to_next_level($user_id) {
			if( !$this->enable_arenas){
				return;
			}
			// check points limit
			$points_in_level = $this->get_user_points_in_level($user_id);
			$current_level_name = get_user_meta($user_id, 'current_level_name', true);
			$current_level_settings = $this->get_level_settings($current_level_name);
			$next_level = $this->get_next_level($current_level_name);

			if ((int) $points_in_level >= (int) $current_level_settings['points_limit']) {
				$this->set_user_level($user_id, $next_level['name']);
				return;
			}

			// check victories
			$victories = (int) get_user_meta($user_id, 'level_victories', true);

			if ($victories >= $current_level_settings['victories_limit']) {
				$this->set_user_level($user_id, $next_level['name']);
			}
		}

		function get_next_level($current_level_name) {
			$levels = $this->get_sorted_levels_by_key('points_limit');
			$out = array();

			if (empty($levels) || !is_array($levels)) {
				return $out;
			}

			foreach ($levels as $index => $level) {
				if ($level['name'] === $current_level_name) {
					$out = $levels[$index + 1];
					break;
				}
			}

			return $out;
		}

		function add_course($user_id, $correct_questions_count = 1) {
			$this->add_points_to_user($user_id, (int) $correct_questions_count, 'in', 'course');

			// Increase courses counter
			$this->adjust_counter(1, 'in', $user_id, 'level_courses');

			// Give points if the user reached the required number of courses
			$courses = (int) get_user_meta($user_id, 'level_courses', true);

			$current_level_name = get_user_meta($user_id, 'current_level_name', true);
			$current_level_settings = $this->get_level_settings($current_level_name);

			if ($courses % (int) $current_level_settings['courses_required'] == 0) {
				$this->add_points_to_user($user_id, (int) $current_level_settings['courses_points'], 'in', 'course');
			}

			$this->maybe_move_to_next_level($user_id);
		}

		function add_victory($user_id) {
			// Increase victories counter
			$this->adjust_counter(1, 'in', $user_id, 'level_victories');

			// Add points per victory
			$current_level_name = get_user_meta($user_id, 'current_level_name', true);
			$current_level_settings = $this->get_level_settings($current_level_name);
			$this->add_points_to_user($user_id, (int) $current_level_settings['victory_points'], 'in', 'victory');
		}

		function adjust_counter($number, $type, $object_id, $meta_key, $data_type = 'user_meta') {
			if ($data_type === 'user_meta') {
				$existing = (int) get_user_meta($object_id, $meta_key, true);
			} else {
				$existing = (int) get_post_meta($object_id, $meta_key, true);
			}

			if ($type === 'in') {
				$existing += (int) $number;
			} else {
				$existing -= (int) $number;
			}

			if ($data_type === 'user_meta') {
				update_user_meta($object_id, $meta_key, $existing);
			} else {
				update_post_meta($object_id, $meta_key, $existing);
			}
		}

		function set_user_level($user_id, $level = null) {
			if( !$this->enable_arenas){
				return;
			}
			$existing_points = $this->get_user_points($user_id);

			if (empty($level)) {
				$level = $this->get_user_level($user_id);
			} else {
				$level = $this->get_level_settings($level);
			}

			update_user_meta($user_id, 'current_level_started_at', (!empty($existing_points['_total_' . $this->table_name]) ) ? $existing_points['_total_' . $this->table_name] : 0);
			update_user_meta($user_id, 'current_level_name', $level['name']);
			update_user_meta($user_id, 'level_courses', 0);
			update_user_meta($user_id, 'level_victories', 0);
		}

		/**
		 * Creates or returns an instance of this class.
		 */
		static function get_instance() {
			if (null == VG_Points::$instance) {
				VG_Points::$instance = new VG_Points();
				VG_Points::$instance->init();
			}
			return VG_Points::$instance;
		}

		function __set($name, $value) {
			$this->$name = $value;
		}

		function __get($name) {
			return $this->$name;
		}

		function get_sorted_levels_by_key($key, $order = SORT_ASC) {
			$levels = $this->get_levels();
			$out = array();

			if (!is_array($levels)) {
				return $out;
			}
			$points = array();
			foreach ($levels as $index => $level_settings) {
				$points[$index] = $level_settings[$key];
			}
			array_multisort($points, $order, SORT_NUMERIC, $levels, SORT_NUMERIC);


			return $levels;
		}

		function get_user_level($user_id) {
			if (!$user_id) {
				return false;
			}

			$points = $this->get_user_points_in_level($user_id);

			$out = $this->get_level_by_points($points);

			return $out;
		}

		function get_user_points_in_level($user_id) {
			if (!$user_id) {
				return false;
			}

			$existing_points = $this->get_user_points($user_id);
			if (empty($existing_points)) {
				return 0;
			}
			$started_at = (int) get_user_meta($user_id, 'current_level_started_at', true);

			// _points = current points after rewards
			// _total_points = all time points
			return $existing_points['_total_' . $this->table_name] - $started_at;
		}

		function get_user_points($user_id) {
			if (!$user_id) {
				return false;
			}

			$out = array(
				'_' . $this->table_name => (int) get_user_meta($user_id, '_' . $this->table_name, true),
				'_total_' . $this->table_name => (int) get_user_meta($user_id, '_total_' . $this->table_name, true),
			);

			return $out;
		}

		function get_user_ranking($user_id, $date_after = 'last 30 days', $date_before = null) {
			$users_ranking = $this->get_points_by_date(array(
				'date_after' => date('Y-m-d', strtotime($date_after)),
				'group_by' => 'user_id',
				'query_select' => "user_id,SUM(CASE WHEN type='in' THEN points ELSE 0 END)- SUM(CASE WHEN type='out'  THEN points ELSE 0 END) as total",
				'method' => 'get_results',
				'page' => 1,
				'per_page' => false,
				'order' => 'DESC',
				'order_by' => 'total',
			));

			if (empty($users_ranking)) {
				return 0;
			}

			$out = 0;
			foreach ($users_ranking as $index => $user_ranked) {
				if ((int) $user_ranked['user_id'] === $user_id) {
					$out = $index + 1;
				}
			}

			return $out;
		}

		function get_ranked_users($users_number, $date_after = 'last 30 days', $date_before = null, $page = 1, $search = null) {
			$users_ranking = $this->get_points_by_date(array(
				'date_after' => date('Y-m-d', strtotime($date_after)),
				'date_before' => date('Y-m-d', strtotime($date_before)),
				'group_by' => 'user_id',
				'query_select' => "user_id,SUM(CASE WHEN type='in' THEN points ELSE 0 END)- SUM(CASE WHEN type='out'  THEN points ELSE 0 END) as total",
				'method' => 'get_results',
				'page' => null,
				'per_page' => null,
				'order' => 'DESC',
				'order_by' => 'total',
			));
			$out = array();

			if (empty($users_ranking)) {
				return $out;
			}

			$only_user_ids = array();
			if (!empty($search)) {
				$users = new WP_User_Query(array(
					'search' => '*' . esc_attr($search) . '*',
					'fields' => 'ID',
					'search_columns' => array(
						'user_login',
						'user_nicename',
						'user_email',
						'user_url',
					),
				));
				$only_user_ids = $users->get_results();
			}


			foreach ($users_ranking as $index => $user_ranked) {

				if (!empty($search) && !in_array((int) $user_ranked['user_id'], $only_user_ids)) {
					continue;
				}
				$out[(int) $user_ranked['user_id']] = array_merge($user_ranked, array(
					'ranking' => $index + 1
				));
			}

			$ranked = array_chunk($out, (int) $users_number);

			return (isset($ranked[$page - 1])) ? $ranked[$page - 1] : array();
		}

		function get_misc_settings() {
			$out = array(
				'surveys_points' => (int) get_option('points_feedback_surveys', 0),
				'courses_victories_points_limit_per_year' => (int) get_option('yearly_points_limit_per_courses_victories', 1),
				'top3_points_limit_per_year' => (int) get_option('yearly_points_limit_per_weekly_top3_appearance', 1),
				'points_limit_per_year' => (int) get_option('yearly_points_limit', 1),
				'weekly_top3_points' => (int) get_option('points_per_weekly_top3_appearance', 1),
			);

			return $out;
		}

		function add_serie($user_id, $correct_count) {

			if (empty($correct_count)) {
				return;
			}
			$current_level_name = get_user_meta($user_id, 'current_level_name', true);
			$current_level_settings = $this->get_level_settings($current_level_name);

			if (empty($current_level_settings['points_per_right_answer'])) {
				$current_level_settings['points_per_right_answer'] = 1;
			}

			$this->add_points_to_user($user_id, (int) $correct_count * (int) $current_level_settings['points_per_right_answer'], 'in', 'serie');
		}

		function add_course_feedback($user_id) {
			$this->add_points_to_user($user_id, (int) get_option('points_feedback_surveys', 0), 'in', 'course_feedback');
		}

		function add_loss($user_id) {
			$current_level_name = get_user_meta($user_id, 'current_level_name', true);
			$current_level_settings = $this->get_level_settings($current_level_name);

			$this->remove_points_from_user($user_id, (int) $current_level_settings['points_lost_on_defeat'], 'loss');
		}

		function remove_points_from_user($user_id, $points, $source) {
			$this->add_points_to_user($user_id, $points, 'out', $source);
		}

		function get_points_by_date($args = array()) {
			global $wpdb;

			$defaults = array(
				'user_id' => '',
				'date_after' => '',
				'date_before' => '',
				'source' => '',
				'type' => '',
				'group_by' => '',
				'query_select' => "SUM(CASE WHEN type='in' THEN points ELSE 0 END)- SUM(CASE WHEN type='out'  THEN points ELSE 0 END)",
				'method' => 'get_var',
				'page' => 1,
				'per_page' => false,
				'order' => '',
				'order_by' => '',
			);

			extract(wp_parse_args($args, $defaults));

			$sql = "SELECT " . $query_select . " FROM {$wpdb->prefix}points ";

			$wheres = array();

			if (!empty($user_id)) {
				if (is_array($user_id)) {
					$wheres[] = "user_id IN ( " . esc_sql(implode(',', array_map('intval', $user_id))) . " )";
				} else {
					$wheres[] = "user_id = " . esc_sql($user_id);
				}
			}

			if (!empty($source)) {
				if (is_array($source)) {
					$wheres[] = "source IN ('" . implode("','", esc_sql($source)) . "' )";
				} else {
					$wheres[] = "source = '" . esc_sql($source) . "'";
				}
			}
			if (!empty($type)) {
				$wheres[] = "type = '" . esc_sql($type) . "'";
			}
			if (!empty($date_after)) {
				$wheres[] = "date > '" . esc_sql($date_after) . "'";
			}
			if (!empty($date_before)) {
				$wheres[] = "date < '" . esc_sql($date_before) . "'";
			}
			$sql .= (!empty($wheres) ) ? ' WHERE ' . implode(' AND ', $wheres) : '';


			if (!empty($group_by)) {
				$sql .= ' GROUP BY ' . esc_sql($group_by);
			}
			if (!empty($order_by) && !empty($order)) {
				$sql .= ' ORDER BY ' . esc_sql($order_by) . ' ' . esc_sql(strtoupper($order));
			}

			if (!empty($page) && !empty($per_page)) {
				$offset = ( $page < 2 ) ? 0 : ( $page - 1) * (int) $per_page;
				$sql .= " LIMIT " . esc_sql($offset) . "," . esc_sql($per_page);
			}

			$results = ( $method === 'get_results' ) ? $wpdb->get_results($sql, ARRAY_A) : $wpdb->get_var($sql);

			return $results;
		}

		function are_year_limits_reached($user_id, $source) {

			if( !$this->enable_limits){
				return false;
			}
			if (in_array($source, array('course', 'victory'))) {
				$limite_anual_by_source = (int) get_option('yearly_points_limit_per_courses_victories', 0);
				$db_source = array('course', 'victory');
			} elseif ($source === 'ranking_top3_semanal') {
				$limite_anual_by_source = (int) get_option('yearly_points_limit_per_weekly_top3_appearance', 0);
				$db_source = $source;
			}

			$limite_anual = (int) get_option('yearly_points_limit', 0);

			// global
			$total_points = (int) $this->get_points_by_date(array(
						'user_id' => $user_id,
						'date_after' => date('Y-m-d', strtotime('last 365 days')),
			));

			if ($total_points >= $limite_anual) {
				return true;
			}

			// per source
			if (isset($limite_anual_by_source) && isset($total_points)) {
				$total_points = (int) $this->get_points_by_date(array(
							'user_id' => $user_id,
							'date_after' => date('Y-m-d', strtotime('last 365 days')),
							'source' => $db_source,
				));

				if ($total_points >= $limite_anual_by_source) {
					return true;
				}
			}


			return false;
		}

		function add_points_to_user($user_id, $new_points = 1, $type = 'in', $source = 'general') {
			global $wpdb;
			if (!$user_id) {
				return false;
			}

			if (!$new_points && $this->starting_points) {
				$new_points = 1;
			}

			if ($this->are_year_limits_reached($user_id, $source) && $type === 'in') {
				return false;
			}

			$existing_points = $this->get_user_points($user_id);

			foreach ($existing_points as $key => $existing_point) {
				if ($type === 'in') {
					$existing_point += $new_points;
				} else {
					$existing_point -= $new_points;
				}

				update_user_meta($user_id, $key, $existing_point);
			}

			// We store the points in a custom table to be able to retrieve/count points per date
			// If we want total/current points we can use the user meta field
			$wpdb->insert($wpdb->prefix . 'points', array(
				'points' => $new_points,
				'user_id' => $user_id,
				'type' => $type,
				'source' => $source,
					), array(
				'points' => '%d',
				'user_id' => '%d',
				'type' => '%s',
				'source' => '%s',
			));

			$this->maybe_move_to_next_level($user_id);
			return $this;
		}

		function get_level_by_points($points) {
			$levels = $this->get_sorted_levels_by_key('points_limit');
			$points = (int) $points;
			$out = array();

			if (!is_array($levels)) {
				return $out;
			}

			if ($levels[0]['points_limit'] > $points) {
				$out = $levels[0];
			} elseif ($levels[count($levels) - 1]['points_limit'] < $points) {
				$out = $levels[count($levels) - 1];
			} else {
				foreach ($levels as $index => $level) {
					if ($level['points_limit'] < $points && $levels[$index + 1]['points_limit'] >= $points) {
						$out = $levels[$index + 1];
						break;
					}
				}
			}
			return $out;
		}

		function get_level_settings($level_name) {
			$levels = $this->get_levels();
			$out = array();

			if (!is_array($levels)) {
				return $out;
			}

			foreach ($levels as $level_settings) {
				if ($level_settings['name'] === $level_name) {
					$out = $level_settings;
				}
			}

			return $out;
		}

		function get_levels() {
			if (!empty($this->levels)) {
				return $this->levels;
			}

			return get_option('vg_point_levels', array());
		}

		function get_all_levels_for_output() {
			$levels = $this->get_levels();
			$out = array();

			if (empty($levels) || !is_array($levels)) {
				return $out;
			}
			foreach ($levels as $level) {
				$out[] = $this->format_settings_for_output($level);
			}

			return $out;
		}

		function format_settings_for_output($level_settings) {

			if (empty($level_settings)) {
				return null;
			}
			$out = array(
				'type' => $level_settings['type'],
				'name' => $level_settings['name'],
				'victory_limit' => $level_settings['victories_limit'],
				'points_limit' => $level_settings['points_limit'],
				'victory_points' => $level_settings['victory_points'],
				'required_courses_won_for_points' => $level_settings['courses_required'],
				'courses_points' => $level_settings['courses_points'],
				'loss_points' => $level_settings['points_lost_on_defeat'],
			);

			return $out;
		}

	}

}

if (!function_exists('VG_Points_Obj')) {

	function VG_Points_Obj() {
		return VG_Points::get_instance();
	}

}

require 'options-page.php';