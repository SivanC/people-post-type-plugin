<?php

/**
 * This class is used to import the JSON records from the old website into
 * wordpress posts.
 * 
 * @version 0.3.1
 * @author Sivan Cooperman
 */
class JsonImporter {

    private $json;

    public function __construct( $filename ) {
        $json_string = file_get_contents( $filename );
        $this->json = json_decode( $json_string, $assoc = true );
    }

    public static function init() {
        $importer = new self( __DIR__ . '/../data/data.json' );
        DataIO::console_log("Constructing...");
        add_action( 'after_submit_import_settings', [$importer, 'import_post'], 10, 1 );
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
        // Working with such a large file we need to increase the memory
        // capacity for the script.
        ini_set( "memory_limit", "16M" );
        // Puts the JSON in an associative array
        $data = $this->getJson()['family'];

        // Get person by index
        $person = $data[$index]['person'];

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
        $sections = $person['sections'];
        foreach ( $sections as $sectionKey => $section ) {
            $partnerExp = '/.*Spouse.*/i';
            // TODO: Don't forget the Haga family who has a
            // "Pre-adoptive Children" section that will trip the filter.
            $childExp = '/.*(Adopt|Foster)*.*Child.*/i';
            $parentExp = '/.*(Adopt|Foster)*.*Parent.*/i';

            $sectionName = $section['section'];
            DataIO::console_log("Section Name: " . $sectionName );
            switch ( $sectionName ) {
                case preg_match( $parentExp, $sectionName ) ? $sectionName : !$sectionName:
                    // Sets the relationship type to foster, adopt, or bio
                    $parentType = strpos( $sectionName, "Adopt") ? 'adopt' : ( strpos( $sectionName, "Foster" ) ? 'foster' : 'bio' );
                    $content = $section['content'];
                    foreach ( $content as $parentKey => $parent ) {
                        // Sometimes there are blank parents on the records,
                        // so they get skipped
                        if ( $parent['title'] == "" ) {
                            continue;
                        }
                        // Sometimes there are notes in these sections, seven
                        // words is my arbitrary cutoff. By no means foolproof.
                        else if ( substr_count( $parent['title'], ' ') >= 7 ) {
                            $meta_input['person_notes'] .= $parent['title'] . "\n";
                        } else {
                            // Getting rid of the placeholder array
                            if ( $meta_input['person_parent_group'][0]['person_parent_name'] == "" ) {
                                array_pop( $meta_input['person_parent_group'] );
                            }
                            $meta_input['person_parent_group'][] = array(
                                'person_parent_name' => empty( $parent['path'] ) ? $parent['title'] : $this->map_name( $parent['path'] ),

                                'person_parent_type' => $parentType,
                            );
                        }
                    } break;
                case preg_match( $childExp, $sectionName ) ? $sectionName : !$sectionName:
                    $childType = strpos( $sectionName, "Adopt") ? 'adopt' : ( strpos( $sectionName, "Foster" ) ? 'foster' : 'bio' );

                    $content = $section['content'];
                    foreach ( $content as $childKey => $child ) {
                        if ( $child['title'] == "" ) {
                            continue;
                        } else if ( substr_count( $child['title'], ' ') >= 7 ) {
                            $post['person_notes'] .= $child['title'] . "\n";
                        } else {
                            // Getting rid of the placeholder array
                            if ( $meta_input['person_child_group'][0]['person_child_name'] == "" ) {
                                array_pop( $meta_input['person_child_group'] );
                            }
                            $meta_input['person_child_group'][] = array(
                                'person_child_name' => empty( $child['path'] ) ? $child['title'] : $this->map_name( $child['path'] ),

                                'person_child_type' => $childType,

                                'person_child_ordered' => "On",

                                'person_child_birth_order' => $childKey,
                            );
                        }
                    } break;
                case preg_match( $partnerExp, $sectionName ) ? $sectionName : !$sectionName:
                    $content = $section['content'];
                    foreach ( $content as $partnerKey => $partner ) {
                        if ( $partner['title'] == "" ) {
                            continue;
                        } else if ( substr_count( $partner['title'], ' ') >= 7 ) {
                            $post['person_notes'] .= $partner['titles'] . "\n";
                        } else {
                            // Getting rid of the placeholder array
                            if ( $meta_input['person_partner_group'][0]['person_partner_name'] == "" ) {
                                array_pop( $meta_input['person_partner_group'] );
                            }
                            $meta_input['person_partner_group'][] = array(
                                'person_partner_name' => empty( $partner['path'] ) ? $partner['title'] : $this->map_name( $partner['path'] ),

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
                    $content = $section['content'];
                    foreach ( $content as $detailKey => $detail ) {
                        // Grab title and path if they exist otherwise start adding details
                        if ( $detail['title'] != "" ) {
                            $meta_input['notes'][] = $detail['title'];
                        } if ( $detail['path'] != "" ) {
                            $meta_input['notes'][] = $detail['path'];
                        }
                        $details = $detail['details'];
                        foreach ( $details as $dKey => $d ) {
                            // birthdate/place, hebrew/married name are present
                            // for every record, but not always filled out
                            if ( in_array( $d['detail'], array( "birth date", "birth place", "hebrew name", "married name" ) ) ) {
                                if ( $d['detail'] != "" ) {
                                    $meta_input['details'][$d['detail']] = $d['detail_content'];
                                }
                            } else {
                                $meta_input['notes'][] = $d['detail_content'];
                            }
                        }
                    }
                    break;
                case "Contact":
                    $emailExp = '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i';
                    $phoneExp = '/\b\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})\b/';
                    $content = $section['content'];
                    foreach ( $content as $contactKey => $contact ) {
                        if ( preg_match( $emailExp, $contact['title'], $emailMatches) ) {
                            if ( empty( $meta_input['person_email'][0] ) ) {
                                array_pop( $meta_input['person_email'] );
                            } else {
                                foreach ( $emailMatches as $emailMatchIndex => $emailMatch ) {
                                    // First element of the $matches array is the whole string
                                    if ( $emailMatchIndex != 0 ) {
                                        $meta_input['person_email'][] = $emailMatch;
                                    }
                                }
                            }
                        } if ( preg_match( $phoneExp, $contact['title'], $phoneMatches ) ) {
                            if ( empty( $meta_input['person_phone_number'][0] ) ) {
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
                case "Siblings":
                    break;
                default:
                    // make_post($sectionName);
                    echo("<script>console.log(\"{$sectionName}\")</script>");
            }
        }

        $postarr = array(
            'post_title' => $person['name'],

            'post_content' => '',

            'post_status' => 'publish',

            'post_type' => 'person',

            'meta_input' => $meta_input
        );

        //make_post((print_r($meta_input, true)));

        $error = wp_insert_post( $postarr, true );
    }

    /**
     * Gets the full name of a record via its old-website path, ensuring the 
     * abbreviated name present in other records is not used.
     * 
     * @param String $path a path on the old website corresponding to a record.
     */
    function map_name( $path ) {
        $data = $this->json['family'];

        foreach ( $data as $personKey => $person ) {
            $person = $person['person'];

            // Forward slash at the beginning of the path is inconsistently
            // used, so the regex matches it with or without.
            if ( preg_match( sprintf("/\/{0,1}%s/", preg_quote( $path, "/" ) ), $person['path'] ) ) {
                return $person['name'];
            }
        }

        return "NOT FOUND";

    }
}

?>