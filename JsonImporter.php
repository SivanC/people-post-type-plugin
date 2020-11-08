<?php

/**
 * This class is used to import the JSON records from the old website into
 * wordpress posts.
 * 
 * @version 0.2.1
 * @author Sivan Cooperman
 */
class JsonImporter {

    private $json;

    public function __construct( $filename ) {

        $file = fopen( $filename, 'r' );
        $json_string = fread( $file, filesize( $filename ) );
        fclose( $file );
        $this->$json = json_decode( $json_string, $assoc = true );
    }

    public function getJson() {
        return $this->json;
    }

    /**
     * Iterates through a JSON comprised of JSON records scapred from the old
     * website, and posts them as Person-type wordpress posts.
     */
    function import_posts() {
        // Puts the JSON in an associative array
        $data = $this->getJson()['family'];

        foreach ( $data['person'] as $personKey => $person ) {
            $this->import_post( $personKey );            
        }
    }

    function import_post( $index ) {
        // Puts the JSON in an associative array
        $data = $this->getJson()['family'];

        // Get person by index
        $person = $data[$index];

        // Basic outline of all the fields needed for the post
        $meta_input = array(
            'post_title' => $person['name'],

            'person_parent_group' => array(
                array(
                    'person_parent_name' => "",

                    'person_parent_type' => "",
                ),
            ),

            'person_child_group' => array(
                array(
                    'person_child_name' => "",

                    'person_child_type' => "",

                    'person_child_ordered' => "On",

                    'person_child_birth_order' => -1,
                )
            ),

            'person_partner_group' => array(
                array(
                    'person_partner_name' => "",

                    'person_partner_type' => "",
                    
                    'person_partner_start_date' => "",

                    'person_partner_end_date' => "",

                    'person_partner_ordered' => "On",

                    'person_partner_order' => -1,
                ),
            ),

            'person_current_address' => array(
                "",
            ),

            'person_email' => array(
                "",
            ),

            'person_phone_number' => array(
                "",
            ),

            'person_notes' => "",
        );

        foreach ( $person['sections'] as $sectionKey => $section ) {
            $partnerExp = '/.*Spouse.*/i';
            $childExp = '/.*(Adopt|Foster)*.*Child.*/i';
            $parentExp = '/.*(Adopt|Foster)*.*Parent.*/i';
            $sectionName = $section['section'];
            switch ( $sectionName ) {
                case preg_match( $parentExp, $sectionName ) ? $sectionName : !$sectionName:
                    // Sets the relationship type to foster, adopt, or bio
                    $parentType = strpos( $sectionName, "Adopt") ? 'adopt' : ( strpos( $sectionName, "Foster" ) ? 'foster' : 'bio' );
                    foreach ( $section['content'] as $parentKey => $parent ) {
                        // Sometimes there are blank parents on the records,
                        // so they get skipped
                        if ( $parent['title'] == "" ) {
                            continue;
                        }
                        // Sometimes there are notes in these sections, seven
                        // words is my arbitrary cutoff. By no means foolproof.
                        else if ( substr_count( $parent['name'], ' ') >= 7 ) {
                            $meta_input['person_notes'] .= $parent['name'] . "\n";
                        } else {
                            // Getting rid of the placeholder array
                            if ( $meta_input['person_parent_group'][0]['person_parent_name'] == "" ) {
                                array_pop( $meta_input['person_parent_group'] );
                            }
                            $meta_input['person_parent_group'][] = array(
                                'person_parent_name' => map_names()[$parent['path']],

                                'person_parent_type' => $parentType,
                            );
                        }
                    } break;
                case preg_match( $childExp, $sectionName ) ? $sectionName : !$sectionName:
                    $childType = strpos( $sectionName, "Adopt") ? 'adopt' : ( strpos( $sectionName, "Foster" ) ? 'foster' : 'bio' );
                    foreach ( $section['content'] as $childKey => $child ) {
                        if ( $child['title'] == "" ) {
                            continue;
                        } else if ( substr_count( $child['name'], ' ') >= 7 ) {
                            $post['person_notes'] .= $child['name'] . "\n";
                        } else {
                            // Getting rid of the placeholder array
                            if ( $meta_input['person_child_group'][0]['person_child_name'] == "" ) {
                                array_pop( $meta_input['person_child_group'] );
                            }
                            $meta_input['person_child_group'][] = array(
                                'person_child_name' => map_names()[$child['path']],

                                'person_child_type' => $childType,

                                'person_child_ordered' => "On",

                                'person_child_birth_order' => $childKey,
                            );
                        }
                    } break;
                case preg_match( $partnerExp, $sectionName ) ? $sectionName : !$sectionName:
                    foreach ( $section['content'] as $partnerKey => $partner ) {
                        if ( $partner['title'] == "" ) {
                            continue;
                        } else if ( substr_count( $partner['name'], ' ') >= 7 ) {
                            $post['person_notes'] .= $partner['name'] . "\n";
                        } else {
                            // Getting rid of the placeholder array
                            if ( $meta_input['person_partner_group'][0]['person_partner_name'] == "" ) {
                                array_pop( $meta_input['person_partner_group'] );
                            }
                            $meta_input['person_partner_group'][] = array(
                                'person_partner_name' => map_names()[$partner['path']],

                                'person_partner_type' => 'married',

                                'person_partner_start_date' => "",

                                'person_partner_end_date' => "",

                                'person_partner_ordered' => "On",

                                'person_partner_order' => $partnerKey,
                            );
                        }
                    } break;
                case "History":
                case "Details":
                    foreach ( $section['content'] as $detailKey => $detail ) {
                        // Grab title and path if they exist otherwise start adding details
                        if ( $detail['title'] != "" ) {
                            $meta_input['notes'][] = $detail['title'];
                        } if ( $detail['path'] != "" ) {
                            $meta_input['notes'][] = $detail['path'];
                        }
                        $detail = $detail['details'];
                        // birth date/place, hebrew/married name are present for every record, but not always filled out
                        if ( in_array( $detail['detail'], array( "birth date", "birth place", "hebrew name", "married name" ) ) ) {
                            if ( $detail['detail'] != "" ) {
                                $meta_input['details'][$detail['detail']] = $detail['detail_content'];
                            }
                        } else {
                            $meta_input['notes'][] = $detail['detail_content'];
                        }
                    }
                    break;
                case "Contact":
                    $emailExp = '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i';
                    $phoneExp = '/\b\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})\b/';
                    foreach ( $section['content'] as $contactKey => $contact ) {
                        if ( preg_match( $emailExp, $contact, $emailMatches) ) {
                            if ( $meta_input['person_email'][0] == "" ) {
                                array_pop( $post['person_email'] );
                            } else {
                                foreach ( $emailMatches as $emailMatchIndex => $emailMatch ) {
                                    // First element of the $matches array is the whole string
                                    if ( $emailMatchIndex != 0 ) {
                                        $meta_input['person_email'][] = $emailMatch;
                                    }
                                }
                            }
                        } if ( preg_match( $phoneExp, $contact, $phoneMatches ) ) {
                            if ( $meta_input['person_phone_number'][0] == "" ) {
                                array_pop( $meta_input['person_phone_number'] );
                            } else {
                                foreach ( $phoneMatches as $phoneMatchIndex => $phoneMatch ) {
                                    if ( $phoneMatchIndex != 0 ) {
                                        $meta_input['person_phone_number'][] = $phoneMatches;
                                    }
                                }
                            }
                        }
                    }
                    break;
                default:
                    make_post($sectionName);
            }
        }

        $postarr = array(
            'post_title' => $person['title'],

            'post_content' => '',

            'post_status' => 'publish',

            'post_type' => 'person',

            'meta_input' => $meta_input
        );

        $error = wp_insert_post( $postarr, true );
    }

    /**
     * Generates an array of key value pairs containing a record's path (from the
     * old website) and full name (as shown on its own old page).
     */
    function map_names() {
        $data = $this->json['family'];

        $names = array();
        foreach ( $data['person'] as $person ) {
            $names[$person['path']] = $person['name'];
        }

        return $names;
    }
}

?>