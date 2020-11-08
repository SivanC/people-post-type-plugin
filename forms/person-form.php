<?php

// Remove post title and content editors from People post type
add_action( 'init', function() {
    remove_post_type_support('person', 'editor');
    remove_post_type_support('person', 'title');
});

/**
 * Creates and returns custom meta boxes used to post a custom People-type post
 * using the metabox.io plugin. Also uses Metabox groups and tabs.
 * 
 * @param meta_boxes An empty array? Not quite sure. Refer to metabox.io
 * reference documentation.
 * 
 * @return meta_boxes An array containing all the settings/values that describe
 * the People post submissionf form.
 */
function person_register_meta_boxes( $meta_boxes ) {

    $prefix = 'person_';

    global $wpdb;

    // Grabs the post title to populate the title field
    $post_id = !empty( $_GET['post'] ) ? $_GET['post'] : "false";
    $post_title = get_post_field( 'post_title', $post_id );

    $meta_boxes[] = [

        'title'      => esc_html__( 'Person Meta Box', 'online-generator' ),

        'id'         => $prefix . 'meta_box',

        'post_types' => ['person'],

        'context'    => 'normal',
        
        'style'      => 'seamless',

        'priority'   => 'high',

        'fields'     => [

            [

                'id' => 'basic_info_header',

                'type' => 'custom_html',

                'std' => '<h1>Basic Information</h1>',

                'tab' => 'basic_info'

            ],

            [

                'type' => 'text',
                // This is really important
                'id'   => 'post_title',

                'name' => esc_html__( 'Full Name', 'online-generator' ),
                // Default value set to post title
                'std'  => $post_title,

                'tab' => 'basic_info'
            ],


            [

                'type' => 'text',

                'id'   => $prefix . 'other_names',

                'name' => esc_html__( 'Additional First, Middle Names', 'online-generator' ),

                'tab' => 'basic_info'

            ],

            [

                'type' => 'text',

                'id'   => $prefix . 'other_surnames',

                'name' => esc_html__( 'Additional Family Names', 'online-generator' ),

                'tab' => 'basic_info'

            ],

            [

                'type' => 'divider',

                'tab' => 'basic_info'

            ],

            [

                'type' => 'text',

                'id' => $prefix . 'gender',

                'name' => "Gender",

                'datalist' => [

                    'options' => [

                        'male' => "Male",

                        'female' => "Female",

                        'nb' => "Nonbinary",
                    
                    ],

                ],

                'tab' => 'basic_info'

            ],

            [

                'type' => 'divider',

                'tab' => 'basic_info'

            ],

            [

                'type' => 'group',

                'id' => $prefix . 'birth_place_group',

                'group_title' => "Birth Information",

                'collapsible' => true,

                'tab' => 'basic_info',

                'fields' => [

                    [

                        'type' => 'text',
        
                        'id'   => $prefix . 'birth_date',
        
                        'name' => esc_html__( 'Birth Date', 'online-generator' ),
        
                    ],
        
                    [
        
                        'type' => 'text',
        
                        'id'   => $prefix . 'birth_place',
        
                        'name' => esc_html__( 'Birth Place', 'online-generator' ),
        
                    ],
        
                    [
        
                        'type' => 'osm',
        
                        'id' => $prefix . 'birth_place_map',
        
                        'std' => '47.613060,-122.284320',
        
                        'address_field' => $prefix . 'birth_place',
                
                    ],

                ]

            ],

            [

                'type' => 'divider',

                'tab' => 'basic_info'

            ],

            [

                'type' => 'group',

                'id' => $prefix . 'death_place_group',

                'group_title' => "Death Information",

                'collapsible' => true,

                'tab' => 'basic_info',

                'fields' => [

                    [

                        'type' => 'text',
        
                        'id'   => $prefix . 'death_date',
        
                        'name' => esc_html__( 'Death Date', 'online-generator' ),
        
                    ],
        
                    [
        
                        'type' => 'text',
        
                        'id'   => $prefix . 'death_place',
        
                        'name' => esc_html__( 'Death Place', 'online-generator' ),
        
                    ],
        
                    [
        
                        'type' => 'osm',
        
                        'id' => $prefix . 'death_place_map',
        
                        'std' => '47.613060,-122.284320',
        
                        'address_field' => $prefix . 'death_place',
                
                    ],

                ]

            ],

            [

                'id' => 'rel_header',

                'type' => 'custom_html',

                'std' => '<h1>Relationships</h1>',

                'tab' => 'rel'

            ],

            [

                'type' => 'group',

                'id' => $prefix . 'parent_group',

                'group_title' => "Parent {#}",

                'collapsible' => true,

                'clone' => true,

                'sort_clone' => true,

                'add_button' => "Add Parent",

                'tab' => 'rel',

                'fields' => [

                    [

                        'type'  => 'text',
        
                        'id'    => $prefix . 'parent_name',
                        // Grabs all names
                        'datalist' => [
                        
                            'options' => massage_results( $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s;", 'post_title' ), ARRAY_A ) ),
        
                        ],
        
                        'name'  => esc_html__( 'Parent Name', 'online-generator' ),
                
                    ],
        
                    [   
                        'type' => 'select',
        
                        'id' => $prefix . 'parent_type',
        
                        'name' => 'Parent Type',
        
                        'options' => [

                            'none' => "",
        
                            'bio' => 'Biological Parent',
        
                            'adopt' => 'Adoptive Parent',
        
                            'foster' => 'Foster Parent'
                        ],
                
                    ],
                ]

            ],

            [

                'type' => 'divider',

                'tab' => 'rel'

            ],

            [

                'type' => 'group',

                'id' => $prefix . 'child_group',

                'group_title' => "Child {#}",

                'collapsible' => true,

                'clone' => true,

                'sort_clone' => true,

                'add_button' => "Add Child",

                'tab' => 'rel',

                'fields' => [

                    [

                        'type'  => 'text',
        
                        'id'    => $prefix . 'child_name',
        
                        'name'  => esc_html__( 'Child Name', 'online-generator' ),
        
                        'datalist' => [
                        
                            'options' => massage_results( $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s;", 'post_title' ), ARRAY_A ) ),
        
                        ],
        
                    ],

                    [

                        'type' => 'select',

                        'id' => $prefix . 'child_type',

                        'name' => "Child Type",

                        'options' => [

                            'none' => "",

                            'bio' => "Biological Child",

                            'adopt' => "Adopted Child",

                            'foster' => "Foster Child"

                        ]

                    ],

                    [

                        'name' => 'Ordered',

                        'id' => $prefix . 'child_ordered',

                        'type' => 'switch',

                        'std' => "On",

                    ],

                    [

                        'type' => 'hidden',
        
                        'id' => $prefix . 'child_birth_order',
                
                        'std' => -1,
                        
                    ],

                ]

            ],

            [

                'type' => 'divider',

                'tab' => 'rel'

            ],

            [

                'type' => 'group',

                'id' => $prefix . 'partner_group',

                'group_title' => "Partner {#}",

                'collapsible' => true,

                'clone' => true,

                'sort_clone' => true,

                'add_button' => "Add Partner",

                'tab' => 'rel',

                'fields' => [

                    [

                        'type'  => 'text',
        
                        'id'    => $prefix . 'partner_name',
        
                        'name'  => esc_html__( 'Partner Name', 'online-generator' ),
        
                        'datalist' => [
                        
                            'options' => massage_results( $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s;", 'post_title' ), ARRAY_A ) ),
        
                        ],
                
                    ],

                    [

                        'type' => 'select',

                        'id' => $prefix . 'partner_type',

                        'name' => "Partnership",

                        'options' => [

                            'none' => "",

                            'married' => "Married",

                            'partnered' => "Partnered",

                            'civil' => "Civil Partnership"

                        ]
                        
                    ],
        
                    [
        
                        'type' => 'text',
        
                        'id' => $prefix . 'partner_start_date',
        
                        'name' => "Relationship Start Date",
                
                    ],
        
                    [
        
                        'type' => 'date',
        
                        'id' => $prefix . 'partner_end_date',
        
                        'name' => "Relationship End Date",
                
                    ],

                    [

                        'type' => 'hidden',

                        'id' => $prefix . 'partner_order',

                        'std' => -1,

                    ],
                    
                ]

            ],

            [

                'id' => 'loc_header',

                'type' => 'custom_html',

                'std' => '<h1>Locations</h1>',

                'tab' => 'loc'

            ],

            [

                'type' => 'group',

                'id' => $prefix . 'location_group',

                'group_title' => "Location {#}",

                'collapsible' => true,

                'clone' => true,

                'sort_clone' => true,

                'add_button' => "Add Location",

                'tab' => 'loc',

                'fields' => [

                    [
        
                        'type' => 'text',
        
                        'id'   => $prefix . 'location',
        
                        'name' => esc_html__( 'Location', 'online-generator' ),
        
                    ],
        
                    [
        
                        'type' => 'osm',
        
                        'id' => $prefix . 'location_map',
        
                        'std' => '47.613060,-122.284320',

                        'language' => 'en',
        
                        'address_field' => $prefix . 'location',
                
                    ],

                    [
        
                        'type' => 'date',
        
                        'id' => $prefix . 'location_start_date',
        
                        'name' => "Location Start Date",
        
                        'js_options' => [
        
                            'dateFormat' => 'mm-dd-yy',
        
                            'changeYear' => true,
                            // Allowed years for selection are 1800 to current year
                            'yearRange' => '1800:'
        
                        ],
                
                    ],
        
                    [
        
                        'type' => 'date',
        
                        'id' => $prefix . 'location_end_date',
        
                        'name' => "Location End Date",
        
                        'js_options' => [
        
                            'dateFormat' => 'mm-dd-yy',
        
                            'changeYear' => true,
        
                            'yearRange' => '1800:'
        
                        ],
                
                    ],

                ]

            ],

            [

                'id' => 'contact_header',

                'type' => 'custom_html',

                'std' => '<h1>Contact</h1>',

                'tab' => 'contact'

            ],

            [

                'type' => 'divider',

                'tab' => 'contact'

            ],

            [

                'type' => 'text',

                'id'   => $prefix . 'current_address',

                'name' => esc_html__( 'Current Physical Address', 'online-generator' ),

                'tab' => 'contact'

            ],

            [

                'type' => 'divider',

                'tab' => 'contact'

            ],

            [

                'type' => 'email',

                'id'   => $prefix . 'email',

                'name' => esc_html__( 'Email', 'online-generator' ),

                'clone' => 'true',

                'add_button' => "Add Email",

                'tab' => 'contact'

            ],

            [

                'type' => 'divider',

                'tab' => 'contact'

            ],

            [

                'type'  => 'text',

                'id'    => $prefix . 'phone_number',

                'name'  => esc_html__( 'Phone Number', 'online-generator' ),

                'clone' => true,

                'add_button' => "Add Phone Number",

                'tab' => 'contact'

            ],

            [

                'id' => 'notes_header',

                'type' => 'custom_html',

                'std' => '<h1>Notes</h1>',

                'tab' => 'notes'

            ],

            [

                'type' => 'textarea',

                'id' => $prefix . 'notes',

                'name' => "Notes",

                'rows' => 10,

                'tab' => 'notes'

            ],

            [

                'type' => 'textarea',

                'id' => $prefix . 'hidden',

                'name' => "Hidden (only visible to admin)",

                'rows' => 10,

                'tab' => 'notes'

            ],

            [
                'type' => 'hidden',

                'id' => $prefix . 'id',
                // placeholder value to be modified by a filter below
                'std' => '-1'
            ],
            // For records scraped from the old site which have the original html
            [

                'type' => 'hidden',

                'id' => $prefix . 'original_html',

                'std' => "",

            ],

        ],

        'tabs' => [

            'basic_info' => "Basic Information",

            'rel' => "Relationships",
            
            'loc' => "Locations",

            'contact' => "Contact",

            'notes' => "Notes"

        ],

        'validation' => [

            'rules' => [

                'post_title' => [

                    'remote' => [
                        
                        'url' => plugins_url() . "/people-post-type/validation-controller.php",

                        'data' => [

                            'id' => $post_id,
                            // Pass in path so that validation controller can require wp-load.php for wpdb access
                            'path' => get_home_path()

                        ],

                    ],

                    'required' => true

                ],

                'parent_group' => [

                    'remote' => [
                        
                        'url' => plugins_url() . "/people-post-type/validation-controller.php",

                        'data' => [
    
                            'path' => get_home_path()
    
                        ],
        
                    ],

                ],

                'child_group' => [

                    'remote' => [
                        
                        'url' => plugins_url() . "/people-post-type/validation-controller.php",

                        'data' => [
    
                            'path' => get_home_path()

                        ],

                    ],
    
                ],

                'partner_group' => [

                    'remote' => [
                        
                        'url' => plugins_url() . "/people-post-type/validation-controller.php",

                        'data' => [
    
                            'path' => get_home_path()

                        ],

                    ],
    
                ],

            ],

            'messages' => [

                'post_title' => [

                    'remote' => 'The display name must be unique.'

                ]

            ]

        ]

    ];
    return $meta_boxes;
}
add_filter( 'rwmb_meta_boxes', 'person_register_meta_boxes' );

// Prevents the default post title from being set to 'Auto Draft',
// necessary due to this field replacing the usual post_title field.
add_filter( 'rwmb_post_title_field_meta', function() {
    $post_id = get_the_ID();
    $post_title = get_post_field( 'post_title', $post_id ) == "Auto Draft" ? "" : get_post_field( 'post_title', $post_id );
    return $post_title;
});

// Returns the ID of the current post to the database upon submission.
add_filter( 'rwmb_person_id_value', function() {
    return get_the_ID();
});

?>
