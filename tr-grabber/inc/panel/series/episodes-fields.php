<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action('episodes_add_form_fields','episodes_add_form_fields');
add_action('episodes_edit_form_fields','episodes_edit_form_fields');

function episodes_add_form_fields() {
    
    $id = 'poster';
    $title = __('Poster', 'tr-grabber');
    $label_set = __('Set poster', 'tr-grabber');
    $label_use = __('Use as poster', 'tr-grabber');
    $label_remove = __('Remove poster', 'tr-grabber');
    $link_title = $label_set;
    
?>

<div class="form-field term-episode-wrap">
	<label><?php _e('Search serie', 'tr-grabber'); ?></label>
    <div class="tr-grabber-suggest-content">
        <input class="trselect_search_inp" type="text" value="<?php if( isset($_GET['tr_id_post']) ){ echo get_the_title( intval($_GET['tr_id_post']) ); } ?>">
        <span class="dashicons dashicons-search"></span>
    </div>
    <div class="trsrcbx trselectcnt trselectseasons" style="display:none"></div>
    
    <div class="form-required">
        <label><?php _e('ID Serie', 'tr-grabber'); ?></label>
        <input id="serie_id_grabber" aria-required="true" name="serie_id" type="number" placeholder="<?php _e('Enter the post ID or use the search engine above', 'tr-grabber'); ?>" value="<?php if( isset($_GET['tr_id_post']) ){ echo intval($_GET['tr_id_post']); } ?>">
    </div>
</div>

<div class="form-field form-required term-season-wrap">
	<label><?php _e('Season number', 'tr-grabber'); ?></label>
	<select name="season_number">
        <option value=""><?php _e('Select serie', 'tr-grabber'); ?></option>
    </select>
</div>

<div class="form-field form-required term-episode-wrap">
	<label><?php _e('Episode number', 'tr-grabber'); ?></label>
	<input aria-required="true" name="episode" type="number" value="">
</div>

<div class="form-field term-subtitle-wrap">
	<label><?php _e('Subtitle', 'tr-grabber'); ?></label>
	<input name="subtitle" type="text" value="">
</div>

<div class="form-field term-overview-wrap">
	<label><?php echo _e('Synopsis', 'tr-grabber'); ?></label>
    <?php wp_editor( '', 'content', array( 'textarea_rows' => 5, 'media_buttons' => true ) ); ?>
</div>

<div class="form-field term-date-wrap">
	<label><?php _e('Air Date', 'tr-grabber'); ?></label>
	<input name="date" type="date" value="">
</div>

<div class="form-field term-gueststars-wrap">
	<label><?php _e('Guest stars', 'tr-grabber'); ?></label>
	<input name="guest_stars" type="text" value="">
</div>

<div class="form-field term-image-wrap">
    <label><?php _e('Poster', 'tr-grabber'); ?></label>
    <div id="image"></div>
	<input class="tr-grabber-media" name="image_hotlink" type="text" value="" placeholder="<?php _e('External url', 'tr-grabber'); ?>">
	<input id="tr-grabber-media-content" name="image" type="hidden" value="">
    <button data-title="<?php echo $title; ?>" data-button="<?php echo $label_use; ?>" data-id="<?php echo $id; ?>" data-postid="0" type="button" class="button button-primary tr-grabber-media-tax"><?php _e('Set', 'tr-grabber'); ?></button>
    <button style="display:none" type="button" class="button button-primary trgrabber-media-tax-delete tr-rmv"><span class="dashicons dashicons-no-alt"></span></button>
</div>

<textarea style="display:none" id="overview" name="overview"></textarea>

<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'trgrabberlive' ); ?>">
<input type="hidden" name="type" value="2" class="tr-grabber-type">
<input type="hidden" name="grabber-type" value="episode">

<p class="submit"><button type="button" class="button button-primary tr-grabber-tax-valid-form-episode"><?php _e('Save', 'tr-grabber'); ?></button></p>

<?php
}

