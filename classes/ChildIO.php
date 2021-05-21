<?php

/**
 * This class handes IO of child data between the wordpress meta box form and the
 * GraphDB database.
 */
class ChildIO {

    /**
     * This function uses the rwmb_person_child_group_value filter to intercept
     * child data sent by the People post and write it to the GraphDB database.
     * As the data (child name and exact relationship to parent (biological, adopted,
     * or foster child), as well as birth order) is most suited to a graph 
     * database, and not the default wp_postmeta table, it is intercepted on 
     * submission and written there.
     * 
     * @param children The data, in the form of a two-dimensional array.
     * The $children array corresponds to the entirety of the "child" group, and
     * each subarray contains key-value ("person_child_name", "person_child_type",
     * and "birth_order" are the keys) pairs corresponding to a single child.
     */
    public static function write_child_data( $children ) {
        // Getting settings
        DataIO::console_log("Getting settings... (child)");
        $settings = DataIO::get_settings();
        $post_uri = $settings['person_settings_post_uri'];
        $get_uri = $settings['person_settings_get_uri'];
        $statement_iri = $settings['person_settings_statement_iri'];
        substr( $statement_iri, -1 ) == "/" ? $statement_iri : $statement_iri . "/"; // Ensuring there's a slash at the end of the iri

        DataIO::console_log(printf("Example statement predicate: <%s%d>", $statement_iri, 11));

        // By definition, the post posting the data is the child of the children
        // being posted.
        $parent_id = get_the_ID();

        /*  As $children is only a record of what *should* be in the databse, and
            not what shouldn't (for example, if a parent-child relationship was 
            written and then removed on the front end, write_child_data() would
            not otherwise pick up on the absence of a relationship from 
            $children), the id of every child relationship is collected as we
            iterate through $children, and then ones that are found only in the
            database are removed. */
        $child_id_array = array();

        DataIO::console_log("Adding published children to db...");
        foreach ( $children as $index => $child ) {
            /* There must always be at least one child group on the front end,
               so the only way to delete all children is by removing all the 
               others and leaving the first one blank. */
            if ( count( $children ) == 1 && $child["person_child_name"] == "" && $parent["person_child_type"] == "none" ) {
                DataIO::console_log("No children detected, removing all child relationships to parent...");
                // This query deletes all child relationships to the parent
                $query = sprintf("
                PREFIX coop: <http://cooperman.org/terms/>;
                    DELETE WHERE { << <%s%d> ?rel ?child >> coop:birth ?order . }; 
                    DELETE WHERE { <%s%d> ?rel ?child . }",
                $statement_iri, $parent_id,
                $statement_iri, $parent_id );

                DataIO::post_data( $query, $post_uri );
                continue;
            } 
            
            if ( $child["person_child_name"] == "" || $child["person_child_type"] == "none" ) {
                DataIO::console_log("Empty child found");
                continue;
            }

            // get_id() retrieves the post_id value from wp_postmeta using the name of the child.
            // returns -1 if the child is not found.
            $child_id = intval( get_id( $child['person_child_name'] ) );
            // One of: none (default value), bio, adopt, foster
            $rel = $child['person_child_type'];

            // Each child listed can be ordered or unordered. Since birth order
            // is assigned only to ordered children, by index, we subtract the
            // number of unordered children encountered to get the real number.
            $num_unordered_children = 0;
            if ( !( $child['person_child_ordered'] == 1 ) ) {
                DataIO::console_log("Unrdered child detected...");
                $num_unordered_children++;
                $birth_order = -1;
            } else {
                // Get birth order based on ordering of children in the parent post front end
                $birth_order = $index - $num_unordered_children;
                DataIO::console_log("Ordered child detected");
            }

            if ( $child_id != -1 ) {
                /*  $query is a SPARQL query that is sent to the GraphDB database
                    Deletes all previous relationships between the child and parent and
                    inserts the new relationship (as bio, adopt, or 
                    foster) using the child and parent's IDs (same as their post IDs).
                    Note this assumes the child is in the database, which is validated
                    via the above conditional. Also adds the child's birth order using
                    SPARQL* notation to denote the birth order specific to that parent-
                    child coupling.
                */
                $query = sprintf( 
                    "PREFIX coop: <http://cooperman.org/terms/>; 
                    DELETE WHERE { <%s%d> ?rel <%s%d> };
                    INSERT DATA { <%s%d coop:%s <%s%d> }",
                $statement_iri, $parent_id, $statement_iri, $child_id,
                $statement_iri, $parent_id, $rel, $statement_iri, $child_id );

                DataIO::console_log("Child found, creating query to update db...");

            } else {
                // The format for 'person_child_group' within $meta_input is 
                // similar to the 'person_parent_group' format expounded upon
                // in the write_parent_data() doc
                $meta_input = array(
                    'post_title' => $child['person_child_name'],

                    'person_parent_group' => array(
                        array(
                            'person_parent_name' => rwmb_meta( 'post_title' ),

                            'person_parent_type' => $rel,
                        )
                    )
                );

                $postarr = array(
                    'post_title' => $child['person_child_name'],

                    'post_content' => '',

                    'post_status' => 'publish',

                    'post_type' => 'person',

                    'meta_input' => $meta_input
                );

                DataIO::console_log("Posting child to database...");

                // If the act of inserting the post generates any errors they are
                // saved here
                $error = wp_insert_post( $postarr, true );

                DataIO::console_log("Child posted with possible error: $error");

                $child_id = intval( get_id( $child['person_child_name'] ) );
                
                // This query does not worry about deleting existing data because
                // the child is assumed to be absent from both databases.
                $query = sprintf( "PREFIX coop: <http://cooperman.org/terms/>; 
                                INSERT DATA { 
                                    <%s%d> coop:%s <%s%d> .
                                    << <%s%d> coop:%s <%s%d> >> coop:birthOrder %d
                                }",
                    $statement_iri, $parent_id, $rel, $statement_iri, $child_id,
                    $statement_iri, $parent_id, $rel, $statement_iri, $child_id, $birth_order );
            }

            array_push( $child_id_array, $child_id );
            DataIO::post_data( $query, $post_uri );
        }

        // Last element excluded from for loop to avoid adding extra comma
        // See $child_id_array definition above for why the steps below are taken
        $child_uris = "";
        for ( $i = 0; $i < ( count( $child_id_array ) - 1 ); $i++ ) {
            $id = "<" . $statement_iri . strval($child_id_array[$i]) . ">";
            $child_uris .= $id . ", ";
        }
        $child_uris .= "<" . $statement_iri . strval( array_pop( $child_id_array ) ) . ">";

        DataIO::console_log("Updating db to remove orphaned children (not like that!)...");

        // This query deletes all children who are not in $child_uris
        $query = sprintf( "
        PREFIX coop: <http://cooperman.org/terms/>;
        DELETE { ?parent ?relationship ?child . }
        WHERE { 
            ?parent ?relationship ?child .
            <%s%d> ?relationship  ?child .
            FILTER ( ?child NOT IN ( %s )  )
        }", $statement_iri, $parent_id, 
        $child_uris );

        DataIO::post_data( $query, $post_uri );

        // Returns an empty string to wp_postmeta, disabling saving
        return __return_empty_string();
    }

