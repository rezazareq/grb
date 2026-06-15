<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function tr_grabber_function (){
    require_once(TR_GRABBER_PLUGIN_DIR.'inc/config/index.php');
}

function tr_grabber_meta_box() {
	add_meta_box(
		'tr_grabber_featured_meta_box',
		__('Backdrop', 'tr-grabber'),
		'tr_grabber_featured_meta_box_function',
		array('movies', 'series'),
		'side',
		'low'
	);
    
	add_meta_box(
		'additional_information_meta_box',
		__('Additional Information', 'tr-grabber'),
		'show_additional_information_meta_box',
		array('movies', 'series'),
		'normal',
		'high'
	);
             
    add_meta_box(
        'links_meta_box',
        __('Links', 'tr-grabber'),
        'show_links_meta_box',
        array('movies'),
        'normal',
        'low'
    );
        
}
add_action( 'add_meta_boxes', 'tr_grabber_meta_box' );

function tr_grabber_featured_meta_box_function( $post ) {
    
    $id = 'backdrop';
    $title = __('Backdrop', 'tr-grabber');
    $label_set = __('Set backdrop image', 'tr-grabber');
    $label_use = __('Use as backdrop', 'tr-grabber');
    $label_remove = __('Remove backdrop', 'tr-grabber');

    $photo_id = get_post_meta( $post->ID, TR_GRABBER_FIELD_BACKDROP, true );

    if( $photo_id ) {
        $link_title = wp_get_attachment_image( $photo_id, 'medium', false, array( 'style' => 'width:100%;height:auto;', ) );
        $hide_remove_button = '';
    }
    else {
        $photo_id = -1;
        $link_title = $label_set;
        $hide_remove_button = 'display: none;';
    }
    ?>

    <p class="hide-if-no-js trgrabber-image-container-<?php echo $id; ?>"><a href="#" class="trgrabber-add-media trgrabber-media-edit trgrabber-media-edit-<?php echo $id; ?>" data-title="<?php echo $title; ?>" data-button="<?php echo $label_use; ?>" data-id="<?php echo $id; ?>" data-postid="<?php echo $post->ID; ?>" type="button"><?php echo $link_title; ?></a></p>

    <p style="<?php echo $hide_remove_button; ?>"><a href="#" data-title="<?php echo $label_set; ?>" class="trgrabber-media-delete"><?php echo $label_remove; ?></a></p>
<?php   
}

function trgrabber_curl( $url ) {

    $output = '';
    
    $response = wp_remote_get( $url, array( 'sslverify' => false ) );
    if ( is_array( $response ) ) {
        $header = $response['headers'];
        $output = $response['body'];
    }

    return $output;
}

function tr_grabber_select_taxonomy( $tax, $select=0 ) {
    
    $return = '';
    
    $taxonomy = get_categories( array(
        'orderby' => 'name',
        'hide_empty' => 0,
        'taxonomy' => $tax
    ) );

    foreach ( $taxonomy as $tax ) {
        $return.='<option '.selected( $select, $tax->term_id, false ).' value="'.$tax->term_id.'">'.$tax->name.'</option>';
    }
    
    return $return;
    
}

function tr_grabber_type( $id = NULL ) {
    global $pagenow, $current_screen;
    
    $return = 0;
                
    if( isset($_REQUEST['post_type']) and $_REQUEST['post_type'] == 'movies' or isset($current_screen->post_type) and $current_screen->post_type == 'movies' or isset( $_GET['post'] ) and get_post_type( intval($_GET['post']) ) == 'movies' ){
        $return = 1;
    }elseif( isset($_REQUEST['post_type']) and $_REQUEST['post_type'] == 'series' or isset($current_screen->post_type) and $current_screen->post_type == 'series' or isset( $_GET['post'] ) and get_post_type( intval($_GET['post']) ) == 'series' ){
        $return = 2;
    }
    
    return $return;
    
}

