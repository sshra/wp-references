<?php
/*
Plugin Name: References
Description: Enables post references (for any type of publications) to connect articles to each other.
Version: 1.201
Author: Shra <to@shra.ru>
Author URI: https://shra.ru
Tags: post references, refs, references, links
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

	Copyright (C) 2018  Yuriy Korol aka SHRA

  This program is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Some basic API to work with references
 */
class REFShraAPI
{
	/**
	 * returns array currently configured REFERENCES
	 * @postType - allows to filter by post type
	 * @refKey - allows to filter by used meta key (without leading _ref_)
	 */
	static function config_load($postType = NULL, $refKey = NULL) {
		$ini = get_option('post_references_settings');

		if (empty($ini['refs'])) {
			return array();
		}

		$refs = array();
		foreach ($ini['refs'] as $innerKey => $ref) {
			if ($postType !== NULL) {
				if ($ref['ref_post'] != $postType) {
					continue;
				}
			}
			if ($refKey !== NULL) {
				if ($ref['id'] != $refKey) {
					continue;
				}
			}

			$refs[$innerKey] = $ref;
		}

		return $refs;
	}

	/**
	 * ADD/update reference config
	 *
	 * If pair postType / refKey already exists then
	 * function will return innerKey [numeric value] of already existed reference record
	 * or TRUE/FALSE on another case.
	 *
	 * @postType - post type where reference is linked,
	 * @refKey - used meta key to store reference data in WP post meta storage (no need to add leading _ref_),
	 * @linkedTypes - allowed post type(s) linked to @postType (can be array or string),
	 * @title - Title for Reference block in Editor.
	 *
	 * Example:
	 *
	 * $result = REFShraAPI::config_add('post', 'linked-story', array('news', 'events'), 'Linked news & events');
	 * if ($result !== TRUE) {
	 *   // Can't add reference for some reasons
	 * }
	 */
	static function config_add($postType, $refKey, $linkedTypes, $title) {

		if (!post_type_exists($postType)) {
			return FALSE;
		}

		if (!is_array($linkedTypes)) {
			$linkedTypes = array($linkedTypes);
		}

		if (empty($linkedTypes)) {
			return FALSE;
		}

		foreach ($linkedTypes as $item) {
			if (!post_type_exists($item)) {
				return FALSE;
			}
		}

		$ini = get_option('post_references_settings');
		if (empty($ini['refs'])) {
			$ini['refs'] = array();
		}

		$keyFound = -1;
		foreach ($ini['refs'] as $innerKey => $ref) {
			if ($ref['ref_post'] == $postType && $ref['id'] == $refKey) {
				$keyFound = $innerKey;
				break;
			}
		}

		$refData = array(
			'ref_title' => $title,
			'ref_post' => $postType,
			'linked_post' => $linkedTypes,
			'id' => $refKey
		);

		if ($keyFound == -1) {
			$ini['refs'][$ini['next_id']] = $refData;
			$ini['next_id'] ++;
			update_option('post_references_settings', $ini);

			return TRUE;
		}

		$ini['refs'][$keyFound] = $refData;
		update_option('post_references_settings', $ini);
		return $keyFound;
	}

	/**
	 * Delete reference config by pair postType / refKey.
	 * Removes only configs, no post meta data are touched.
	 *
	 * @postType - post type where reference is linked,
	 * @refKey - used meta key to store reference data in WP post meta storage (no need to add leading _ref_),
	 *
	 * Example:
	 *
	 * REFShraAPI::config_remove('post', 'linked-story');
	 */
	static function config_remove($postType, $refKey) {

		$refs = REFShraAPI::config_load($postType, $refKey);
		$ini = get_option('post_references_settings');

		if (!empty($refs)) {
			foreach ($refs as $key => $ref) {
				unset($ini['refs'][$key]);
			}
			update_option('post_references_settings', $ini);
		}
	}

