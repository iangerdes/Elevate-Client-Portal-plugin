<?php
// File: elevate-client-portal/includes/class-ecp-shortcode-helper.php
/**
 * A dedicated helper class to safely interact with WordPress shortcodes.
 * This class prevents PHP notices when checking for shortcodes on pages
 * that do not have standard post content.
 *
 * @package Elevate_Client_Portal
 * @version 88.0.0 (Fatal Error Fix)
 * @comment Added strict checks for post object and content to prevent deprecated warnings in PHP.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class ECP_Shortcode_Helper {

    /**
     * Safely checks if a shortcode exists on the current singular page.
     *
     * @param string $shortcode_tag The shortcode tag to check for (e.g., 'client_portal').
     * @return bool True if the shortcode is found, false otherwise.
     */
    public static function page_has_shortcode( $shortcode_tag ) {
        if ( ! is_singular() ) {
            return false;
        }

        global $post;

        // ** FIX: Ensure we have a valid post object and that post_content is not null/empty. **
        if ( ! is_a( $post, 'WP_Post' ) || empty( $post->post_content ) ) {
            return false;
        }
        
        return has_shortcode( $post->post_content, $shortcode_tag );
    }
}

