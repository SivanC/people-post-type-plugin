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

    public function __construct( $filename, $extras_filename ) {
        $json_string = file_get_contents( $filename );
        $this->json = json_decode( $json_string, $assoc = true );

        $extra_json_string = file_get_contents( $extras_filename );
        $this->extras = json_decode( $extra_json_string, $assoc = true );

        $this->extra_names = array();
        $data = $this->getExtras()['family'];
        foreach ( $data as $recordId => $record ) {
            $this->extra_names[] = $record['person']['name'];
        }
    }

    public static function init() {
        $importer = new self( __DIR__ . '/../data/data.json', __DIR__ . '/../data/extra.json' );
        add_action( 'after_submit_import_settings', [$importer, 'import_handler'], 10, 1 );
    }

    public function getJson() {
        return $this->json;
    }

    public function getExtras() {
        return $this->extras;
    }

    public function import_handler( $index ) {
        if (filter_var( $index, FILTER_VALIDATE_INT ) !== FALSE ) {
            DataIO::console_log("Index is a digit");
            if ( intval( $index ) == -1 ) {
                DataIO::console_log("-1 detected, importing all posts...");
                $this->import_posts();
            }
        } else {
            DataIO::console_log("Importing post with index: " . $index );
            $this->import_post( $index );
        }
    }

    /**
     * Iterates through a JSON comprised of JSON records scapred from the old
     * website, and posts them as Person-type wordpress posts.
     */
    function import_posts() {
        // Puts the JSON in an associative array
        $data = $this->getJson()['family'];

        // foreach ( $data['person'] as $personKey => $person ) {
        //     $this->import_post( $personKey );            
        // }

        for ( $i = 0; $i < 10; $i++ ) {
            $this->import_post( $i );
        }
    }

    function import_post( $index ) {
        DataIO::console_log("Request received for index " . $index );
        // Working with such a large file we need to increase the memory
        // capacity for the script.
        ini_set( "memory_limit", "16M" );
        // Puts the JSON in an associative array
        $data = $this->getJson()['family'];
        $extras = $this->getExtras()['family'];

        // Get person by either name or index in the JSON
        if ( filter_var( $index, FILTER_VALIDATE_INT ) !== FALSE ) {
            $person = $data[$index]['person'];
        } else {
            foreach ( $data as $recordIndex => $record ) {
                if ( $record['person']['name'] == $index ) {
                    $person = $record['person'];
                }
            }
        }

        // checks if the person exists in the extra data file, returns the 
        // index of the entry or false if not found
        $extra_exists = array_search( $person['name'], $this->extra_names  );

        // Basic outline of all the fields needed for the post
        $meta_input = array(
            'post_title' => $person['name'],

            'person_other_names' => "",

            'person_other_surnames' => "",

            'person_birth_group' => array(
                'person_birth_date' => "",

                'person_birth_place' => "",
            ),

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

            'person_original_html' => $person['original'],
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
                            $meta_input['person_notes'] .= $child['title'] . "\n";
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
                            $meta_input['person_notes'] .= $partner['titles'] . "\n";
                        } else {
                            // Getting rid of the placeholder array
                            if ( $meta_input['person_partner_group'][0]['person_partner_name'] == "" ) {
                                array_pop( $meta_input['person_partner_group'] );
                            }
                            $start_date = "";
                            if ( $extra_exists ) {
                                // Gross, I know, not my fault
                                if ( $extras[$extra_exists]['person']['sections'][0]['content'][0]['title'] == $partner['title'] ) {
                                    $start_date = $extras[$extra_exists]['person']['sections'][0]['content'][0]['extra'];
                                }
                            }
                            $meta_input['person_partner_group'][] = array(
                                'person_partner_name' => empty( $partner['path'] ) ? $partner['title'] : $this->map_name( $partner['path'] ),

                                'person_partner_type' => 'married',

                                'person_partner_start_date' => $start_date == "" ? "FUCK YOU" : $start_date,

                                'person_partner_end_date' => "",

                                'person_partner_ordered' => "On",

                                'person_partner_order' => $partnerKey,
                            );
                        }
                    } break;
                case "History":
                case "Details":
                    /*
                    Details have a very confusing architecture: they are 
                    comprised of a title, detail array, and path, and within
                    the detail array is a detail title and detail content,
                    */
                    $content = $section['content'];
                    foreach ( $content as $detailKey => $detail ) {
                        // Grab title and path if they exist otherwise start adding details
                        if ( $detail['title'] != "" ) {
                            $meta_input['person_notes'] .= $detail['title'] . "\n";
                        } if ( $detail['path'] != "" ) {
                            $meta_input['person_notes'] .= $detail['path'] . "\n";
                        }
                        $details = $detail['details'];

                        // The history section has a details array but it is 
                        // empty, so this code will not run for it
                        foreach ( $details as $dKey => $d ) {
                            // birthdate/place, hebrew/married name are present
                            // for every record, but not always filled out
                            switch( $d['detail'] ) {
                                case "birth date":
                                    $meta_input['person_birth_group']['person_birth_date'] = $d['detail_content'];
                                    break;
                                case "birth place":
                                    $meta_input['person_birth_group']['person_birth_place'] = $d['detail_content'];
                                    break;
                                case "hebrew name":
                                    $meta_input['person_other_names'] = $d['detail_content'];
                                    break;
                                case "married name":
                                    $meta_input['person_other_surnames'] = $d['detail_content'];
                                    break;
                                case "deceased":
                                    $meta_input['person_death_group']['person_death_date'] = $d['detail_content'];
                                    break;
                                default:
                                    $meta_input['person_notes'] .= $d['detail_content'] . "\n";
                            }
                        }
                    }
                    break;
                case "Contact":
                    $content = $section['content'];
                    foreach ( $content as $contactKey => $contact ) {
                        $meta_input['person_notes'] .= $contact['title'] . "\n";
                    }
                    break;
                case "Siblings":
                    // Since we don't keep sibling relationships, any siblings without parents need to 
                    if ( !empty( $meta_input['person_parent_group'][0]['person_parent_name'] ) ) {

                    }
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
     * Just the unique part is needed (_____.___/_____/doe/johndoe for example)
     * 
     * @return String returns the path if nothing is found, otherwise the full
     * name of the corresponding record.
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

        return $path;

    }
}

?>