	/**
	 * Get references by post ID
	 *
	 * @postID - post id references for which you need to get. Current post ID will be used, if postID is not provided.
	 *
	 * Example:
	 *
	 * $post_id = get_the_ID();
	 * $links = REFShraAPI::get($post_id);
	 *
	 * Returns array of referenced post ids, grouped by $refKey
	 */
	static function get($postID = NULL) {

		if ($postID == NULL) {
			$postID = get_the_ID();
		}

		if (!empty($postID)) {
			$post = get_post($postID);

			if (!empty($post)) {
				$refs = REFShraAPI::config_load($post->post_type);
				if (!empty($refs)) {
					$links = array();
					foreach ($refs as $ref) {
						$value = get_post_meta($post->ID, '_ref_' . $ref['id'], true);
						$links[$ref['id']] = empty($value) ? array() : $value;
					}
					return $links;
				}
			}
		}
		return FALSE;
	}

	/**
	 * returns array of articles where given article is attached as reference.
	 *
	 * @postID - post id of referenced article. Current post ID will be used, if postID isn`t provided.
	 * @postTypes - array of post types where search for given reference article (any type if empty).
	 * @onlyPublished - boolean, get only published articles
	 * 
	 * Example:
	 *
	 * $post_id = get_the_ID();
	 * $articles = REFShraAPI::find($post_id);
	 *
	 */
	static function find($postID = NULL, $postTypes = array(), $onlyPublished = false) {
		global $wpdb;
		if ($postID == NULL) {
			$postID = get_the_ID();
		}
		$seek = array();
		if (!empty($postID)) {
			$where = array("meta_key like '_ref_%'");
			if ($onlyPublished) {
				$where[] = "WP.post_status = 'publish'";
			}
			$query = "SELECT WP.ID, WP.post_type, WPM.meta_value
   		  FROM {$wpdb->postmeta} WPM
   			INNER JOIN {$wpdb->posts} WP ON WP.ID = WPM.post_id
   			WHERE " . implode(' AND ', $where);
   		if (!empty($postTypes)) {
   			$query .= " AND WP.post_type in ('" . implode("', '", $postTypes) . "')";
   		}
			$data = $wpdb->get_results($query); 
			foreach ($data as $k => $v) {
				$value = @unserialize($v->meta_value);
				if ($value !== false && is_array($value) && in_array($postID, $value)) {
					$seek[] = $v;
				}
			}
		}
		return $seek;
	}

	/**
	 * Update references for post.
	 *
	 * @postID - post id references you need to update. Current post ID will be used, if postID isn`t provided.
	 * @refkey - registered reference key.
	 * @postIDs - array of post IDs which should be saved to reference list of given postID.
	 *
	 * Example:
	 *
	 * $post_id = get_the_ID();
	 * $success = REFShraAPI::update($post_id, 'linked_news', array(100,120,125));
	 *
	 */
	static function update($postID = NULL, $refkey, $postIDs) {

		if ($postID == NULL) {
			$postID = get_the_ID();
		}

		if (!empty($postID)) {
			$post = get_post($postID);

			if (!empty($post)) {
				$refs = REFShraAPI::config_load($post->post_type);
				if (!empty($refs)) {
					foreach ($refs as $ref) {
						if ($refkey == $ref['id']) {

							if (empty($postIDs)) {
								$postIDs = array();
							}
							if (!is_array($postIDs)) {
								$postIDs = array($postIDs);
							}
							update_post_meta( $post->ID, '_ref_' . $ref['id'], $postIDs);
							return TRUE;
						}
					}
				}
			}
		}
		return FALSE;
	}
}

/**
 * References PLugin Class
 */
class REFShraClass
{

  public function __construct()
  {
		if (is_admin()) {
			//Actions
			add_action('admin_enqueue_scripts', array($this, 'load_admin_style') );
			add_action('admin_menu', array($this, '_add_menu'));
			add_action('add_meta_boxes', array($this, 'metabox_init'));
			add_action('save_post', array($this, 'metabox_save'));
		}

		add_shortcode('ref', array($this, '_shortcode'));
	}

