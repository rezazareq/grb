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

    <p class="hide-if-no-js trgrabber-image-container-<?php echo $id; ?>"><a href="#" class="trgrabber-add-media trgrabber-media-edit trgrabber-media-edit-<?php echo $id; ?>" data-title="<?php echo $title; ?>" data-button="<?php echo $label_use; ?>" data-id="<?php echo $id; ?>" data-postid="<?php echo $post->ID; ?>"><?php echo $link_title; ?></a></p>

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
                
    if( isset($_REQUEST['post_type']) and $_REQUEST['post_type'] == 'movies' or isset($current_screen->post_type) and $current_screen->post_type == 'movies' or isset( $_GET['post'] ) and get_post_type( $_GET['post'] ) == 'movies' ) {
        $return = 1;
    }elseif( isset($_REQUEST['post_type']) and $_REQUEST['post_type'] == 'series' or isset($current_screen->post_type) and $current_screen->post_type == 'series' or isset( $_GET['post'] ) and get_post_type( $_GET['post'] ) == 'series' ) {
        $return = 2;
    }
    
    return $return;
    
}

function tr_grabber_count_seasons( $post_id, $display = true, $rest = false ) {
    
    $term_list = wp_get_post_terms($post_id, 'seasons', array("fields" => "all"));
    
    if( !is_wp_error( $term_list ) and isset( $term_list ) ) {

        $total_terms = isset($term_list) ? count($term_list) : 0;
        
        if( $rest == true and $total_terms > 0 ) {  $total_terms = $total_terms-1; }

        $return = $total_terms;

    }
    
    if( $display == true ) { echo $return; }else{ return $return; }
    
}

function tr_grabber_count_episodes( $post_id, $season_current = NULL, $display = true, $rest = false ) {
    
    $term_list = wp_get_post_terms($post_id, 'episodes', array("fields" => "all"));
    
    if( !is_wp_error($term_list) and isset($term_list) ) {
        if( isset( $season_current ) ) {
            
            foreach ($term_list as &$count_episode_season) {
                if( get_term_meta($count_episode_season->term_id, 'season_number', true) == $season_current ) {
                    
                    $array_episodes_season[] = $count_episode_season->term_id;

                }

            }

            $return = isset( $array_episodes_season ) ? count($array_episodes_season) : 0;

            if( $rest == true and $return > 0 ) {  $return = $return-1; }
            
        }else{
            
            $return = isset( $term_list ) ? count($term_list) : 0;
            if( $rest == true and $return > 0 ) {  $return = $return-1; }
            
            $return = $return;
            
        }

    }
    
    if( $display == true ) { echo $return; }else{ return $return; }
    
}

function tr_grabber_list_seasons( $post_id =  NULL, $season = NULL ) {
    
    if( $season == '' ) {
    
        $seasons_list = wp_get_post_terms($post_id, 'seasons', array('orderby' => 'meta_value_num', 'order' => 'ASC', 'fields' => 'all', 'meta_query' => [[
        'key' => 'season_number',
        'type' => 'NUMERIC',
      ]],) );
        
    }else{
        
        $args = array(
            array(
                'relation' => 'AND',
                'tr_id_post' => array(
                    'key' => 'tr_id_post',
                    'compare' => '=',
                    'value' => $post_id,
                ),
                'season_number' => array(
                    'key' => 'season_number',
                    'compare' => '=',
                    'value' => $season,
                ),
            ),
        );
        
        $seasons_list = wp_get_post_terms($post_id, 'seasons', array('orderby' => 'meta_value_num', 'order' => 'ASC', 'fields' => 'all', 'meta_query' => $args, ) );
        
    }
    
    return $seasons_list;
    
}

function tr_grabber_list_episodes( $post_id =  NULL, $season = NULL ) {
    
    if( $season == '' ) {
    
        $episodes_list = wp_get_post_terms($post_id, 'episodes', array('orderby' => 'meta_value_num', 'order' => 'ASC', 'fields' => 'all', 'meta_query' => [[
        'key' => 'episode_number',
        'type' => 'NUMERIC',
        ]],) );
        
    }else{
        
        if( $season == 'special' ) {
            
            $args = array(
                array(
                    'relation' => 'AND',
                    'episode_number' => array(
                        'key' => 'episode_number',
                        'type' => 'NUMERIC',
                    ),
                    'season_number' => array(
                        'key' => 'season_special',
                        'compare' => '=',
                        'value' => 1,
                    ),
                ),
            );
            
        }else{
            
            $args = array(
                array(
                    'relation' => 'AND',
                    'episode_number' => array(
                        'key' => 'episode_number',
                        'type' => 'NUMERIC',
                    ),
                    'season_number' => array(
                        'key' => 'season_number',
                        'compare' => '=',
                        'value' => $season,
                    ),
                ),
                /*
                array(
                    'relation' => 'OR',
                    'episode_number' => array(
                        'key' => 'episode_number',
                        'type' => 'NUMERIC',
                    ),
                    'season_number' => array(
                        'key' => 'season_special',
                        'compare' => '=',
                        'value' => 1,
                    ),
                ),*/
            );
            
        }
        
        $episodes_list = wp_get_post_terms($post_id, 'episodes', array('orderby' => 'meta_value_num', 'order' => 'ASC', 'fields' => 'all', 'meta_query' => $args, ) );
        
    }
    
    return $episodes_list;
    
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
        if(tr_check_type($post->ID)==2 and is_array($runtime_field) and !empty( $runtime_field )){
            $runtime_field = implode('m, ', $runtime_field).'m ';
        }elseif(tr_check_type($post->ID)==2 and !is_array($runtime_field) and !empty( $runtime_field )){
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