<?php

/**
 * A form that allows the user to import a json file of their choice as 
 * Person posts into the database.
 * 
 * @version 0.1.0
 * @author Sivan Cooperman
 */
function import_register_settings_pages( $settings_pages ) {
    $settings_pages[] = [

        'id' => 'import_settings',

        'menu_title' => "Import",

        'style' => 'no-boxes',

        'columns' => 2,

        'submit_button' => "Save Changes",

    ];
    return $settings_pages;
}
add_filter( 'mb_settings_pages', 'import_register_settings_pages' );

function import_register_settings_meta_boxes( $meta_boxes ) {

    $prefix = 'import_settings_';

    $meta_boxes[] = [

        'id' => 'settings',

        'settings_pages' => 'import_settings',

        'fields' => [

            [

                'id' => $prefix . 'import_id',

                'type' => 'text',

                'name' => "Record ID",

                'label_description' => "Enter the ID of the record to be imported. -1 for all records.",

                'std' => "",

            ],

        ],

    ];
    return $meta_boxes;
}
add_filter( 'rwmb_meta_boxes', 'import_register_settings_meta_boxes' );

function call_post_import() {
    $record_id = rwmb_meta('import_settings_import_id', ['object_type' => 'setting'], 'import_settings');

    do_action( 'after_submit_import_settings', $record_id );
}
add_action( 'rwmb_import_settings_import_id_after_save_field', 'call_post_import' );

?>