	/* admin_menu hook */
	public function _add_menu() {
		add_options_page('Post references', 'Post references', 8, __FILE__, array($this, '_options_page'));
	}

	/* REF shortcode implementation */
	public function _shortcode($attr) {
    global $wpdb;

    $attr = shortcode_atts(
      array(
      	'id' => NULL,
      	'key' => NULL
      ),
      $attr
    );

		$refs = REFShraAPI::get($attr['id']);
		$output = '';

		if (!empty($refs)) {
			foreach ($refs as $key => $ids) {
				if ($attr['key'] === NULL || ($key == $attr['key'])) {

					$pubs = $wpdb->get_results("SELECT ID, post_title, post_type
						FROM {$wpdb->posts} WHERE post_status = 'publish'
						AND ID in (" . implode(',', $ids) . ")");

					// Posibility to filter entities list
					$pubs = apply_filters('reference_shortcode_items', $pubs, $attr, $key, $ids);

					if (empty($pubs)) continue;

					$output .= '<ul class="reference-list-' . $key . '">';
					foreach ($pubs as $pub) {
						$title = apply_filters( 'the_title', $pub->post_title, $pub->ID );
						$output .= '<li><a href="' . get_post_permalink($pub->ID) . '">' . $title . '</a></li>';
					}
					$output .= '</ul>';

					// Posibility to filter output
					$output = apply_filters('reference_shortcode_output', $output, $attr, $key, $ids);
				}
			}
		}
		return $output;
	}

	public function load_admin_style() {
		//add chosen jquery plugin
		wp_enqueue_style( 'references_chosen_css', plugin_dir_url( __FILE__ ) . 'admin/chosen.min.css', false, '1.0.0' );
		wp_enqueue_script( 'chosen_js', plugin_dir_url( __FILE__ ) . 'admin/chosen.jquery.min.js', array('jquery'), '1.0.0' );
		wp_enqueue_script( 'references_js', plugin_dir_url( __FILE__ ) . 'admin/references.js', array('jquery', 'chosen_js'), '1.0.0' );
	}

	/* create reference metabox in editor */
	public function metabox_init() {
		//load current options
		$ini = get_option('post_references_settings');

		if (!empty($ini['refs'])) {
			foreach ($ini['refs'] as $v)
				add_meta_box('reference_box_' . $v['id'], $v['ref_title'], array($this, 'metabox_showup'), $v['ref_post'], 'side', 'default', $v);
		}
	}

	/* render metabox */
	public function metabox_showup($post, $ref_data) {
		global $wpdb;
		if (empty($ref_data['args']['linked_post']))
			$ps = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' ORDER BY post_title", ARRAY_A);
		else
			$ps = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts}
			WHERE post_status = 'publish' AND post_type in ('" . implode("','", $ref_data['args']['linked_post']) . "')  ORDER BY post_title", ARRAY_A);

		// Use nonce for verification
		wp_nonce_field( plugin_basename(__FILE__), 'reference_nonce' );

		$key = '_ref_' . $ref_data['args']['id'];
		echo $this->form_select($key, $ps, array('multy' => true, 'col_key' => 'ID', 'col' => 'post_title',
			'class' => 'chosen', 'NULL' => false, 'value' => get_post_meta( $post->ID, $key, true ), 'placeholder' => __('Select articles') ));
	}

	/* save metabox data */
	public function metabox_save($post_id) {
		// check if it is autosave
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return $post_id;

		// no data
		if (empty($_POST)) return $post_id;

		if (! isset( $_POST['reference_nonce'] ) || ! wp_verify_nonce( $_POST['reference_nonce'], plugin_basename(__FILE__) ) )
			return $post_id;

		$ini = get_option('post_references_settings');
		if (!empty($ini['refs'])) {
			// get post
			$post = get_post($post_id);

      // find new reference values
			foreach ($ini['refs'] as $v) {
				if ($v['ref_post'] == $post->post_type) {
					$key = '_ref_' . $v['id'];
					if (!empty($_POST[$key])) {
						update_post_meta( $post_id, $key, $_POST[$key]);
					} else {
						update_post_meta( $post_id, $key, array());
					}
				}
			}
		}
	}