/**
 * Count distinct seasons for a series
 *
 * Refactored to use TRG_DB for efficient database queries.
 *
 * @param int  $post_id  Series post ID
 * @param bool $display  Whether to echo the result (default: true)
 * @param bool $rest     If true, exclude one from the count (legacy compatibility)
 * @return int|void Count of seasons or void if display=true
 */
function tr_grabber_count_seasons( $post_id, $display = true, $rest = false ) {
    $count = TRG_DB::count_seasons( intval( $post_id ) );
    
    if( $rest && $count > 0 ) {
        $count--;
    }

    if( $display ) {
        echo intval( $count );
    } else {
        return intval( $count );
    }
}

/**
 * Count episodes for a series, optionally filtered by season
 *
 * Refactored to use TRG_DB for efficient database queries.
 *
 * @param int      $post_id         Series post ID
 * @param int|null $season_current  Optional season number to filter
 * @param bool     $display         Whether to echo the result (default: true)
 * @param bool     $rest            If true, exclude one from the count (legacy compatibility)
 * @return int|void Count of episodes or void if display=true
 */
function tr_grabber_count_episodes( $post_id, $season_current = NULL, $display = true, $rest = false ) {
    $count = TRG_DB::count_episodes( intval( $post_id ), $season_current !== null ? intval( $season_current ) : null );
    
    if( $rest && $count > 0 ) {
        $count--;
    }

    if( $display ) {
        echo intval( $count );
    } else {
        return intval( $count );
    }
}

/**
 * Get all seasons for a series
 *
 * Refactored to use TRG_DB. Returns season objects with term_id and name properties
 * for backward compatibility with theme consumers.
 *
 * @param int      $post_id Series post ID
 * @param int|null $season  Optional: if set, filter to a specific season (legacy behavior)
 * @return array Array of season objects
 */
function tr_grabber_list_seasons( $post_id = NULL, $season = NULL ) {
    $post_id = intval( $post_id );
    
    if( null !== $season ) {
        // Legacy: specific season lookup
        $season = intval( $season );
        $episodes = TRG_DB::get_episodes( $post_id, $season );
        
        // Build unique season objects from episodes
        $seasons = array();
        $season_numbers = array();
        
        foreach ( $episodes as $episode ) {
            if ( ! in_array( $episode->season_number, $season_numbers, true ) ) {
                $season_numbers[] = $episode->season_number;
                
                // Create lightweight season object
                $season_obj = new stdClass();
                $season_obj->term_id = $episode->term_id ?? 0;
                $season_obj->season_number = $episode->season_number;
                $season_obj->name = 'Season ' . $episode->season_number; // Fallback name
                
                $seasons[] = $season_obj;
            }
        }
        
        return $seasons;
    }
    
    // Get all distinct seasons
    $db_seasons = TRG_DB::get_seasons( $post_id );
    $seasons = array();
    
    foreach ( $db_seasons as $db_season ) {
        // Fetch first episode of this season to get term_id
        $episodes = TRG_DB::get_episodes( $post_id, intval( $db_season->season_number ) );
        
        $season_obj = new stdClass();
        $season_obj->term_id = ! empty( $episodes ) ? intval( $episodes[0]->term_id ) : 0;
        $season_obj->season_number = intval( $db_season->season_number );
        $season_obj->name = 'Season ' . $db_season->season_number; // Fallback name
        
        $seasons[] = $season_obj;
    }
    
    return $seasons;
}

/**
 * Get all episodes for a series, optionally filtered by season
 *
 * Refactored to use TRG_DB. Returns episode objects with term_id, name, season_number,
 * episode_number properties for backward compatibility with theme consumers.
 *
 * @param int      $post_id Series post ID
 * @param int|null $season  Optional season number filter; 'special' for special episodes
 * @return array Array of episode objects
 */
