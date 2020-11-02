<?php

/**
 * Imports the JSON data that was scraped from the previous website.
 */
function import_posts( $json ) {
    // Puts the JSON in an associative array
    $data = json_decode( $json, $assoc = true )['family'];

    foreach ( $data as $personKey => $person ) {
        $post_title = $person['name'];

        $post = array(
            'parents' => array(),
            'children' => array(),
            'partners' => array(),
            'notes' => array(),
            'contact' => array(),
        );

        foreach ( $person['section'] as $sectionKey => $section ) {
            switch ( $section['section'] ) {
                case "Parents":
                    foreach ( $section['content'] as $parentKey => $parent ) {
                        // Set name to index if the title is empty ("Parent 1", "Parent 2")
                        $parent_name = $parent['title'] == "" ? "Parent " . strval( $parentKey ) : $parent_title;

                        foreach ( $parent['details'] as $detailKey => $detail ) {
                            foreach ( $detail as $detailFieldKey => $detailField ) {
                                
                            }
                        }
                    }
            }
        }
    }
}

?>