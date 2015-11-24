<?php
/*
Plugin Name: Post feed
Plugin URI:  http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Post feed widget. Show posts in various different layouts and with various options
Version:     0.1
Author:      Rivermouth Ltd
Author URI:  http://rivermouth.fi
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: rvrmth-feed
*/

include_once 'wp_dropdown_post_types.php';

class Rvrmth_Widget_Feed extends WP_Widget {

	private static $text_domain = 'rvrmth-feed';

	private static $types = array(
		'tiles' => 'Tiles',
		'feed' => 'Feed'
	);

	function __construct()
	{
		parent::__construct(
			// Base ID of your widget
			'rvrmth_widget_feed',
			// Widget name will appear in UI
			__('Feed widget', self::$text_domain),
			// Widget description
			array('description' => __( 'Widget for creating post feeds.', self::$text_domain ))
		);

		$this->register_ajax_js();
	}

	private static function get_default_instance()
	{
		return array(
			'title' => __( 'Title', self::$text_domain ),
			'post_type' => 'post',
			'category' => 0,
			'type' => self::$types['Tiles'],
			'max_results' => 4,
			'columns_per_row' => 4,
			'show_post_title' => 'on',
			'show_post_excerpt' => 'on',
			'title_after_image' => 'off',
			'show_showall_button' => 'on',
			'showall_button_text' => __('Show all', self::$text_domain),
			'shuffle_posts_every_ms' => 0,
		);
	}