function tr_grabber_list_episodes( $post_id = NULL, $season = NULL ) {
    $post_id = intval( $post_id );
    
    if( $season === 'special' ) {
        // Get special episodes (is_special = 1)
        return TRG_DB::get_episodes( $post_id, null, true );
    } elseif( null !== $season && $season !== '' ) {
        // Get specific season episodes
        return TRG_DB::get_episodes( $post_id, intval( $season ) );
    } else {
        // Get all episodes
        return TRG_DB::get_episodes( $post_id );
    }
}

function trgrabber_base64en($string) {
    return base64_encode($string);
}

function trgrabber_base64de($string) {
    return base64_decode($string);
}

function trgrabber_head() {
    if( get_query_var('tr_post_type')!='' and is_category() ){
        echo '<meta name="robots" content="noindex, follow">'."\n\r";
    }
}
add_action('wp_head', 'trgrabber_head');

if ( !function_exists( 'trposts_func' ) ) :
function trposts_func($atts) {
    if(locate_template( array( 'inc/shortcodes.php' ) ) == '' ) return;
    return trposts_func_theme($atts);
}
add_shortcode( 'trposts', 'trposts_func' );
endif;

function trgrabber_info( $show = NULL, $tag = 'span', $class = '', $display = TRUE ) {
    global $post;
    
    $return = '';
    
    $class = $class == '' ? '' : ' class="'.$class.'"';

    if( $show == 'year' ){
        $date_field = get_post_meta($post->ID, TR_GRABBER_FIELD_DATE, true);
        $date_field_year = $date_field;
        if($date_field!=''){
            $date_field = explode('-', $date_field);
            $date_field_year = $date_field['0'] == '' ? '' : $date_field['0'];
        }
        $date_field_year = $date_field_year == '' ? __('Unknown', 'tr-grabber') : $date_field_year;
        $return.= '<'.$tag.$class.'>'.$date_field_year.'</'.$tag.'>';
    }
    
    if( $show == 'runtime' ) {
        $runtime_field = get_post_meta($post->ID, TR_GRABBER_FIELD_RUNTIME, true);
        if(tr_grabber_type($post->ID)==2 and is_array($runtime_field) and !empty( $runtime_field )){
            $runtime_field = implode('m, ', $runtime_field).'m ';
        }elseif(tr_grabber_type($post->ID)==2 and !is_array($runtime_field) and !empty( $runtime_field )){
            $runtime_field = implode('m, ', explode(',', $runtime_field)).'m';
        }elseif( !empty($runtime_field) ){
            $runtime_field = $runtime_field;
        }else{
            $runtime_field = __('Unknown', 'tr-grabber');
        }

        if($runtime_field!=''){
            $return.= '<'.$tag.$class.'>'.$runtime_field.'</'.$tag.'>';
        }
    }
    
    if( $display == TRUE ) { echo $return; }else{ return $return; }
    
}

