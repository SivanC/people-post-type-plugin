<?php

/**
 * Plugin Name: People Post Type
 * Description: Adds the People post type and graph database connection.
 * Version: 0.7.1
 * Author: Sivan Cooperman 
 */

/*  This file contains miscellaneous functions and adds hooks to various
    static class methods.
*/

// Metabox Form
include('person-form.php');

// Classes that handle ingress and egress of data from the form
include('DataIO.php');
include('ParentIO.php');
include('ChildIO.php');

// Parent IO filters
add_filter( 'rwmb_person_parent_group_value', [ 'ParentIO', 'write_parent_data' ] );
add_filter( 'rwmb_person_parent_group_field_meta', [ 'ParentIO', 'read_parent_data' ] );

// Child IO filters
add_filter( 'rwmb_person_child_group_value', [ 'ChildIO', 'write_child_data' ] );
add_filter( 'rwmb_person_child_group_field_meta', [ 'ChildIO', 'read_child_data' ] );

// On delete
add_action( 'before_delete_post', [ 'DataIO', 'delete_data' ], 10, 1 );

/**
 * Creates a 'person'-type post with a given title. Useful for debugging when
 * it is difficult to print to the page or the console (e.g. when testing 
 * 'on submit' hooks)
 * 
 * @param title A string to be used as the post title.
 * 
 * @return error If the act of inserting the post throws any errors, they
 * are returned in the $error variable.
 */
function make_post( $title ) {
    $postarr = array(
        'post_title' => $title,

        'post_content' => '',

        'post_status' => 'publish',

        'post_type' => 'person'
    );

    $error = wp_insert_post( $postarr, true );

    return $error;
 }

/**
 * Transforms a $wpdb->get_results() two-dimensional array into a single array 
 * 
 * @param results A two-dimensional array as given by $wpdb-> get_results() with the ARRAY_A option
 * 
 * @return massaged_results The resulting one-dimensional array 
 */
function massage_results( $results ) {
    $massaged_results = array();

    foreach ( $results as $result ) {
        foreach( $result as $meta_value ) {
            $massaged_results[] = $meta_value;
        }
    }

    return $massaged_results;
}

/**
 * Takes a user's display name and returns their id from the wp_postmeta table.
 * 
 * @param display_name A unique display name/post_title in the wp_postmeta table.
 * 
 * @return id The ID of the post containing $display_name as the post title.
 */
function get_id( $display_name ) {

    global $wpdb;

    $query = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", "post_title", $display_name);

    $results = $wpdb->get_row( $query, ARRAY_A );

    $id = count($results) != 0 ? $results['post_id'] : -1;

    return $id;

}

?>
