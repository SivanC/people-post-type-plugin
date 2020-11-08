<?php

/**
 * A form that allows the user to import a json file of their choice as 
 * Person posts into the database.
 * 
 * @version 0.0.1
 * @author Sivan Cooperman
 */

 // Remove post title and content editors from People post type
add_action( 'init', function() {
    remove_post_type_support('person', 'editor');
    remove_post_type_support('person', 'title');
});

function import_register_meta_boxes( $meta_boxes ) {
    $prefix = "import_";

    $meta_boxes[] = [
        'title' => "Import JSON",
        'id' => $prefix . 'meta_box',
        'post_types' => ['import'],
    ];
}

 ?>