function trgrabber_img($id, $size, $title=NULL, $taxonomy=NULL, $text=0, $exclude=NULL){

    $return = '';
    
    if( $taxonomy == 'episodes' ) { // episodes
        
        $image_hotlink = get_term_meta( $id, 'still_path_hotlink', true );
        $image = get_term_meta( $id, 'still_path', true );
        
        if( isset($image) and !empty($image) ) {
            $return = $image;
        }elseif( isset( $image_hotlink ) and !empty( $image_hotlink ) ) {
            
            if( $size == 'episode' ) $size = 'w185';
            if( $size == 'episodes' ) $size = 'w92';
            
            if (filter_var($image_hotlink, FILTER_VALIDATE_URL) === FALSE) {
                
                $return = '<img src="//image.tmdb.org/t/p/'.$size.$image_hotlink.'" alt="'.sprintf( __('Image %s', 'toroplay'), get_the_title($id)).'">';
                
            }else{
                
                $return = '<img src="'.$image_hotlink.'" alt="'.sprintf( __('Image %s', 'toroplay'), get_the_title($id)).'">';
                
            }
            
        }
        
    }elseif( $taxonomy == 'seasons' ) { // seasons
        
        $image_hotlink = get_term_meta( $id, 'poster_path_hotlink', true );
        $image = get_term_meta( $id, 'poster_path', true );
        
        if( isset($image) and !empty($image) ) {
            $return = $image;
        }elseif( isset( $image_hotlink ) and !empty( $image_hotlink ) ) {
            
            if( $size == 'thumbnail' ) $size = 'w185';
            
            if (filter_var($image_hotlink, FILTER_VALIDATE_URL) === FALSE) {
                
                $return = '<img src="//image.tmdb.org/t/p/'.$size.$image_hotlink.'" alt="'.sprintf( __('Image %s', 'toroplay'), get_the_title($id)).'">';
                
            }else{
                
                $return = '<img src="'.$image_hotlink.'" alt="'.sprintf( __('Image %s', 'toroplay'), get_the_title($id)).'">';
                
            }
            
        }
        
    }else{ // posts
        
        if( get_the_post_thumbnail($id, $size) ) {
            
            $return = get_the_post_thumbnail( $id, $size );
            
        } elseif( get_post_meta($id, TR_GRABBER_POSTER_HOTLINK, true) != '' ) {
            
            if( $size == 'thumbnail' ) $size = 'w185';
            if( $size == 'widget' ) $size = 'w92';
            
            if (filter_var(get_post_meta($id, TR_GRABBER_POSTER_HOTLINK, true), FILTER_VALIDATE_URL) === FALSE) {
            
                $return = '<img src="//image.tmdb.org/t/p/'.$size.get_post_meta($id, TR_GRABBER_POSTER_HOTLINK, true).'" alt="'.sprintf( __('Image %s', 'toroplay'), get_the_title($id)).'">';
                
            }else{
                
                $return = '<img src="'.get_post_meta($id, TR_GRABBER_POSTER_HOTLINK, true).'" alt="'.sprintf( __('Image %s', 'toroplay'), get_the_title($id)).'">';
                
            }
            
        }
        
    }
    
    return empty( $return ) ? '<img src="'.get_template_directory_uri().'/img/cnt/noimg-'.$size.'.png" alt="'.sprintf( __('Image %s', 'toroplay'), $title).'">' : $return;

}

function trgrabberremoveElementWithValue($array, $key, $value){
    foreach($array as $subKey => $subArray){
      if($subArray[$key] == $value){
           unset($array[$subKey]);
      }
    }
    return $array;
}

function tr_grabber_get_domain_from_url($url) {
    
    $parse = parse_url($url);
    return $parse['host'];

}

function tr_grabber_frame_servers() {
    global $config_grabber;
    
    $server = array();
    
    $server[] = $config_grabber['hideopenload'] == 1 ? 'openload.co' : array();
    $server[] = $config_grabber['hidestreamango'] == 1 ? 'streamango.com' : array();
    $server[] = $config_grabber['hidevidoza'] == 1 ? 'vidoza.net' : array();
    $server[] = $config_grabber['hidestreamplay'] == 1 ? 'streamplay.to' : array();
    $server[] = $config_grabber['hideflashx'] == 1 ? 'flashx.tv' : array();
    $server[] = $config_grabber['hideflashx'] == 1 ? 'www.flashx.tv' : array();
    $server[] = $config_grabber['hidestreamcherry'] == 1 ? 'streamcherry.com' : array();
    $server[] = $config_grabber['hidethevideo'] == 1 ? 'thevideo.website' : array();
    
    $server = $config_grabber['hideframes'] == 1 ? $server : array();
    
    return array_filter($server);
    
}

function tr_grabber_check_shortcode($content) {
    $pattern = get_shortcode_regex();
    preg_match('/'.$pattern.'/s', $content, $matches);
    return isset($matches[0]) ? 1 : 0;
}