  /* Options for admin page */
  public function _options_page() {
		global $wpdb;
?>
<style>
	.refer {
		border: 1px solid #888;
		padding: 10px;
		border-radius: 2px;
		background-color: #eee;
		display: inline-block;
		vertical-align: top;
		margin: 0 5px 5px 0;
	}

</style>
<div class="wrap">
	<h2><?=__('References settings');?></h2>

<?php
		//load current options
		$ini = get_option('post_references_settings');

		if (!empty($_POST['action']) ) {
			if (empty($_POST['ref_title'])) {
				echo '<div id="message" class="error notice is-dismissible">
						<p>' . __("Metabox title is empty!") . '</p></div>';
			} else {

				switch ($_POST['action']) {
				case 'manage_reference':
					//manage existing reference
					$index = $_POST['ref_index'];

					if ($_POST['sbm'] == __('Update')) {
						if (empty($_POST['linked_post'])) $_POST['linked_post'] = array();

						$ini['refs'][$index] = array(
							'ref_title' => $_POST['ref_title'],
							'ref_post' => $_POST['ref_post'],
							'linked_post' => $_POST['linked_post'],
							'id' => $_POST['ref_id'],
						);
						update_option('post_references_settings', $ini);
					}
					if ($_POST['sbm'] == __('Delete')) {
						unset($ini['refs'][$index]);
						$ini['refs'] = array_values($ini['refs']);
						update_option('post_references_settings', $ini);
//						$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_ref_" . $_POST['id'] . "'");
					}
					break;
				case 'add_new_reference':
					if (!preg_match('#^[\w]+$#s', $_POST['ref_id']))
						echo '<div id="message" class="error notice is-dismissible">
							<p>' . __("Meta key contains invalid chars!") . '</p></div>';
					else {
						if (empty($_POST['linked_post'])) $_POST['linked_post'] = array();
						//new reference
						$ini['refs'][] = array(
							'ref_title' => $_POST['ref_title'],
							'ref_post' => $_POST['ref_post'],
							'linked_post' => $_POST['linked_post'],
							'id' => $_POST['ref_id'],
						);
						$ini['next_id'] ++;

						update_option('post_references_settings', $ini);
					}
					break;
				}
			}
		}

		//post types
		$pt = get_post_types( array('show_ui' => true), 'names');

		if (!empty($ini['refs'])) {
			echo "<h3>" . __('Existing references.') . "</h3>";

			foreach ($ini['refs'] as $k => $v) {
?>
	<div class="refer">
		<form method="post">
		<table>
		<tr valign="top"><td>
			<p>
				<b>Metabox Title *:</b><br />
				<input size="35" name="ref_title" type="text" value="<?=esc_attr($v['ref_title'])?>">
			</p>
			<p>
				<b>Content type:</b><br />
				<?=$this->form_select('ref_post', $pt, array('multy' => false, 'NULL' => false, 'value' => $v['ref_post'] ))?>
			</p>
			<p>
				<b>Used meta key:</b> _ref_<input size="10" type="text" name="ref_id" value="<?=esc_attr($v['id'])?>"><br />
				<small><?=__('Use this key to get data from postmetas inside of code.')?></small>
			</p>
			<input type="hidden" name="ref_index" value="<?=$k?>" />
			<input type="hidden" name="action" value="manage_reference" />
			<input type="submit" class="button-primary" name="sbm" value="<?=__('Update')?>" />
			<input type="submit" class="button-primary" name="sbm" value="<?=__('Delete')?>" />
		</td>
		<td>&nbsp;</td>
		<td>
			<p>
				<b><?=__('Referenced post types')?>:</b><br />
				<?=__('Use CTRL to select more then one type.');?><br />
				<?=$this->form_select('linked_post', $pt, array('multy' => true, 'value' => $v['linked_post'], 'NULL' => false))?>
			</p>
		</td></tr>
		</table>
		</form>
	</div>
<?
			}
		}
?>
	<h3><?=__('Add new reference.')?></h3>
	<p><?=__('Connect different types of publications.')?></p>
	<div class="refer">
		<form method="post">
		<table>
		<tr valign="top"><td>
			<p>
				<b><?=__('Metabox Title');?> *:</b><br />
				<input size="35" name="ref_title" type="text" value="">
			</p>
			<p>
				<b><?=__('Add references metabox to editor of');?>:</b><br />
				<?=$this->form_select('ref_post', $pt, array('multy' => false, 'NULL' => false))?>
			</p>
			<p>
				<b><?=__('Use meta key');?>:</b> _ref_<input size="10" type="text" name="ref_id" value="<?=$ini['next_id']?>"><br />
				<small><?=__('Use this key [a-z0-9] to get data from postmetas inside of code.');?><br />
				<?=__('For example');?>: get_post_meta($post->ID, '_ref_<?=$ini['next_id']?>', true);
				</small>
			</p>
			<input type="hidden" name="action" value="add_new_reference" />
			<input type="submit" class="button-primary" name="sbm" value="<?=__('Add')?>" />

		</td>
		<td>&nbsp;</td>
		<td>
			<p>
				<b><?=__('To allow to connect only next types of articles');?>:</b><br />
				<?=__('Don\'t select any type if you need references to any type of records.')?><br/>
				<?=__('Use CTRL to select more then one type.');?><br />
				<?=$this->form_select('linked_post', $pt, array('multy' => true, 'NULL' => false))?>
			</p>
		</td></tr>
		</table>
		</form>
	</div>

</div>
<?php
    }

