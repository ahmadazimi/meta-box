<?php
// Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'RWMB_Taxonomy_Field' ) )
{
	class RWMB_Taxonomy_Field
	{
		/**
		 * Enqueue scripts and styles
		 *
		 * @return void
		 */
		static function admin_enqueue_scripts()
		{
			wp_enqueue_style( 'select2', RWMB_CSS_URL . 'select2-css/select2.css', array(), '3.2' );
			wp_enqueue_style( 'rwmb-select-advanced', RWMB_CSS_URL . 'select-advanced.css', array(), RWMB_VER );
			wp_register_script( 'select2',  RWMB_JS_URL . 'select2-js/select2.js', array(), '3.2', true );
			wp_register_script( 'select_advanced',  RWMB_JS_URL . 'select-advanced.js', array('select2'), RWMB_VER, true );
			wp_enqueue_style( 'rwmb-taxonomy', RWMB_CSS_URL . 'taxonomy.css', array(), RWMB_VER );
			wp_enqueue_script( 'rwmb-taxonomy', RWMB_JS_URL . 'taxonomy.js', array( 'jquery', 'select_advanced', 'wp-ajax-response' ), RWMB_VER, true );
		}

		/**
		 * Add default value for 'taxonomy' field
		 *
		 * @param $field
		 *
		 * @return array
		 */
		static function normalize_field( $field )
		{
			$field = wp_parse_args( $field, array(
				'js_options' => array(),
			) );

			$field['js_options'] = wp_parse_args( $field['js_options'], array(
				'allowClear' => true,
				'width' => 'resolve',
				'placeholder' => "Select a Value"
			) );
			// Default query arguments for get_terms() function
			$default_args = array(
				'hide_empty' => false,
			);

			if ( ! isset( $field['options']['args'] ) )
				$field['options']['args'] = $default_args;
			else
				$field['options']['args'] = wp_parse_args( $field['options']['args'], $default_args );

			// Show field as checkbox list by default
			if ( ! isset( $field['options']['type'] ) )
				$field['options']['type'] = 'checkbox_list';

			// If field is shown as checkbox list, add multiple value
			if ( in_array( $field['options']['type'], array( 'checkbox_list', 'checkbox_tree' ) ) )
			{
				$field['multiple'] = true;
				$field['field_name'] = "{$field['id']}[]";
			}

			// For select tree: display it as a normal select box (no multiple attribute), but allows to save multiple values
			if ( 'select_tree' == $field['options']['type'] || ('select_advanced' == $field['options']['type'] && $field['multiple'] == true) )
				$field['field_name'] = "{$field['id']}[]";

			if ( in_array( $field['options']['type'], array( 'checkbox_tree', 'select_tree' ) ) )
			{
				if ( isset( $field['options']['args']['parent'] ) )
				{
					$field['options']['parent'] = $field['options']['args']['parent'];
					unset( $field['options']['args']['parent'] );
				}
				else
				{
					$field['options']['parent'] = 0;
				}
			}

			return $field;
		}

		/**
		 * Get field HTML
		 *
		 * @param $html
		 * @param $field
		 * @param $meta
		 *
		 * @return string
		 */
		static function html( $html, $meta, $field )
		{

			$options = $field['options'];
			$terms   = get_terms( $options['taxonomy'], $options['args'] );

			$html = '';
			// Checkbox LIST
			if ( 'checkbox_list' === $options['type'] )
			{
				$html = array();
				$tpl = '<label><input type="checkbox" name="%s" value="%s" %s /> %s</label>';
				foreach ( $terms as $term )
				{
					$html[] = sprintf(
						$tpl,
						$field['field_name'],
						$term->slug,
						checked( in_array( $term->slug, $meta ), true, false ),
						$term->name
					);
				}
				$html = implode( '<br />', $html );
			}
			// Checkbox TREE
			elseif ( 'checkbox_tree' === $options['type'] )
			{
				$elements = self::process_terms( $terms );
				$html    .= self::walk_checkbox_tree( $meta, $field, $elements, $field['options']['parent'], true );
			}
			// Select TREE
			elseif ( 'select_tree' === $options['type'] )
			{
				$elements = self::process_terms( $terms );
				$html    .= self::walk_select_tree( $meta, $field, $elements, $field['options']['parent'], '', true );
			}
			// Select
			elseif ( 'select_advanced' === $options['type'] ) {
				$html = sprintf(
					'<select class="rwmb-select-advanced" name="%s" id="%s"%s data-options ="%s">',
					$field['field_name'],
					$field['id'],
					$field['multiple'] ? ' multiple="multiple"' : '',
					esc_attr( json_encode( $field['js_options'] ))
				);
				$option = '<option value="%s" %s>%s</option>';
	
				foreach ( $terms as $term )
				{
					$html .= sprintf(
						$option,
						$term->slug,
						selected( in_array( $term->slug, $meta ), true, false ),
						$term->name
					);
				}
				$html .= '</select>';
			}
			else
			{
				$multiple = $field['multiple'] ? " multiple='multiple' style='height: auto;'" : '';
				$html    .= "<select name='{$field['field_name']}'{$multiple}>";
				$option = '<option value="%s" %s>%s</option>';
				foreach ( $terms as $term )
				{
					$html .= sprintf(
						$option,
						$term->slug,
						selected( in_array( $term->slug, (array)$meta ), true, false ),
						$term->name
					);
				}
				$html .= '</select>';
			}

			return $html;
		}

		/**
		 * Walker for displaying checkboxes in treeformat
		 *
		 * @param      $meta
		 * @param      $field
		 * @param      $elements
		 * @param int  $parent
		 * @param bool $active
		 *
		 * @return string
		 */
		static function walk_checkbox_tree( $meta, $field, $elements, $parent = 0, $active = false )
		{
			if ( ! isset( $elements[$parent] ) )
				return;
			$terms  = $elements[$parent];
			$hidden = ( !$active ? 'hidden' : '' );

			$html = "<ul class = 'rw-taxonomy-tree {$hidden}'>";
			$li = '<li><label><input type="checkbox" name="%s" value="%s" %s /> %s</label>';
			foreach ( $terms as $term )
			{
				$html .= sprintf(
					$li,
					$field['field_name'],
					$term->slug,
					checked( in_array( $term->slug, $meta ), true, false ),
					$term->name
				);
				$html .= self::walk_checkbox_tree( $meta, $field, $elements, $term->term_id, ( in_array( $term->slug, $meta ) ) && $active ) . '</li>';
			}
			$html .= '</ul>';

			return $html;
		}

		/**
		 * Walker for displaying select in treeformat
		 *
		 * @param        $meta
		 * @param        $field
		 * @param        $elements
		 * @param int    $parent
		 * @param string $parent_slug
		 * @param bool   $active
		 *
		 * @return string
		 */
		static function walk_select_tree( $meta, $field, $elements, $parent = 0, $parent_slug = '', $active = false )
		{
			if ( ! isset( $elements[$parent] ) )
				return;
			$terms    = $elements[$parent];
			$hidden   = $active ? 'active' : 'disabled';
			$disabled = disabled( $active, false, false );
			$multiple = $field['multiple'] ? " multiple='multiple' style='height: auto;'" : '';
			$id       = empty( $parent_slug ) ? '' : " id='rwmb-taxonomy-{$parent_slug}'";

			$html  = "<div{$id} class='rw-taxonomy-tree {$hidden}'>";
			$html .= "<select name='{$field['field_name']}'{$disabled}{$multiple}>";
			$html .= "<option value=''>None</option>";

			$option = '<option value="%s" %s>%s</option>';
			foreach ( $terms as $term )
			{
				$html .= sprintf(
					$option,
					$term->slug,
					selected( in_array( $term->slug, $meta ), true, false ),
					$term->name
				);
			}
			$html .= '</select>';
			foreach ( $terms as $term )
			{
				$html .= self::walk_select_tree( $meta, $field, $elements, $term->term_id, $term->slug, in_array( $term->slug, $meta ) && $active ) . '</li>';
			}
			$html .= '</div>';

			return $html;
		}

		/**
		 * Processes terms into indexed array for walker functions
		 *
		 * @param $terms
		 *
		 * @internal param $field
		 * @return array
		 */
		static function process_terms( $terms )
		{
			$elements = array();
			foreach ( $terms as $term )
			{
				$elements[$term->parent][] = $term;
			}
			return $elements;
		}

		/**
		 * Save post taxonomy
		 *
		 * @param $post_id
		 * @param $field
		 * @param $old
		 *
		 * @param $new
		 */
		static function save( $new, $old, $post_id, $field )
		{
			wp_set_object_terms( $post_id, $new, $field['options']['taxonomy'] );
		}

		/**
		 * Standard meta retrieval
		 *
		 * @param mixed 	$meta
		 * @param int		$post_id
		 * @param array  	$field
		 * @param bool  	$saved
		 *
		 * @return mixed
		 */
		static function meta( $meta, $post_id, $saved, $field )
		{
			$options = $field['options'];

			$meta = wp_get_post_terms( $post_id, $options['taxonomy'] );
			$meta = is_array( $meta ) ? $meta : (array) $meta;
			$meta = wp_list_pluck( $meta, 'slug' );

			return $meta;
		}
	}
}
