<?php

/**
 * Plugin Name: People Post Type
 * Description: Adds the People post type and graph database connection.
 * Version: 0.11.2
 * Author: Sivan Cooperman 
 */

/*  This file contains miscellaneous functions and adds hooks to various
    static class methods.
*/

// Metabox Form and Settings Page
include('forms/person-form.php');
include('forms/settings.php');

// Classes that handle ingress and egress of data from the form
include('classes/DataIO.php');
include('classes/ChildIO.php');
include('classes/ParentIO.php');
include('classes/PartnerIO.php');

// JSON Data import
include('import/JsonImporter.php');
include('import/import-form.php');

// Child IO filters
add_filter( 'rwmb_person_child_group_value', [ 'ChildIO', 'write_child_data' ] );
add_filter( 'rwmb_person_child_group_field_meta', [ 'ChildIO', 'read_child_data' ] );

// Parent IO filters
add_filter( 'rwmb_person_parent_group_value', [ 'ParentIO', 'write_parent_data' ] );
add_filter( 'rwmb_person_parent_group_field_meta', [ 'ParentIO', 'read_parent_data' ] );

// Partner IO filters
add_filter( 'rwmb_person_partner_group_value', [ 'PartnerIO', 'write_partner_data' ] );
add_filter( 'rwmb_person_partner_group_field_meta', [ 'PartnerIO', 'read_partner_data' ] );

// On delete
add_action( 'before_delete_post', [ 'DataIO', 'delete_data' ], 10, 1 );

// On load for importing
add_action( 'plugins_loaded', ['JsonImporter', 'init'] );


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