function episodes_edit_form_fields($tag) {
    
    $term_id = $tag->term_id;
    $title = __('Poster', 'tr-grabber');
    $label_use = __('Use as poster', 'tr-grabber');
    
    // Fetch episode data from TRG_DB
    $episode = null;
    $episodes = TRG_DB::get_episodes( 0, null, false );
    foreach ( $episodes as $ep ) {
        if ( intval( $ep->term_id ) === $term_id ) {
            $episode = $ep;
            break;
        }
    }
    
    // Fallback to term meta if not found in DB
    if ( ! $episode ) {
        $image = get_term_meta( $term_id, 'poster_path', true ) == '' ? '' : get_term_meta( $term_id, 'still_path', true );
        $image_url = get_term_meta( $term_id, 'still_path', true ) == '' ? '' : '<img src="'.wp_get_attachment_image_src(get_term_meta( $term_id, 'still_path', true ), 'medium')[0].'" alt="'.__('image', 'tr-grabber').'">';
        $image_hotlink = get_term_meta( $term_id, 'still_path_hotlink', true ) == '' ? '' : get_term_meta( $term_id, 'still_path_hotlink', true );
        $display = $image == '' ? ' style="display:none"' : '';
        $name = get_term_meta($term_id, 'name', true) == '' ? '' : get_term_meta($term_id, 'name', true);
        $content = get_term_meta($term_id, 'overview', true) == '' ? '' : get_term_meta($term_id, 'overview', true);
        $date = get_term_meta($term_id, 'air_date', true) == '' ? '' : get_term_meta($term_id, 'air_date', true);
        $guest = get_term_meta($term_id, 'guest_stars', true) == '' ? '' : get_term_meta($term_id, 'guest_stars', true);
        $post_id = get_term_meta($term_id, 'tr_id_post', true);
    } else {
        $image = $episode->still_path ?? '';
        $image_url = $image == '' ? '' : '<img src="'.$image.'" alt="'.__('image', 'tr-grabber').'">';
        $image_hotlink = $episode->still_path_hotlink ?? '';
        $display = $image == '' ? ' style="display:none"' : '';
        $name = $episode->name ?? '';
        $content = $episode->overview ?? '';
        $date = $episode->air_date ?? '';
        $guest = $episode->guest_stars ?? '';
        $post_id = $episode->post_id ?? 0;
    }
?>

<tr class="form-field term-advancedbt-wrap">
    <th scope="row"></th>
    <td>
        <a target="_blank" class="button" href="<?php echo 'post.php?post='.intval($post_id).'&amp;action=edit'; ?>"><?php _e('View Post', 'tr-grabber'); ?></a>
        <button class="grabberadvanced button" type="button"><?php _e('Advanced form', 'tr-grabber'); ?></button>
    </td>
</tr>

<tr class="form-field">  
    <th scope="row" valign="top">
        <label><?php _e('Subtitle', 'tr-grabber'); ?></label>  
    </th>  
    <td>  
        <input type="text" name="subtitle" value="<?php echo esc_attr( $name ); ?>">
    </td>  
</tr>

<tr class="form-field">  
    <th scope="row" valign="top">
        <label><?php _e('Air Date', 'tr-grabber'); ?></label>  
    </th>  
    <td>  
        <input type="date" name="date" value="<?php echo esc_attr( $date ); ?>">
    </td>  
</tr>

<tr class="form-field">  
    <th scope="row" valign="top">
        <label><?php _e('Guest stars', 'tr-grabber'); ?></label>  
    </th>  
    <td>  
        <input type="text" name="guest_stars" value="<?php echo esc_attr( $guest ); ?>">
    </td>  
</tr>

<tr class="form-field term-overview-wrap">
    <th scope="row" valign="top">
        <label><?php _e('Synopsis', 'tr-grabber'); ?></label>  
    </th>
    <td>
        <?php wp_editor( $content, 'overview', array( 'textarea_rows' => 5, 'media_buttons' => true ) ); ?>
    </td>
</tr>

<tr class="form-field term-image-wrap">
    <th scope="row" valign="top">
        <label><?php _e('Poster', 'tr-grabber'); ?></label>  
    </th>
    <td>        
        <div class="term-image-wrap">
            <div id="image"><?php echo $image_url; ?></div>
            <input class="tr-grabber-media" name="image_hotlink" type="text" value="<?php echo esc_attr( $image_hotlink ); ?>" placeholder="<?php _e('External url', 'tr-grabber'); ?>">
            <input id="tr-grabber-media-content" name="image" type="hidden" value="<?php echo esc_attr( $image ); ?>">
            <button data-title="<?php echo $title; ?>" data-button="<?php echo $label_use; ?>" data-id="<?php echo $term_id; ?>" data-postid="0" type="button" class="button button-primary tr-grabber-media-tax"><?php _e('Set', 'tr-grabber'); ?></button>
            <button<?php echo $display; ?> type="button" class="button button-primary trgrabber-media-tax-delete tr-rmv"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
    </td>
</tr>

<?php
}

