<?php
/**
 * Plugin Name: Klandeimport
 * Plugin URI: https://github.com/redundans/klandeimport/
 * Description: An plugin for importing user activity from different developer forums.
 * Version: 0.1
 * Author: Jesper Nilsson
 * Author URI: https://github.com/redundans
 * Text Domain: klandeimport
 * License: GPL2
 */

include_once('includes/simple_html_dom.php');

define( 'GRAVATAR_SIZE', '20' );

/**
 * Function for registrating the activity post type
 */
function create_posttype() {
	register_post_type( 'activity',
		array(
		  'labels' => array(
		    'name' => __( 'Activity' ),
		    'singular_name' => __( 'Activity' )
		  ),
		  'public' => true,
		  'has_archive' => false,
		  'rewrite' => array('slug' => 'activities'),
		  'supports' => array( 'title', 'editor', 'author' )
		)
	);
}
add_action( 'init', 'create_posttype' );

/**
 * Function for adding Activity Links to the user edit page.
 * @param  object $user_id
 */
function add_extra_activity_links( $user )
{
    ?>
        <h3>User Activity Links</h3>

        <table class="form-table">
            <tr>
                <th><label for="github_profile">Github Link</label></th>
                <td><input type="text" name="github_profile" value="<?php echo esc_attr(get_the_author_meta( 'github_profile', $user->ID )); ?>" class="regular-text" /></td>
            </tr>

            <tr>
                <th><label for="wordpress_org_profile">Wordpress.org Link</label></th>
                <td><input type="text" name="wordpress_org_profile" value="<?php echo esc_attr(get_the_author_meta( 'wordpress_org_profile', $user->ID )); ?>" class="regular-text" /></td>
            </tr>

            <tr>
                <th><label for="bitbucket_profile">Bitbucket link</label></th>
                <td><input type="text" name="bitbucket_profile" value="<?php echo esc_attr(get_the_author_meta( 'bitbucket_profile', $user->ID )); ?>" class="regular-text" /></td>
            </tr>

            <tr>
                <th><label for="stackoverflow_profile">Stackoverflow link</label></th>
                <td><input type="text" name="stackoverflow_profile" value="<?php echo esc_attr(get_the_author_meta( 'stackoverflow_profile', $user->ID )); ?>" class="regular-text" /></td>
            </tr>
        </table>
    <?php
}
add_action( 'show_user_profile', 'add_extra_activity_links' );
add_action( 'edit_user_profile', 'add_extra_activity_links' );

/**
 * Function for saving the content of the Activity Links from the user edit page.
 * @param  object $user_id
 */
function save_extra_activity_links( $user_id )
{
    update_user_meta( $user_id,'github_profile', sanitize_text_field( $_POST['github_profile'] ) );
    update_user_meta( $user_id,'wordpress_org_profile', sanitize_text_field( $_POST['wordpress_org_profile'] ) );
    update_user_meta( $user_id,'bitbucket_profile', sanitize_text_field( $_POST['bitbucket_profile'] ) );
    update_user_meta( $user_id,'stackoverflow_profile', sanitize_text_field( $_POST['stackoverflow_profile'] ) );
}
add_action( 'personal_options_update', 'save_extra_activity_links' );
add_action( 'edit_user_profile_update', 'save_extra_activity_links' );

/**
 * This function gets a list of all users and then fetches activity from different sources.
 */
function import_users_activity(){
	$users = get_users();

	foreach ($users as $user) {
		import_user_github_activity( $user );
		import_user_wordpress_activity( $user );
		import_user_stackoverflow_activity( $user );
	}
}

/**
 * This function imports activity from a github RSS.
 * @param  object $user
 */
function import_user_github_activity( $user ){
	$github = get_user_meta( $user->ID , 'github_profile', TRUE );
    if (!($x = simplexml_load_file( $github )))
        return;
 
    foreach ($x->entry as $activity) {
    	$id = (string)$activity->id;
    	if( get_post_by_title( $id ) == NULL ) {
	    	$link = (string)$activity->link[href];
	    	$content = (string)$activity->title;
	    	$date = date( 'Y-m-d H:i:s', strtotime( (string)$activity->published ) );
	    	$date_key = date( 'Y-m-d', strtotime( (string)$activity->published ) );
	    	$wordarray = explode(' ', $content);
	    	if (count($wordarray) > 1 ) {
	    		
	    		/*$wordarray[count($wordarray)-1] = '<a href="' . $link . '" target="_blank">' . $wordarray[count($wordarray)-1] . '</a>'; 
	    		$wordarray[0] = '<strong>' . $wordarray[0] . '</strong>'; 
				$content = implode(' ', $wordarray);*/

				$content = get_avatar( $user->ID, GRAVATAR_SIZE );
				$content .= '<span class="fa fa-github fa-lg"></span>';
				$content .= '<a href="' . $link . '" target="_blank">' . $wordarray[count($wordarray)-1] . '</a>';
			}
			$key = md5( $date_key.strip_tags($content) );
			save_activity( $user, $key, $content, $date );
		}
    }
}

