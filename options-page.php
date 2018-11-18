<?php
/**
 * Ultimate Fields Container Setup
 *
 * This code will setup a container called Niveles de Puntos.
 * In order for this code to work, you need to have the Ultimate Fields plugin installed or embedded in the theme.
 * 
 * Add this code directly to you functions.php file or a file that's included in it.
 *
 * For more information, please visit http://ultimate-fields.com/
 */
add_action( 'uf_setup_containers', 'setup_niveles_de_points_fields' );
function setup_niveles_de_points_fields() {
	uf_setup_container( array (
		'_wpb_vc_js_status' => 'false',
		'uf_title' => 'Points and arenas',
		'uf_description' => 'Here you can setup different arenas and the points related to the arenas.',
		'uf_type' => 'options',
		'uf_options_page_type' => 'settings',
		'uf_options_page_slug' => 'vg_points',
		'uf_options_menu_position' => '100',
		'fields' => 
		array (
			0 => 
			array (
				'type' => 'repeater',
				'field_title' => 'Arenas',
				'field_id' => 'vg_point_levels',
				'description' => 'Add every arena',
				'repeater_fields' => 
				array (
					0 => 
					array (
						'type' => 'group',
						'title' => 'Level',
						'key' => 'level',
						'group_fields' => 
						array (
							0 => 
							array (
								'type' => 'text',
								'field_title' => 'Name',
								'field_id' => 'name',
								'autocomplete_suggestions' => 'Dummie
	Semipro
	Pro',
								'output_format_value' => 'none',
							),
							1 => 
							array (
								'type' => 'text',
								'field_title' => 'Victories limit',
								'field_id' => 'victories_limit',
								'description' => 'When the user reaches this limit it will move to the next level.',
								'output_format_value' => 'none',
							),
							2 => 
							array (
								'type' => 'text',
								'field_title' => 'Points limit',
								'field_id' => 'points_limit',
								'description' => 'When the user reaches this limit it will move to the next level.',
								'output_format_value' => 'none',
							),
							3 => 
							array (
								'type' => 'text',
								'field_title' => 'Points per victory',
								'field_id' => 'victory_points',
								'output_format_value' => 'none',
							),
							4 => 
							array (
								'type' => 'text',
								'field_title' => 'Required courses',
								'field_id' => 'courses_required',
								'description' => 'Select the number of courses that the user must complete in order to receive the points.',
								'output_format_value' => 'none',
							),
							5 => 
							array (
								'type' => 'text',
								'field_title' => 'Points per courses group',
								'field_id' => 'courses_points',
								'output_format_value' => 'none',
							),
							6 => 
							array (
								'type' => 'text',
								'field_title' => 'Points lost on defeat',
								'field_id' => 'points_lost_on_defeat',
								'output_format_value' => 'none',
							),
							array (
								'type' => 'text',
								'field_title' => 'Points per right answer',
								'field_id' => 'points_per_right_answer',
								'output_format_value' => 'none',
							),
						),
					),
				),
			),
			2 => 
			array (
				'type' => 'text',
				'field_title' => 'Points per feedback/surveys',
				'field_id' => 'points_feedback_surveys',
				'output_format_value' => 'none',
			),
			3 => 
			array (
				'type' => 'text',
				'field_title' => 'Yearly point limit per courses and victories',
				'field_id' => 'yearly_points_limit_per_courses_victories',
				'output_format_value' => 'none',
			),
			4 => 
			array (
				'type' => 'text',
				'field_title' => 'Points gained for appearing in the weekly top3 ranking',
				'field_id' => 'points_per_weekly_top3_appearance',
				'output_format_value' => 'none',
			),
			5 => 
			array (
				'type' => 'text',
				'field_title' => 'Yearly points limit for appearing in the weekly top3 ranking',
				'field_id' => 'yearly_points_limit_per_weekly_top3_appearance',
				'output_format_value' => 'none',
			),
			6 => 
			array (
				'type' => 'text',
				'field_title' => 'Yearly points limit',
				'field_id' => 'yearly_points_limit',
				'output_format_value' => 'none',
			),
		),
	) );
}