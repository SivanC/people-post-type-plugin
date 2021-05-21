<?php

/**
 * This class handes IO of partner data between the wordpress meta box form and the
 * GraphDB database.
 */
class ParentIO {

    /**
     * This function uses the rwmb_person_parent_group_value filter to intercept
     * parent data sent by the People post and write it to the GraphDB database.
     * As the data (parent name and exact relationship to child (biological, adoptive,
     * or foster parent)) is most suited to a graph database, and not the default
     * wp_postmeta table, it is intercepted on submission and written there.
     * 
     * @param parents The data, in the form of a two-dimensional ragged array.
     * The $parents array corresponds to the entirety of the "parent" group, and
     * each subarray contains key-value ("person_parent_name" and "person_parent_type")
     * pairs corresponding to a single parent.
     */
    public static function write_parent_data( $parents ) {
        // Getting settings
        $settings = DataIO::get_settings();
        $post_uri = $settings['person_settings_post_uri'];
        $get_uri = $settings['person_settings_get_uri'];
        $statement_iri = $settings['person_settings_statement_iri'];
        substr( $statement_iri, -1 ) == "/" ? $statement_iri : $statement_iri . "/"; // Ensuring there's a slash at the end of the iri

        // By definition, the post posting the data is the child of the parents
        // being posted.
        $child_id = get_the_ID();

        /*  As $parents is only a record of what *should* be in the databse, and
            not what shouldn't (for example, if a parent-child relationship was 
            written and then removed on the front end, write_parent_data() would
            not otherwise pick up on the absence of a relationship from 
            $parents), the id of every parent relationship is collected as we
            iterate through $parents, and then ones that are found only in the
            database are removed.
        */
        $parent_id_array = array();

        foreach ( $parents as $parent ) {
            /* Metabox requires that there must always be at least one parent group, 
               so the only way to delete all parents is by removing all the others 
               and leaving the first one blank.
            */
            if ( count( $parents ) == 1 && $parent["person_parent_name"] == "" && $parent["person_parent_type"] == "none" ) {
                // This query deletes all parent relationships to the child,
                // both literal (a is the father of b) and implied (from a's perspective b is his first child)
                $query = sprintf(  
                    "PREFIX coop: <http://cooperman.org/terms/>;
                    DELETE WHERE { << ?parent ?rel <%s%d> >> coop:birth ?order . }; 
                    DELETE WHERE { ?parent ?rel <%s%d> . }",
                $statement_iri, $child_id,
                $statement_iri, $child_id );

                DataIO::post_data( $query, $post_uri );
            } 
            
            if ( $parent["person_parent_name"] == "" || $parent["person_parent_type"] == "none" ) {
                continue;
            }

            // get_id() retrieves the post_id value from wp_postmeta using the name of the parent.
            // returns -1 if the parent is not found.
            $parent_id = intval( get_id( $parent['person_parent_name'] ) );
            // One of: none (default value), bio, adopt, foster
            $rel = $parent['person_parent_type'];

            if ( $parent_id != -1 ) {

                // Getting current number of children of the parent
                $num_children = $parent[]

                /*  $query is a SPARQL query that is sent to the GraphDB database

                    Deletes previous literal relationships between the parent and child and
                    inserts the new relationship (as bio, adopt, or foster) using the parent
                    and child's IDs (same as their post IDs).

                    Note this assumes the parent is in the database, which is validated
                    via the above conditional.

                    Does not alter existing birth order statements as the order
                    can only be adjusted from the parent record, but appends the
                    child record to the end of the list.
                */
                $query = sprintf( 
                    "PREFIX coop: <http://cooperman.org/terms/>; 
                    DELETE WHERE { <%s%d> ?rel <%s%d> };
                    INSERT DATA { <%s%d> coop:%s <%s%d> }",
                $statement_iri, $parent_id, $statement_iri, $child_id,
                $statement_iri, $parent_id, $rel, $statement_iri, $child_id );
            } else {
                // The format for 'person_child_group' within $meta_input is 
                // similar to the 'person_parent_group' format expounded upon
                // in the write_parents_data() doc
                $meta_input = array(
                    'post_title' => $parent['person_parent_name'],

                    'person_child_group' => array(
                        array(
                            'person_child_name' => rwmb_meta( 'post_title' ),

                            'person_child_type' => $rel,

                            'person_child_ordered' => 1,

                            'person_child_birth_order' => 0
                        )
                    )
                );

                $postarr = array(
                    'post_title' => $parent['person_parent_name'],

                    'post_content' => '',

                    'post_status' => 'publish',

                    'post_type' => 'person',

                    'meta_input' => $meta_input
                );

                // Saves any posting-related error msgs
                $error = wp_insert_post( $postarr, true );

                $parent_id = intval( get_id( $parent['person_parent_name'] ) );
                
                // This query does not worry about deleting existing data because
                // the parent is assumed to be absent from both databases. Assumes
                // the child is ordered and is the first child of the parent.
                $query = sprintf( 
                    "PREFIX coop: <http://cooperman.org/terms/>; 
                    INSERT DATA { 
                        <%s%d> coop:%s <%s%d> .
                        << <%s%d> coop:%s <%s%d> >> coop:birth %d . 
                    }",
                $statement_iri, $parent_id, $rel, $statement_iri, $child_id );
            }

            array_push( $parent_id_array, $parent_id );
            DataIO::post_data( $query, $post_uri );
        }

        // Last element excluded from for loop to avoid adding extra comma
        // See $parent_id_array definition above for why the steps below are taken
        $parent_uris = "";
        for ( $i = 0; $i < ( count( $parent_id_array ) - 1 ); $i++ ) {
            $id = "<" . $statement_iri . strval($parent_id_array[$i]) . ">";
            $parent_uris .= $id . ", ";
        }
        $parent_uris .= "<" . $statement_iri . strval( array_pop( $parent_id_array ) ) . ">";

        $query = sprintf( 
        "PREFIX coop: <http://cooperman.org/terms/>;
        DELETE WHERE { 
            ?parent ?rel <%s%d> .
            FILTER ( ?parent NOT IN ( %s )  ) }",
        $statement_iri, $child_id, $parent_uris );

        DataIO::post_data( $query, $get_uri );

        // Returns an empty string to wp_postmeta, disabling saving
        return __return_empty_string();
    }

