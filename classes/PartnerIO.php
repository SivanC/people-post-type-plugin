<?php

/**
 * This class handes IO of partner data between the wordpress meta box form and the
 * GraphDB database. In other IO classes, there is a clear distinction between
 * the two parties (parent and child, child and parent). As both partners in a
 * relationship are referred to as partner, herein the partner represented by
 * the main post will be referred to as partner, and the groups therein 
 * representing other records will be referred to as partnee, except in the
 * plural.
 */
class PartnerIO {

    /**
     * This function uses the rwmb_person_partner_group_value filter to intercept
     * partner data sent by the People post and write it to the GraphDB database.
     * As the data (partner name and exact relationship to their partner (married,
     * partnered, or civil partnership, as well as relationship order), is most suited to a graph 
     * database, and not the default wp_postmeta table, it is intercepted on 
     * submission and written there.
     * 
     * @param partners The data, in the form of a two-dimensional array.
     * The $partner array corresponds to the entirety of the "partner" group, and
     * each subarray contains key-value ("person_partner_name", "person_partner_type",
     * "person_partner_start_date", "person_partner_end_date", and "relationship_order"
     * are the keys) pairs corresponding to a single partnership.
     */
    public static function write_partner_data( $partners ) {
        // Getting settings
        $settings = DataIO::get_settings();
        $post_uri = $settings['person_settings_post_uri'];
        $get_uri = $settings['person_settings_get_uri'];
        $statement_iri = $settings['person_settings_statement_iri'];
        substr( $statement_iri, -1 ) == "/" ? $statement_iri : $statement_iri . "/"; // Ensuring there's a slash at the end of the iri

        // By definition, the post posting the data is the partner of the partnee
        // being posted.
        $partner_id = get_the_ID();

        /*  As $partners is only a record of what *should* be in the databse, and
            not what shouldn't (for example, if a partner-partnee relationship was 
            written and then removed on the front end, write_partner_data() would
            not otherwise pick up on the absence of a relationship from 
            $partner), the id of every partnership is collected as we
            iterate through $partners, and then ones that are found only in the
            database are removed.
        */
        $partnee_id_array = array();

        foreach ( $partners as $index => $partnee ) {
            /* There must always be at least one partner group on the front end,
               so the only way to delete all partners is by removing all the 
               others and leaving the first one blank.
            */
            if ( count( $partners ) == 1 && $partnee["person_partner_name"] == "" && $partnee["person_partner_type"] == "none"
                    && $partnee["person_partner_start_date"] == "" && $partnee["person_partner_end_date"] == "" ) {
                // This query deletes all child relationships to the parent
                // $query = sprintf( "PREFIX coop: <http://cooperman.org/terms/>;
                // DELETE { ?partner ?rel ?child . } 
                // WHERE { ?parent ?rel ?child .
                //     <http://cooperman.org/people/%d> ?rel ?child . };",
                //     $parent_id );

                DataIO::post_data( $query, $post_uri );
                continue;
            } 
            
            if ( $partnee["person_partner_name"] == "" || $partnee["person_partner_type"] == "none" ) {
                continue;
            }

            // get_id() retrieves the post_id value from wp_postmeta using the name of the partnee.
            // returns -1 if the partnee is not found.
            $partnee_id = intval( get_id( $partnee['person_partner_name'] ) );
            // One of: none (default value), bio, adopt, foster
            $rel = $partnee['person_partner_type'];

            if ( $partnee_id != -1 ) {
                /*  $query is a SPARQL query that is sent to the GraphDB database
                    Deletes all previous relationships between the partnee and partner and
                    inserts the new relationship (as married, partnered, or 
                    civil) using the partnee and partner's IDs (same as their post IDs).
                    Note this assumes the partnee is in the database, which is validated
                    via the above conditional. Also adds the partnee's relationship order using
                    SPARQL* notation to denote the partnership order specific to that partner-
                    partnee coupling.
                */
                $query = sprintf( "PREFIX coop: <http://cooperman.org/terms/>; 
                                DELETE { 
                                    ?parent ?rel ?child .
                                    << ?parent ?rel ?child >> coop:birthOrder ?order . } 
                                WHERE { ?parent ?rel ?child .
                                    <http://cooperman.org/people/%d> ?rel <http://cooperman.org/people/%d> . };
                                INSERT DATA { 
                                    <http://cooperman.org/people/%d> coop:%s <http://cooperman.org/people/%d> . 
                                    << <http://cooperman.org/people/%d> coop:%s <http://cooperman.org/people/%d> >> coop:birthOrder %d .
                                }",
                    $parent_id, $child_id,
                    $parent_id, $rel, $child_id,
                    $parent_id, $rel, $child_id, $birth_order );
            } else {
                // The format for 'person_partner_group' within $meta_input is 
                // similar to the 'person_parent_group' format expounded upon
                // in the write_partner_data() doc
                $meta_input = array(
                    'post_title' => $partnee['person_partner_name'],

                    'person_partner_group' => array(
                        array(
                            'person_partner_name' => rwmb_meta( 'post_title' ),

                            'person_partner_type' => $rel,

                            'person_partner_start_date' => $partnee['person_partner_start_date'],

                            'person_partner_end_date' => $partnee['person_partner_end_date'],
                        )
                    )
                );

                $postarr = array(
                    'post_title' => $partnee['person_partner_name'],

                    'post_content' => '',

                    'post_status' => 'publish',

                    'post_type' => 'person',

                    'meta_input' => $meta_input
                );

                // If the act of inserting the post generates any errors they are
                // saved here
                $error = wp_insert_post( $postarr, true );

                $partnee_id = intval( get_id( $child['person_partner_name'] ) );
                
                // This query does not worry about deleting existing data because
                // the child is assumed to be absent from both databases.
                $query = sprintf( "PREFIX coop: <http://cooperman.org/terms/>; 
                                INSERT DATA { 
                                    <http://cooperman.org/people/%d> coop:%s <http://cooperman.org/people/%d> .
                                    << <http://cooperman.org/people/%d> coop:%s <http://cooperman.org/people/%d> >> coop:birthOrder %d
                                }",
                    $parent_id, $rel, $child_id,
                    $parent_id, $rel, $child_id, $birth_order );
            }

            array_push( $partnee_id_array, $partnee_id );
            DataIO::post_data( $query, $post_uri );
        }

        // Last element excluded from for loop to avoid adding extra comma
        // See $partnee_id_array definition above for why the steps below are taken
        $partnee_uris = "";
        for ( $i = 0; $i < ( count( $partnee_id_array ) - 1 ); $i++ ) {
            $id = "<http://cooperman.org/people/" . strval($partnee_id_array[$i]) . ">";
            $partnee_uris .= $id . ", ";
        }
        $partnee_uris .= "<http://cooperman.org/people/" . strval( array_pop( $partnee_id_array ) ) . ">";

        // This query deletes all partners who are not in $partnee_uris
        $query = sprintf( "PREFIX coop: <http://cooperman.org/terms/>;
        DELETE { ?parent ?relationship ?child . }
        WHERE { 
            ?parent ?relationship ?child .
            <http://cooperman.org/people/%d> ?relationship  ?child .
            FILTER ( ?child NOT IN ( %s )  )
        }", $parent_id, $child_uris );

        DataIO::post_data( $query, $post_uri );

        // Returns an empty string to wp_postmeta, disabling saving
        return __return_empty_string();
    }

    /**
     * This function uses the rwmb_person_partner_group_meta filter to retrieve
     * partnee data from the GraphDB database and display it in the People form for
     * a given post. As the data is most suited to a graph database, and not the
     * default wp_postmeta table, it is intercepted on submission and written to there.
     */
    public static function read_partner_data() {
        // Getting settings
        $settings = DataIO::get_settings();
        $post_uri = $settings['person_settings_post_uri'];
        $get_uri = $settings['person_settings_get_uri'];
        $statement_iri = $settings['person_settings_statement_iri'];
        substr( $statement_iri, -1 ) == "/" ? $statement_iri : $statement_iri . "/"; // Ensuring there's a slash at the end of the iri

        global $wpdb;

        $post_id = get_the_id();

        // A SPARQL query that searches for any partners of the post in the GraphDB
        // database.
        $partnee_query = sprintf( 
            "PREFIX coop: <http://cooperman.org/terms/>
            SELECT ?parent ?relationship ?child ?order
            WHERE {
                << ?parent ?relationship ?child >> coop:birthOrder ?order .
                << <http://cooperman.org/people/%d> ?relationship ?child >> coop:birthOrder ?order .
                FILTER ( ?relationship IN ( coop:bio, coop:adopt, coop:foster ) )
            }", $post_id );

        /*  The GET request performed in get_data() returns a table as a string, 
            rows separated by newlines, columns separated by commas:
                val1,val2,val3,val4 
                val5,val6,val7,val8
                val9,val10,val11,val12
        */
        $data = DataIO::get_data( $partnee_query, $get_uri );
        $partnee_table = explode( "\n", $data );
        // Last element (empty string) removed
        array_pop( $partnee_table );
        //make_post(print_r($partnee_table, true));
        
        $partners = array();

        // The first row is always 'partner,relationship,partnee', so if it is the
        // only row we just return a $partners array with an empty partnee
        if ( count( $partnee_table ) == 1 ) {
            array_push( $partners, array( "person_partnee_name" => "",
                                        "person_partnee_type" => "none",
                                        "person_partner_start_date" => "",
                                        "person_partner_end_date" => "" ) );
        } else {
            foreach( $partnee_table as $index => $partnee ) {
                // skip first row (headers, always comprised of 'partner,relationship,partnee,order')
                if ( $index == 0 ) {
                    //make_post("Skipped row 0");
                    continue;
                }
                $partnee = explode( ',', $partnee );
                // get id from uri (don't go backwards (i.e. substr($partnee[0], -3))
                // because the number of digits varies in the post id)
                $partnee_id = substr($partnee[2], 28);
                // select name from wp_postmeta by post_id ($partnee_id)
                $partnee_name_query = $wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id = %d;", "post_title", $partnee_id);
                $partnee_name = $wpdb->get_row( $partnee_name_query, ARRAY_N )[0];

                // $partnee_id and $partnee_rel are derived from rows of the following format:
                // <http://cooperman.org/people/123> <http://cooperman.org/terms/married> <http://cooperman.org/people/456>
                // where 123 is the partner id, married is the relationship, and 456 is the partnee id
                $partner_rel = substr( $partnee[1], 27 );

                $partner_order = intval( $partnee[3] );

                array_push( $partners, array("person_partner_name" => $partnee_name,
                                            "person_partner_type" => $partner_rel,
                                            "person_partner_start_date" => $start_date,
                                            "person_partner_end_date" => $end_date,
                                            "person_partner_order" => $partner_order ) );
            }
        }

        // Sorts the array by birth order
        uasort( $partners, function($a, $b) { return ($a["person_partner_order"] - $b["person_partner_order"] ); } );
        //make_post(print_r($children, true));
        return $partners;
    }

}

?>