/**
 * Save episode metadata on create_episodes hook
 *
 * Stores relational data (post_id, season, episode) to TRG_DB.
 * Keeps image fields in term meta for simplicity.
 *
 * @param int $term_id Episode term ID
 */
function save_episodes_custom_meta( $term_id ) {
    
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'trgrabberlive' ) ) {
        return;
    }
    
    // Verify capability
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }

    $post_id = isset( $_POST['serie_id'] ) ? intval( $_POST['serie_id'] ) : 0;
    $season_number = isset( $_POST['season_number'] ) ? intval( $_POST['season_number'] ) : 0;
    $episode_number = isset( $_POST['episode'] ) ? intval( $_POST['episode'] ) : 0;

    // Save image fields to term meta (keep simple)
    if ( isset( $_POST['image_hotlink'] ) ) {
        $hotlink = sanitize_text_field( $_POST['image_hotlink'] );
        if ( $hotlink ) {
            update_term_meta( $term_id, 'still_path_hotlink', esc_url( $hotlink ) );
        } else {
            delete_term_meta( $term_id, 'still_path_hotlink' );
        }
    }
    
    if ( isset( $_POST['image'] ) ) {
        $image = sanitize_text_field( $_POST['image'] );
        if ( $image ) {
            update_term_meta( $term_id, 'still_path', sanitize_file_name( $image ) );
        } else {
            delete_term_meta( $term_id, 'still_path' );
        }
    }

    // Build episode data for TRG_DB
    $episode_data = array(
        'post_id'       => $post_id,
        'term_id'       => $term_id,
        'season_number' => $season_number,
        'episode_number' => $episode_number,
        'is_special'    => 0,
        'name'          => isset( $_POST['subtitle'] ) ? sanitize_text_field( $_POST['subtitle'] ) : '',
        'air_date'      => isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '',
        'overview'      => isset( $_POST['overview'] ) ? wp_kses_post( $_POST['overview'] ) : '',
        'guest_stars'   => isset( $_POST['guest_stars'] ) ? sanitize_text_field( $_POST['guest_stars'] ) : '',
    );

    // Upsert to TRG_DB
    TRG_DB::upsert_episode( $episode_data );

    // Update term slug and name
    $slug_episodes = TR_GRABBER_SLUG_EPISODES;
    $name_episodes = TR_GRABBER_TITLE_EPISODES;
    
    $vars = array( '{name}', '{season}', '{episode}' );
    $vars_replace = array( get_the_title( $post_id ), $season_number, $episode_number );
    
    $slug_episodes = str_replace( $vars, $vars_replace, $slug_episodes );
    $name_episodes = str_replace( $vars, $vars_replace, $name_episodes );
    
    wp_update_term( $term_id, 'episodes', array(
        'name' => $name_episodes,
        'slug' => $slug_episodes
    ));
    
    // Link episode to series
    wp_set_object_terms( $post_id, $term_id, 'episodes', true );
    
    // Update episode count on series post
    update_post_meta( $post_id, TR_GRABBER_FIELD_NEPISODES, tr_grabber_count_episodes( $post_id, NULL, false ) );
}

