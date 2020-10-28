<?php

/**
 * This function receives a GET request made by an ajax call made by the metabox plugin in 'person-form.php'.
 * Returns true or false based on whether the post title is unique or not, and other validation.
 */
function validate( $request ) {

    // Because this function is called via ajax we need to call wp-load to get access to the database.
    require_once( $_GET['path'] . 'wp-load.php' );

    global $wpdb;

    $unique_display_name = false;
    // Return false if there is no post_title sent
    if ( !empty( $request['post_title'] ) ) {
        
        // Check if the name is unique and return accordingly
        $query = $wpdb->prepare("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s;",
        "post_title", $request['post_title'] );
        $results = $wpdb->get_results($query, ARRAY_A);

        // If there are no duplicate entries
        if ( count($results) == 0 ) {
            $unique_display_name = true;
        // If there is an entry found with a duplicate name
        // If the given post id matches the post id found in the table (implying the post id is provided (aka the post is being updated and not created))
        } elseif ( $request['id'] == $results[0]['post_id'] ) {
            $unique_display_name = true;
        }
    }

    // Here, the echo() actually embeds the boolean in the HTTP response
    echo(json_encode($unique_display_name));
}
// Calling the function on a get request
validate( $_GET );

?>