    /**
     * This function uses the rwmb_person_child_group_meta filter to retrieve
     * parent data from the GraphDB database and display it in the People form for
     * a given post. As the data (child name and exact relationship to parent 
     * (biological, adopted, or foster parent), or birth order) is most suited 
     * to a graph database, and not the default wp_postmeta table, it is 
     * intercepted on submission and written to there.
     */
    public static function read_child_data() {
        // Getting settings
        $settings = DataIO::get_settings();
        $post_uri = $settings['person_settings_post_uri'];
        $get_uri = $settings['person_settings_get_uri'];
        $statement_iri = $settings['person_settings_statement_iri'];
        substr( $statement_iri, -1 ) == "/" ? $statement_iri : $statement_iri . "/"; // Ensuring there's a slash at the end of the iri
        
        global $wpdb;

        $post_id = get_the_id();

        // A SPARQL query that searches for any children of the post in the GraphDB
        // database.
        $child_query = sprintf( 
            "PREFIX coop: <http://cooperman.org/terms/>
            SELECT ?parent ?rel ?child
            WHERE {
                ?parent ?relationship ?child;
                    ?relationship <%s%d> .
                FILTER ( ?relationship IN ( coop:bio, coop:adopt, coop:foster ) )
            }", $statement_iri, $post_id );

        /*  The GET request performed in get_data() returns a table as a string, 
            rows separated by newlines, columns separated by commas:
                val1,val2,val3,val4 
                val5,val6,val7,val8
                val9,val10,val11,val12
        */
        $data = DataIO::get_data( $child_query, $get_uri );
        $child_table = explode( "\n", $data );
        // Last element (empty string) removed
        array_pop( $child_table );
        //make_post(print_r($child_table, true));
        
        $children = array();

        // The first row is always 'parent,relationship,child', so if it is the
        // only row we just return a $children array with an empty parent
        if ( count( $child_table ) == 1 ) {
            array_push( $children, array( "person_child_name" => "",
                                        "person_child_type" => "none",
                                        "person_child_ordered" => 1,
                                        "person_child_birth_order" => -1 ) );
        } else {
            foreach( $child_table as $index => $child ) {
                // skip first row (headers, always comprised of 'parent,relationship,child,order')
                if ( $index == 0 ) {
                    //make_post("Skipped row 0");
                    continue;
                }
                $child = explode( ',', $child );
                // get id from uri (don't go backwards (i.e. substr($child[0], -3))
                // because the number of digits varies in the post id)
                $child_id = substr($child[2], 28);
                // select name from wp_postmeta by post_id ($child_id)
                $child_name_query = $wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id = %d;", "post_title", $child_id);
                $child_name = $wpdb->get_row( $child_name_query, ARRAY_N )[0];

                // $child_id and $child_rel are derived from rows of the following format:
                // <http://cooperman.org/people/123> <http://cooperman.org/terms/bio> <http://cooperman.org/people/456>
                // where 123 is the parent id, bio is the relationship, and 456 is the child id
                $child_rel = substr( $child[1], 27 );

                $birth_order = intval( $child[3] );

                array_push( $children, array("person_child_name" => $child_name,
                                            "person_child_type" => $child_rel,
                                            "person_child_ordered" => $birth_order == -1 ? 0 : 1,
                                            "person_child_birth_order" => $birth_order ) );
            }
        }

        // Sorts the array by birth order
        uasort( $children, function($a, $b) { return ($a["person_child_birth_order"] - $b["person_child_birth_order"] ); } );
        //make_post(print_r($children, true));
        return $children;
    }

}

?>