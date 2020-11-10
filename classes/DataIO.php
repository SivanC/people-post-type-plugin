<?php

/**
 * This class contains methods used by ParentIO, ChildIO, and PartnerIO to read
 * and write data to/from the GraphDB database.
 */
class DataIO {
    /**
     * Sends a POST request to a given URL with an attached 'update' argument.
     * 
     * @param update The argument to pass to the URL
     * @param url The URL to send the POST request to
     * 
     * @return body The response body returned by the URL
     */
    public static function post_data( $update, $url ) {
        $request = array(
            'update' => $update
        );

        // Compiling our array of arguments to send to the server
        $args = array(
            'body' => $request,
            'headers' => array(
                'Accept' => 'application/x-trig'
            )
        );

        // POST the data to the database
        $response = wp_remote_post( $url, $args);
        $body = wp_remote_retrieve_body( $response );
        return $body;
    }

    /**
     * Sends a GET request to a URL with an attached 'query' argument. Notice
     * that, while post_data() uses a cleaner method, by putting the update
     * argument in the request variable, that is not possible here for some
     * reason. Instead, the argument must be URL-encoded and appended to the URL.
     * 
     * @param query The argument to pass to the URL
     * @param URL The URL to send the GET request to
     * 
     * @return body The response body returned by the URL
     */
    public static function get_data( $query, $url ) {

        $query = urlencode( $query );

        $response = wp_remote_get( $url . '?query=' . $query );
        $body = wp_remote_retrieve_body( $response );
        return $body;
    }

    /**
     * Sends a POST request to a URL with an attached 'delete' argument, which
     * removes all relationships from the GraphDB database that contain
     * the given post ID.
     * 
     * @param post_id The ID of the Person/post to be scrubbed from the
     * database.
     * @param url The URL to send the POST request to
     * 
     * @return body The response body returned by the URL
     */
    public static function delete_data( $post_id, $url = 'http://localhost:7200/repositories/test-repo/statements' ) {
        $query = sprintf( 'PREFIX coop: <http://cooperman.org/terms/>;
        DELETE { ?parent ?relationship ?child ?order }
        WHERE { 
            { 
                ?parent ?relationship ?child .
                <http://cooperman.org/people/%1$d> ?relationship ?child .
            } UNION {
                ?parent ?relationship ?child .
                ?parent ?relationship <http://cooperman.org/people/%1$d> . 
            } UNION {
                ?parent ?relationship ?child ?order
                << <http://cooperman.org/people/%1$d> ?relationship ?child >> coop:birthOrder ?order .
            } UNION {
                ?parent ?relationship ?child ?order
                <<  ?parent ?relationship <http://cooperman.org/people/%1$d> >> coop:birthOrder ?order .
            }
        }', $post_id ); // Use %1$d instead of %d in order to not have to repeat the argument 4 times

        return DataIO::post_data( $query, $url );
    }

    public static function get_settings() {
        global $wpdb;

        $settings = array(
            'person_settings_post_uri' => rwmb_meta( 'person_settings_post_uri', ['object_type' => 'setting'], 'person_settings' ),
            'person_settings_get_uri' => rwmb_meta( 'person_settings_get_uri', ['object_type' => 'setting'], 'person_settings' ),
            'person_settings_statement_iri' => rwmb_meta( 'person_settings_statement_iri', ['object_type' => 'setting'], 'person_settings' ),
        );

        return $settings;
    }

    public static function console_log( $string ) {
        echo("<script>console.log(\"{$string}\");</script>");
    }
}

?>