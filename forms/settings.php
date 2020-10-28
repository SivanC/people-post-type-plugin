<?php

function person_register_settings_pages( $settings_pages ) {
    $settings_pages[] = [

        'id' => 'person_settings',

        'menu_title' => "People Settings",

        'style' => 'no-boxes',

        'columns' => 2,

        'submit_button' => "Save Changes",

    ];
    return $settings_pages;
}
add_filter( 'mb_settings_pages', 'person_register_settings_pages' );

function person_register_settings_meta_boxes( $meta_boxes ) {

    $prefix = 'person_settings_';

    $meta_boxes[] = [

        'id' => 'settings',

        'settings_pages' => 'person_settings',

        'fields' => [

            [

                'id' => $prefix . 'name',

                'type' => 'text',

                'name' => "Name",

            ],

        ],

    ];
    return $meta_boxes;
}
add_filter( 'rwmb_meta_boxes', 'person_register_settings_meta_boxes' );

?>