	/* form the list */
	public function form_select($id, $src, $args) {
		$defaults = array(
			'NULL' => true,
			'value' => '',
			'col' => '',
			'col_key' => '',
			'multy' => false,
			'class' => '',
			'placeholder' => '',
		);
		$args = array_merge($defaults, $args);

		$options = '';

		//list can be empty
		if ($args['NULL'])
			$options = '<option value="">---</option>';

		foreach ($src as $k => $v) {
			if (is_array($v)) {
				if (!empty($args['col']))
					$value = $v[$args['col']];
				else
					$value = array_shift($v);

				if (!empty($args['col_key']))
					$key = $v[$args['col_key']];
				else
					$key = $k;

			} else {
				$key = $k;
				$value = $v;
			}

			if ($args['multy'] && is_array($args['value']) && in_array($key, $args['value']))
				$options .= '<option value="' . $key . '" selected>' . $value . '</option>';
			else if (!$args['multy'] && $args['value'] !== '' && $key == $args['value'])
				$options .= '<option value="' . $key . '" selected>' . $value . '</option>';
			else
				$options .= '<option value="' . $key . '">' . $value . '</option>';
		}

		if ($args['multy'])
			return '<select class="' . $args['class'] . '" id="id_' . $id . '" name="' . $id . '[]" '
			. (empty($args['placeholder']) ? '' : 'data-placeholder="' . esc_attr($args['placeholder']) . '"' )
			. ' multiple>' . $options . '</select>';
		else
			return '<select class="' . $args['class'] . '" id="id_' . $id . '" name="' . $id . '">' . $options . '</select>';
	}

	/* default settings */
	static function default_settings() {
		return array('refs' => array(), 'next_id' => 1);
	}

	/* install actions (when activate first time) */
    static function install() {
		global $wpdb;
		//set defaults
		add_option('post_references_settings', REFShraClass::default_settings() );
	}

	/* uninstall hook */
    static function uninstall() {
		global $wpdb;
		//delete_option('post_references_settings');
	}

}

