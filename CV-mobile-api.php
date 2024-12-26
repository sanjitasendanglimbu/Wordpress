<?php
/**
 * Plugin Name: Dynamic Taxonomy REST API
 * Description: Custom REST API endpoint to fetch terms of any taxonomy dynamically and filter posts with taxonomies and ACF fields.
 * Version: 1.2
 * Author: Your Name
 */

// Hook into the REST API initialization
add_action('rest_api_init', function () {
    // Register a GET route to fetch taxonomy terms
    register_rest_route('elearning/v1', '/taxonomy-terms', [
        'methods' => 'GET',
        'callback' => 'get_dynamic_taxonomy_terms',
        'permission_callback' => '__return_true', // Public access
    ]);

    // Register a POST route for filtering posts
    register_rest_route('elearning/v1', '/filter-posts', [
        'methods' => 'POST',
        'callback' => 'custom_filter_posts',
        'permission_callback' => '__return_true', // Public access
    ]);
});

/**
 * Fetch terms of any taxonomy dynamically.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function get_dynamic_taxonomy_terms($request) {
    $taxonomy = $request->get_param('taxonomy');

    // Validate the taxonomy
    if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
        return rest_ensure_response([
            'success' => false,
            'message' => 'Invalid or missing taxonomy.',
        ]);
    }

    // Get terms for the specified taxonomy
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
        return rest_ensure_response([
            'success' => false,
            'message' => $terms->get_error_message(),
        ]);
    }

    if (empty($terms)) {
        return rest_ensure_response([
            'success' => false,
            'message' => 'No terms found for the specified taxonomy.',
        ]);
    }

    // Build response data
    $terms_data = array_map(function ($term) use ($taxonomy) {
        return [
            'termId' => $term->term_id,
            'name'   => $term->name,
            'image'  => get_field('image', "{$taxonomy}_{$term->term_id}"), // ACF field
        ];
    }, $terms);

    return rest_ensure_response([
        'success' => true,
        'data' => $terms_data,
    ]);
}


/**
 * Filter posts based on taxonomy terms and custom fields.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function custom_filter_posts($request) {
    $parameters = $request->get_json_params();

    // Define query arguments
    $query_args = [
        'post_type'      => 'e-learning',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'tax_query'      => [],
    ];

    // Build tax queries dynamically
    if (!empty($parameters) && is_array($parameters)) {
        foreach ($parameters as $taxonomy => $terms) {
            if (!empty($terms) && taxonomy_exists($taxonomy)) {
                $query_args['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => array_map('intval', $terms),
                ];
            }
        }

        // Add 'relation' only if tax_query is not empty
        if (!empty($query_args['tax_query'])) {
            $query_args['tax_query']['relation'] = 'AND';
        }
    }

    // Execute the query
    $query = new WP_Query($query_args);

    // Initialize response data
    $posts = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            // Fetch taxonomy terms dynamically
            // $taxonomy_data = [];
            // if (!empty($parameters) && is_array($parameters)) {
            //     foreach ($parameters as $taxonomy => $terms) {
            //         if (taxonomy_exists($taxonomy)) {
            //             $taxonomy_terms = get_the_terms(get_the_ID(), $taxonomy);
            //             if (!empty($taxonomy_terms) && is_array($taxonomy_terms)) {
            //                 $taxonomy_data[$taxonomy] = array_map(function ($term) {
            //                     return [
            //                         'termId' => $term->term_id,
            //                         'name'   => $term->name,
            //                         'image'  => get_field('image', "{$taxonomy}_{$term->term_id}"),
            //                     ];
            //                 }, $taxonomy_terms);
            //             } else {
            //                 $taxonomy_data[$taxonomy] = [];
            //             }
            //         }
            //     }
            // }

            // Add post data to response
            $posts[] = [
                'title'      => get_the_title(),
                'link'       => get_permalink(),
                'image'      => has_post_thumbnail() ? get_the_post_thumbnail_url() : '',
            
            ];
        }
        wp_reset_postdata();
    }

    return rest_ensure_response([
        'success' => true,
        'data'    => $posts,
    ]);
}