/**
 * This function imports activity from a Stackoverflow RSS.
 * @param  object $user
 */
function import_user_stackoverflow_activity( $user ){
	$stackoverflow = get_user_meta( $user->ID , 'stackoverflow_profile', TRUE );
	if( $stackoverflow != '' ){
	    if (!($x = simplexml_load_file( $stackoverflow )))
	        return;
	 
	    foreach ($x->entry as $activity) {
	    	$id = (string)$activity->id;
	    	if( get_post_by_title( $id ) == NULL ) {
		    	$link = (string)$activity->link[href];
		    	$content = (string)$activity->title;
		    	$date = date( 'Y-m-d H:i:s', strtotime( (string)$activity->published ) );
		    	$date_key = date( 'Y-m-d', strtotime( (string)$activity->published ) );
		    	$wordarray = explode(' ', $content);
		    	if (count($wordarray) > 1 ) {
		    		$wordarray[0] = '<a href="' . $link . '" target="_blank">' . $wordarray[0] . '</a>';
					$content = implode(' ', $wordarray); 
				}
				$key = md5( $date_key.strip_tags($content) );
				save_activity( $user, $key, $content, $date );
			}
	    }
	}
}

/**
 * This function imports activity from wordpress.org markup.
 * @param  object $user
 */
function import_user_wordpress_activity( $user ){
	$wordpress = get_user_meta( $user->ID , 'wordpress_org_profile', TRUE );
	if( $wordpress != '' ){
		$html = file_get_html( $wordpress );
		foreach( $html->find('ul[id=activity-list] li') as $activity){
			$content = $activity->first_child('p')->innertext;
			$date = date( 'Y-m-d', strtotime( $activity->last_child('p')->innertext ) );
			$key = md5( strip_tags($content) );
			save_activity( $user, $key, $content, $date );
		}
	}
}

/**
 * This function saves an imported activity as a post or updates a existing post.
 * @param  int $id
 * @param  string $content
 * @param  string $date
 * @param  string $date_key
 */
function save_activity( $user, $key, $content, $date ){
	$post_id = get_post_by_title( $key );
	if( $post_id != null ) {
		$repeat = get_post_meta( $post_id, 'activity_repeat', TRUE );
		//update_post_meta( $post_id, 'activity_repeat', $repeat+1 );
	} else {
		$new_activity = array(
			'post_title'    => $key,
			'post_content'  => $content,
			'post_date'		=> $date,
			'post_type' 	=> 'activity',
			'post_status'   => 'publish',
			'post_author'   => $user->ID,
		);
		$repeat = 1;
		$post_id = wp_insert_post( $new_activity );
		update_post_meta( $post_id, 'activity_repeat', $repeat );
	}
}

/**
 * This function get a post id by searching after a activity_key.
 * @param  string $activity_key
 * @return string
 */
function get_post_by_meta_value( $activity_key ){
	global $wpdb;
	$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_value = '%s' AND meta_key = 'activity_key'", $activity_key ));
	return $post_id;
}

/**
 * This function get a post object by searching after a post title.
 * @param  string $page_title
 * @return object, false
 */
function get_post_by_title($page_title) {
    global $wpdb;
        $post = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type='activity'", $page_title ));
        if ( $post )
            return get_post($post, $output);

    return null;
}

/**
 * Function for setting the import to an hourly cron job.
 */
function klandeimport_activation() {
	wp_schedule_event( time(), 'hourly', 'klandeimport_hourly_event_hook' );
}
register_activation_hook( __FILE__, 'klandeimport_activation' );

/**
 * Function that describes what to do on the hourly cron job.
 */
function klandeimport_do_this_hourly() {
	import_users_activity();
}
add_action( 'klandeimport_hourly_event_hook', 'klandeimport_do_this_hourly' );