add_action( 'create_episodes', 'save_episodes_custom_meta', 10, 2 );

/**
 * Save episode metadata on edited_episodes hook
 *
 * Updates relational data in TRG_DB.
 *
 * @param int $term_id Episode term ID
 */
function save_episodesedit_custom_meta( $term_id ) {
    
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'trgrabberlive' ) ) {
        return;
    }
    
    // Verify capability
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }

    // Save image fields to term meta
    if ( isset( $_POST['image_hotlink'] ) ) {
        $hotlink = sanitize_text_field( $_POST['image_hotlink'] );
        if ( $hotlink ) {
            update_term_meta( $term_id, 'still_path_hotlink', esc_url( $hotlink ) );
        } else {
            delete_term_meta( $term_id, 'still_path_hotlink' );
        }
    }
    
    if ( isset( $_POST['image'] ) ) {
        $image = sanitize_text_field( $_POST['image'] );
        if ( $image ) {
            update_term_meta( $term_id, 'still_path', sanitize_file_name( $image ) );
        } else {
            delete_term_meta( $term_id, 'still_path' );
        }
    }

    // Get existing episode from DB to preserve post/season/episode keys
    $episodes = TRG_DB::get_episodes( 0, null, false );
    $existing_episode = null;
    
    foreach ( $episodes as $ep ) {
        if ( intval( $ep->term_id ) === $term_id ) {
            $existing_episode = $ep;
            break;
        }
    }
    
    if ( ! $existing_episode ) {
        return; // Episode not in DB yet
    }

    // Update episode data in TRG_DB
    $episode_data = array(
        'post_id'       => intval( $existing_episode->post_id ),
        'term_id'       => $term_id,
        'season_number' => intval( $existing_episode->season_number ),
        'episode_number' => intval( $existing_episode->episode_number ),
        'is_special'    => intval( $existing_episode->is_special ),
        'name'          => isset( $_POST['subtitle'] ) ? sanitize_text_field( $_POST['subtitle'] ) : $existing_episode->name,
        'air_date'      => isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : $existing_episode->air_date,
        'overview'      => isset( $_POST['overview'] ) ? wp_kses_post( $_POST['overview'] ) : $existing_episode->overview,
        'guest_stars'   => isset( $_POST['guest_stars'] ) ? sanitize_text_field( $_POST['guest_stars'] ) : $existing_episode->guest_stars,
    );

    TRG_DB::upsert_episode( $episode_data );

    // Update term slug and name
    $slug_episodes = TR_GRABBER_SLUG_EPISODES;
    $name_episodes = TR_GRABBER_TITLE_EPISODES;
    
    $vars = array( '{name}', '{season}', '{episode}' );
    $vars_replace = array( get_the_title( intval( $existing_episode->post_id ) ), intval( $existing_episode->season_number ), intval( $existing_episode->episode_number ) );
    
    $slug_episodes = str_replace( $vars, $vars_replace, $slug_episodes );
    $name_episodes = str_replace( $vars, $vars_replace, $name_episodes );
    
    wp_update_term( $term_id, 'episodes', array(
        'name' => $name_episodes,
        'slug' => $slug_episodes
    ));

    // Update episode count
    update_post_meta( intval( $existing_episode->post_id ), TR_GRABBER_FIELD_NEPISODES, tr_grabber_count_episodes( intval( $existing_episode->post_id ), NULL, false ) );
}

add_action( 'edited_episodes', 'save_episodesedit_custom_meta', 10, 2 );

/**
 * Handle episode deletion
 *
 * Removes episode from TRG_DB.
 *
 * @param int    $term_id  Episode term ID
 * @param string $taxonomy Taxonomy name
 */
function delete_episodes_grabber( $term_id, $taxonomy ) {
    
    if( $taxonomy !== 'episodes' ) {
        return;
    }
    
    // Delete from TRG_DB
    TRG_DB::delete_episode_by_term( intval( $term_id ) );
}

add_action( 'pre_delete_term', 'delete_episodes_grabber', 10, 2 );