    /**
     * This function uses the rwmb_person_parent_group_meta filter to retrieve
     * parent data from the GraphDB database and display it in the People form for
     * a given post. As the data (parent name and exact relationship to child 
     * (biological, adoptive, or foster parent)) is most suited to a graph database,
     * and not the default wp_postmeta table, it is intercepted on submission and
     * written there.
     */
    public static function read_parent_data() {
        // Getting settings
        $settings = DataIO::get_settings();
        $post_uri = $settings['person_settings_post_uri'];
        $get_uri = $settings['person_settings_get_uri'];
        $statement_iri = $settings['person_settings_statement_iri'];
        substr( $statement_iri, -1 ) == "/" ? $statement_iri : $statement_iri . "/"; // Ensuring there's a slash at the end of the iri
        
        global $wpdb;

        $post_id = get_the_id();

        // A SPARQL query that searches for any parents of the post in the GraphDB
        // database.
        $parent_query = sprintf( 
            "PREFIX coop: <http://cooperman.org/terms/>
            SELECT ?parent ?rel ?child
            WHERE {
                ?parent ?relationship ?child;
                    ?relationship <%s%d> .
                FILTER ( ?relationship IN ( coop:bio, coop:adopt, coop:foster ) )
            }", 
        $statement_iri, $post_id );

        /*  The GET request performed in get_data() returns a table as a string, 
            rows separated by newlines, columns separated by commas:
                val1,val2,val3 
                val4,val5,val6, 
                val7,val8,val9'
        */
        $data = DataIO::get_data( $parent_query, $get_uri );
        $parent_table = explode( "\n", $data );
        // Last element (empty string) removed
        array_pop( $parent_table );
        
        $parents = array();

        // The first row is always 'parent,relationship,child', so if it is the
        // only row we just return a $parents array with an empty parent
        if ( count( $parent_table ) == 1 ) {
            array_push( $parents, array( "person_parent_name" => "", "person_parent_type" => "none" ) );
        } else {
            foreach( $parent_table as $parent ) {
                $parent = explode( ',', $parent );
                // skip first row (always comprised of 'parent,relationship,child')
                if ( $parent[0] == "parent" ) {
                    continue;
                }
                // get id from uri (don't go backwards (i.e. substr($parent[0], -3))
                // because the number of digits varies in the post id)
                $parent_id = substr($parent[0], 28);
                // select name from wp_postmeta by post_id ($parent_id)
                $parent_name_query = $wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id = %d;", "post_title", $parent_id);
                $parent_name = $wpdb->get_row( $parent_name_query, ARRAY_N )[0];

                // $parent_id and $parent_rel are derived from rows of the following
                // format:
                // <http://example.com/people/123> <http://example.com/terms/bio> <http://example.com/people/456>
                // where 123 is the parent id, bio is the relationship, and 456 is the child id
                $parent_rel = substr( $parent[1], 27, -6 );

                array_push( $parents, array("person_parent_name" => $parent_name, "person_parent_type" => $parent_rel ) );
            }
        }

        return $parents;
    }

}

?>
