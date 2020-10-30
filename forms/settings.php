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

                'id' => $prefix . 'post_uri',

                'type' => 'text',

                'name' => "Database POST URI",

                'label_description' => "POST requests to the graph database will be sent to this URI",

            ],

            [

                'id' => $prefix . 'get_uri',

                'type' => 'text',

                'name' => "Database GET URI",

                'label_description' => "GET requests to the graph database will be sent to this URI",

            ],

            [

                'id' => $prefix . 'statement_iri',

                'type' => 'text',

                'name' => "Statement IRI",

                'label_description' => "Determines the IRI used to refer to records in the graph database, like http://example.org/people/",

            ],

        ],

    ];
    return $meta_boxes;
}
add_filter( 'rwmb_meta_boxes', 'person_register_settings_meta_boxes' );

?>