class ReferencesList_Widget extends WP_Widget {

	// widget constructor
	public function __construct(){
    parent::__construct(
        'rererenceslist_widget',
	__('References List Widget'),
        array(
            'classname'   => 'rererenceslist_widget',
            'description' => __( 'A basic References List widget to output list of articles.')
            )
    );
	}

	/**
	* Front-end display of widget.
	*
	* @see WP_Widget::widget()
	*
	* @param array $args     Widget arguments.
	* @param array $instance Saved values from database.
	*/
  public function widget( $args, $instance ) {
    global $wpdb;
		if (!is_singular()) return false;
		$postID = get_the_ID();
		if (!$postID) return false;
		$post_type = get_post_type();

    extract( $args );

    $title      = apply_filters( 'widget_title', $instance['title'] );
    $message    = $instance['message'];
		$ref		= $instance['ref'];

		$ini = get_option('post_references_settings');
		foreach ($ini['refs'] as $v) {
			$id = '_ref_' . $v['id'];

			if ($id == $ref && $post_type == $v['ref_post']) {
				$ids = get_post_meta($postID, $id, true);

				if (empty($ids)) return false;

				$pubs = $wpdb->get_results("SELECT post_title, ID
					FROM {$wpdb->posts} WHERE post_status = 'publish'
					AND ID in (" . implode(',', $ids) . ")");

				if (empty($pubs)) return false;

				echo $before_widget;

				if ( $title ) {
					echo $before_title . $title . $after_title;
				}

				echo $message;

				echo "<ul>";
				foreach ($pubs as $v) {
					$post_title = apply_filters( 'the_title', $v->post_title, $v->ID );
					echo '<li><a href="' . get_post_permalink($v->ID) . '">' . $post_title . '</a></li>';
				}
				echo "</ul>";

				echo $after_widget;
			}
		}
  }

  public function update( $new_instance, $old_instance ) {

    $instance = $old_instance;

    $instance['title'] = strip_tags( $new_instance['title'] );
    $instance['message'] = strip_tags( $new_instance['message'] );
		$instance['ref'] = strip_tags( $new_instance['ref'] );

    return $instance;
  }

	public function form( $instance ) {
		// creates the back-end form
		$instance = array_merge(array('title' => '', 'message' => '', 'ref' => ''), $instance);

        $title      = esc_attr( $instance['title'] );
        $message    = esc_attr( $instance['message'] );
		$ref        = esc_attr( $instance['ref'] );

		$ini = get_option('post_references_settings');

		if (empty($ini['refs'])) {
			echo __('You should create record(s) on <a href="/wp-admin/options-general.php?page=references%2Freferences.php">
			References settings page</a> first.');
		} else {
			$selector = '';
			foreach ($ini['refs'] as $v) {
				$id = '_ref_' . $v['id'];
				$selector .= '<option ' . ($id == $ref ? 'selected' : '') . ' value="' . $id . '">' . esc_attr($v['ref_title']) . ' (' . $v['id'] . ')' .  '</option>';
			}
?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('message'); ?>"><?php _e('Description'); ?></label>
            <textarea class="widefat" rows="16" cols="20" id="<?php echo $this->get_field_id('message'); ?>" name="<?php echo $this->get_field_name('message'); ?>"><?php echo $message; ?></textarea>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('ref'); ?>"><?php _e('References'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('ref'); ?>" name="<?php echo $this->get_field_name('ref'); ?>"><?php echo $selector; ?></select>
        </p>
<?php
		}
	}
}

register_uninstall_hook( __FILE__, array('REFShraClass', 'uninstall'));
register_activation_hook( __FILE__, array('REFShraClass', 'install') );

$ref_obj = new REFShraClass();

/* Register the widgets */
add_action( 'widgets_init', function() {
	register_widget( 'ReferencesList_Widget');
});

if (isset($ref_obj)) {
	//to do:
	;
}