	public function register_ajax_js()
	{
		$fetch_items_fn_name = 'fetch_items_ajax';
		$fetch_items_ajax_callback = array($this, $fetch_items_fn_name);
		add_action('wp_ajax_fetch_items_ajax', $fetch_items_ajax_callback);
		add_action('wp_ajax_nopriv_fetch_items_ajax', $fetch_items_ajax_callback);
		wp_enqueue_script('feed', plugins_url( '/rvrmth-feed.js', __FILE__ ), array('jquery'));
		wp_localize_script('feed', 'ajax_object', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'fetch_items_fn_name' => $fetch_items_fn_name
		));
	}

	private function feed_item_content($fn_args)
	{
		if (!isset($fn_args['thumbnail_size'])) {
			$fn_args['thumbnail_size'] = 'thumbnail';
		}
		$thumbnail_url = wp_get_attachment_image_src(get_post_thumbnail_id(), $fn_args['thumbnail_size'])[0];
		$thumbnail_title = get_post(get_post_thumbnail_id())->post_title;
		$title = $fn_args['show_post_title'] ? '<div class="title"><h1><a title="' . get_the_title() . '" rel="bookmark" href="' . get_the_permalink() . '">' . get_the_title() . '</a></h1></div>' : '';

		if (!$fn_args['title_after_image']) { echo $title; }
		?>
		<div class="thumbnail featured-image" style="background-image: url(<?php echo $thumbnail_url; ?>)">
			<?php if (has_post_thumbnail()) : ?>
				<img src="<?php echo $thumbnail_url; ?>" title="<?php echo $thumbnail_title; ?>" alt="<?php echo $thumbnail_title; ?>" />
				<meta itemprop="thumbnailURL" content="<?php echo $thumbnail_url; ?>" />
			<?php endif; ?>
		</div>
		<?php
		if ($fn_args['title_after_image']) { echo $title; }
		if ($fn_args['show_post_excerpt']) {
			echo '<div class="entry" itemprop="text">' . get_the_excerpt() . '</div>';
		}
	}

	public function echo_box($args) {
		echo '<div class="box">';
		$this->feed_item_content($args);
		echo '</div>';
	}

	public function echo_feed_item($args) {
		echo '<div class="feed--item"><article>';
		$this->feed_item_content($args);
		echo '</article></div>';
	}

	private function fetch_items($args=null)
	{
		$type = $args['type'];
		$post_type = $args['post_type'];
		$category = $args['category'];
		$max_results = $args['max_results'];
		$shuffle_posts_every_ms = $args['shuffle_posts_every_ms'];
		$loop_query_params = '';
		if ($shuffle_posts_every_ms > 0) {
			$loop_query_params .= 'orderby=rand&';
		}
		$loop_query_params .= 'post_type=' . $post_type . '&cat=' . $category . '&posts_per_page=' . $max_results;

		if (function_exists('rvrmth_feed_args')) {
			rvrmth_feed_args($fn_args);
		}
		$wrapper_classess = "feed feed--$type feed--post-type-$post_type feed--cat-$category";
		if ($type == 'tiles') {
			echo '<div class="' . $wrapper_classess . ' row row--' . $args['columns_per_row'] . '-col">';
			$render_fn = function_exists('rvrmth_feed_echo_box') ? 'rvrmth_feed_echo_box' : array($this, 'echo_box');
			do_loop($render_fn, $loop_query_params, false, $args);
		}
		else if ($type == 'feed') {
			echo '<div class="' . $wrapper_classess . '">';
			$render_fn = function_exists('rvrmth_feed_echo_feed_item') ? 'rvrmth_feed_echo_feed_item' : array($this, 'echo_feed_item');
			do_loop($render_fn, $loop_query_params, false, $args);
		}
		echo '</div>';
	}

	public function fetch_items_ajax()
	{
		$post_object = get_post($_POST['post_id']);
		setup_postdata( $GLOBALS['post'] =& $post_object );
		$this->fetch_items($_POST['args']);
		wp_reset_postdata();
		wp_die();
	}

	// Creating widget front-end
	// This is where the action happens
	public function widget( $args, $instance )
	{
		echo $args['before_widget'];
		if (!empty($args['title'])) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
		}

		if (!$instance) {
			$instance = self::get_default_instance();
		}

		$post_type = $instance['post_type'];
		$category = $instance['category'];
		$type = $instance['type'];
		$max_results = $instance['max_results'];
		$columns_per_row = $instance['columns_per_row'];
		$show_post_title = $instance['show_post_title'];
		$show_post_excerpt = $instance['show_post_excerpt'];
		$title_after_image = $instance['title_after_image'];
		$show_showall_button = $instance['show_showall_button'];
		$showall_button_text = $instance['showall_button_text'];
		$shuffle_posts_every_ms = $instance['shuffle_posts_every_ms'];

		$fn_args = array(
			'show_post_title' => $show_post_title,
			'show_post_excerpt' => $show_post_excerpt,
			'title_after_image' => $title_after_image,
			'columns_per_row' => $columns_per_row,
			'type' => $type,
			'post_type' => $post_type,
			'category' => $category,
			'max_results' => $max_results,
			'shuffle_posts_every_ms' => $shuffle_posts_every_ms
		);

		$element_id = 'rvrmth-feed-instance-' . $args['widget_id'];
		$ajax_arguments = '';
		if ($type == 'tiles') {
			$ajax_arguments =
				'data-post-id=\'' . get_the_ID() . '\' ' .
				'data-args=\'' . json_encode($fn_args) . '\' ' .
				'data-ajax-enabled="' . ($shuffle_posts_every_ms > 0 ? 'enabled' : '') . '" ';
		}

		echo '<div id="' . $element_id . '" class="rvrmth-feed" ' . $ajax_arguments . '>';
		$this->fetch_items($fn_args);
		if ($show_showall_button) {
			if ($category > 0) {
				$button_text = get_cat_name($category);
				$button_href = get_category_link($category);

			}
			else {
				$post_type_object = get_post_type_object($post_type);
				$button_text = $post_type_object->labels->name;
				$button_href = get_post_type_archive_link($post_type_object->name);
			}
			echo '<div><a href="' . $button_href . '" class="button button--block">' . $showall_button_text . '</a></div>';
		}
		echo '</div>';

		echo $args['after_widget'];
	}

	// Widget Backend
	public function form( $instance )
	{
		if (!$instance) {
			$instance = self::get_default_instance();
		}
		$title = $instance[ 'title' ];
		$post_type = $instance['post_type'];
		$category = esc_attr($instance['category']);
		$type = esc_attr($instance['type']);
		$max_results = esc_attr($instance['max_results']);
		$columns_per_row = esc_attr($instance['columns_per_row']);
		$show_post_title = $instance['show_post_title'];
		$show_post_excerpt = $instance['show_post_excerpt'];
		$title_after_image = $instance['title_after_image'];
		$show_showall_button = $instance['show_showall_button'];
		$showall_button_text = $instance['showall_button_text'];
		$shuffle_posts_every_ms = $instance['shuffle_posts_every_ms'];
		// Widget admin form
		?>
			<p>
				<div>
					<label for="<?php echo $this->get_field_id( 'title' ); ?>">
						<?php _e( 'Title:' ); ?>
					</label>
					<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'post_type' ); ?>">
						<?php _e( 'Post type:' ); ?>
					</label>
					<?php wp_dropdown_post_types( array( 'show_option_none' =>' ','name' => $this->get_field_name( 'post_type' ), 'selected' => $post_type ) ); ?>
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'category' ); ?>">
						<?php _e( 'Category:' ); ?>
					</label>
					<?php wp_dropdown_categories( array( 'show_option_none' =>' ','name' => $this->get_field_name( 'category' ), 'selected' => $category ) ); ?>
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'type' ); ?>">
						<?php _e( 'Feed type:' ); ?>
					</label>
					<select id="<?php echo $this->get_field_id( 'type' ); ?>" name="<?php echo $this->get_field_name( 'type' ); ?>">
						<?php
							foreach(self::$types as $key => $value) {
								echo '<option value="' . $key . '" ' . ($type == $key ? 'selected=selected ' : '') . '>' . $value . '</option>';
							}
						?>
					</select>
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'max_results' ); ?>">
						<?php _e( 'Number of posts to show:' ); ?>
					</label>
					<input class="widefat" id="<?php echo $this->get_field_id( 'max_results' ); ?>" name="<?php echo $this->get_field_name( 'max_results' ); ?>" type="number" value="<?php echo $max_results; ?>" />
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'showall_button_text' ); ?>">
						<?php _e( '"Show all" -button text:' ); ?>
					</label>
					<input class="widefat" id="<?php echo $this->get_field_id( 'showall_button_text' ); ?>" name="<?php echo $this->get_field_name( 'showall_button_text' ); ?>" type="text" value="<?php echo $showall_button_text; ?>" />
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'show_post_title' ); ?>">
						<?php _e( 'Show posts\' title?' ); ?>
					</label>
					<input class="widefat" id="<?php echo $this->get_field_id( 'show_post_title' ); ?>" name="<?php echo $this->get_field_name( 'show_post_title' ); ?>" type="checkbox" <?php checked( $show_post_title, 'on'); ?> />
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'show_post_excerpt' ); ?>">
						<?php _e( 'Show posts\' excerpt?' ); ?>
					</label>
					<input class="widefat" id="<?php echo $this->get_field_id( 'show_post_excerpt' ); ?>" name="<?php echo $this->get_field_name( 'show_post_excerpt' ); ?>" type="checkbox" <?php checked( $show_post_excerpt, 'on'); ?> />
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'title_after_image' ); ?>">
						<?php _e( 'Post title after image?' ); ?>
					</label>
					<input class="widefat" id="<?php echo $this->get_field_id( 'title_after_image' ); ?>" name="<?php echo $this->get_field_name( 'title_after_image' ); ?>" type="checkbox" <?php checked( $title_after_image, 'on'); ?> />
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'show_showall_button' ); ?>">
						<?php _e( 'Show "show all" -button?' ); ?>
					</label>
					<input class="widefat" id="<?php echo $this->get_field_id( 'show_showall_button' ); ?>" name="<?php echo $this->get_field_name( 'show_showall_button' ); ?>" type="checkbox" <?php checked( $show_showall_button, 'on'); ?> />
				</div>

				<hr>

				<h3>Applies only for type=tiles</h3>
				<div>
					<label for="<?php echo $this->get_field_id( 'columns_per_row' ); ?>">
						<?php _e( 'Columns per row:' ); ?>
					</label>
					<input class="widefat" id="<?php echo $this->get_field_id( 'columns_per_row' ); ?>" name="<?php echo $this->get_field_name( 'columns_per_row' ); ?>" type="number" min=1 max=4 value="<?php echo $columns_per_row; ?>" />
				</div>
				<div>
					<label for="<?php echo $this->get_field_id( 'shuffle_posts_every_ms' ); ?>">
						<?php _e( 'Shuffle posts every [x] milliseconds (0 == do not shuffle):' ); ?>
					</label>
					<input class="widefat" id="<?php echo $this->get_field_id( 'shuffle_posts_every_ms' ); ?>" name="<?php echo $this->get_field_name( 'shuffle_posts_every_ms' ); ?>" type="number" min="0" step="1000" value="<?php echo $shuffle_posts_every_ms; ?>" />
				</div>
			</p>
		<?php
	}

	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance )
	{
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['category'] = $new_instance['category'];
		$instance['post_type'] = $new_instance['post_type'];
		$instance['type'] = $new_instance['type'];
		$instance['max_results'] = $new_instance['max_results'];
		$instance['columns_per_row'] = $new_instance['columns_per_row'];
		$instance['show_post_title'] = $new_instance['show_post_title'];
		$instance['show_post_excerpt'] = $new_instance['show_post_excerpt'];
		$instance['title_after_image'] = $new_instance['title_after_image'];
		$instance['show_showall_button'] = $new_instance['show_showall_button'];
		$instance['showall_button_text'] = $new_instance['showall_button_text'];
		$instance['shuffle_posts_every_ms'] = $new_instance['shuffle_posts_every_ms'];
		return $instance;
	}

}

// Register and load the widget
function load_rvrmth_widget_feed() {
	register_widget('Rvrmth_Widget_Feed');
}
add_action('widgets_init', 'load_rvrmth_widget_feed');
