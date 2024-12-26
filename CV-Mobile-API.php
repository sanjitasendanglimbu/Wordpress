<?php
/**
 * Plugin Name: chimpvine Mobile API
 * Description: Custom REST API to fetch taxonomy terms and filter posts with optimized performance.
 * Version: 1.3
 * Author: Your Name
 */

// Hook into REST API initialization
add_action('rest_api_init', function () {
    // Register a GET route to fetch taxonomy terms
    register_rest_route('mobileapp/v1', '/taxonomy-terms', [
        'methods' => 'GET',
        'callback' => 'get_optimized_taxonomy_terms',
        'permission_callback' => '__return_true',
    ]);

    // Register a POST route for filtering posts
    register_rest_route('mobileapp/v1', '/filter-posts', [
        'methods' => 'POST',
        'callback' => 'optimized_filter_posts',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Fetch terms of any taxonomy with caching.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function get_optimized_taxonomy_terms($request) {
    $taxonomy = $request->get_param('taxonomy');

    // Validate taxonomy
    if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
        return rest_ensure_response([
            'success' => false,
            'message' => 'Invalid or missing taxonomy.',
        ]);
    }

    // Check cache first
    $cache_key = "taxonomy_terms_{$taxonomy}";
    $terms_data = wp_cache_get($cache_key);

    if ($terms_data === false) {
        // Fetch terms if not in cache
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
                'message' => 'No terms found.',
            ]);
        }

        // Format terms data
        $terms_data = array_map(function ($term) use ($taxonomy) {
            return [
                'termId' => $term->term_id,
                'name'   => $term->name,
                'image'  => get_field('image', "{$taxonomy}_{$term->term_id}"), // ACF field
            ];
        }, $terms);

        // Cache the terms data
        wp_cache_set($cache_key, $terms_data, '', HOUR_IN_SECONDS);
    }

    return rest_ensure_response([
        'success' => true,
        'data' => $terms_data,
    ]);
}

/**
 * Filter posts based on taxonomy terms with pagination and caching.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function optimized_filter_posts($request) {
    $parameters = $request->get_json_params();

    // Pagination parameters
    $page = isset($parameters['page']) ? max(1, intval($parameters['page'])) : 1;
    $posts_per_page = 12;

    // Query arguments
    $query_args = [
        'post_type'      => 'posts',
        'post_status'    => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged'          => $page,
        'tax_query'      => [],
    ];

    // Build tax queries dynamically
    if (!empty($parameters['filters']) && is_array($parameters['filters'])) {
        foreach ($parameters['filters'] as $taxonomy => $terms) {
            if (!empty($terms) && taxonomy_exists($taxonomy)) {
                $query_args['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => array_map('intval', $terms),
                ];
            }
        }

        // Add relation only if tax_query is not empty
        if (!empty($query_args['tax_query'])) {
            $query_args['tax_query']['relation'] = 'AND';
        }
    }

    // Cache key for the query
    $cache_key = 'filtered_posts_' . md5(json_encode($query_args));
    $posts = wp_cache_get($cache_key);

    if ($posts === false) {
        // Execute the query if not in cache
        $query = new WP_Query($query_args);
        $posts = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                // Add post data to response
                $posts[] = [
                    'title' => get_the_title(),
                    'link'  => get_permalink(),
                    'image' => has_post_thumbnail() ? get_the_post_thumbnail_url() : '',
                ];
            }
            wp_reset_postdata();
        }

        // Cache the result
        wp_cache_set($cache_key, $posts, '', HOUR_IN_SECONDS);
    }

    return rest_ensure_response([
        'success' => true,
        'data' => $posts,
        'meta'    => [
            'total_posts' => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'current_page' => $page,
        ],
    ]);
}
