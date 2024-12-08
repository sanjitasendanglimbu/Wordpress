This is perfect work
// Shortcode function to display movie filter and movie list
function movie_filter_shortcode() {
    ob_start();
    ?>
    <div id="movie-filter">
        <div class="filters">
            <input type="text" id="movie-search" placeholder="Search by title..." />

            <h4>Category</h4>
            <select id="movie-category">
                <option value="">Choose a category</option>
                <?php
                $categories = get_terms(['taxonomy' => 'movies-category', 'hide_empty' => true]);
                foreach ($categories as $category) {
                    echo '<option value="' . $category->slug . '">' . $category->name . ' (' . $category->count . ')</option>';
                }
                ?>
            </select>

            <h4>Tags</h4>
            <div id="movie-tags">
                <?php
                $tags = get_terms(['taxonomy' => 'movie-tag', 'hide_empty' => true]);
                foreach ($tags as $tag) {
                    echo '<label><input type="checkbox" value="' . $tag->slug . '"> ' . $tag->name . '</label><br>';
                }
                ?>
            </div>

            <button id="reset-filters">Reset</button>
        </div>

        <div id="movie-list">
            <!-- Movies will be displayed here -->
        </div>
    </div>

    <script>
 jQuery(document).ready(function($) {
    function loadMovies() {
        let search = $('#movie-search').val();
        let category = $('#movie-category').val();
        let tags = $('#movie-tags input:checked').map(function() { return $(this).val(); }).get();

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'filter_movies',
                search: search,
                category: category,
                tags: tags
            },
            success: function(response) {
                $('#movie-list').html(response);
            },
            error: function() {
                $('#movie-list').html('<p>An error occurred while loading movies. Please try again.</p>');
            }
        });
    }

    function updateTags() {
        let category = $('#movie-category').val();

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'get_tags_by_category',
                category: category
            },
            success: function(response) {
                $('#movie-tags').html(response);
            },
            error: function() {
                $('#movie-tags').html('<p>An error occurred while loading tags. Please try again.</p>');
            }
        });
    }

    // Event Listeners
    $('#movie-search').on('input', loadMovies);
    $('#movie-category').on('change', function() {
        updateTags(); // Update tags based on the selected category
        loadMovies(); // Load movies after updating tags
    });
    $('#movie-tags').on('change', 'input', loadMovies);
    
    // Reset Filters button functionality - page reload to reset all filters
    $('#reset-filters').on('click', function() {
        location.reload(); // This reloads the page and resets everything to initial state
    });

    // Initial Load
    loadMovies();
});

    </script>

    <style>
        #movie-category {
            width: 100%;
            padding: 8px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        #movie-filter {
            display: flex;
            gap: 20px;
            max-width: 1200px;
            border: 1px solid black;
        }

        .filters {
            width: 25%;
            padding: 20px;
            border-right: 1px solid #ddd;
            background: #f9f9f9;
        }

        #movie-search {
            width: 250px;
            height: 30px;
            border-radius: 10px;
            font-size: 16px;
            color: #ddd;
            padding: 5px 10px;
        }

        #movie-list {
            width: 75%;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .movie-item {
            text-align: center;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 10px;
            transition: box-shadow 0.3s ease;
        }

        .movie-item:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .movie-item img {
            max-width: 100%;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .movie-item h3 {
            font-size: 18px;
            margin: 10px 0;
        }

        .movie-item p {
            font-size: 14px;
            color: #666;
        }

        .movie-item a {
            display: inline-block;
            margin-top: 10px;
            padding: 5px 10px;
            background: #0073aa;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
        }

        .movie-item a:hover {
            background: #005177;
        }

        #movie-list .movie-item {
            border: 1px solid #ddd;
            padding: 10px;
            margin: 10px 0;
        }

        #movie-list .movie-item img {
            max-width: 100%;
            height: auto;
            display: block;
            margin-bottom: 10px;
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('movie_filter', 'movie_filter_shortcode');

// AJAX Handler for filtering movies
function filter_movies_ajax_handler() {
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $tags = isset($_POST['tags']) ? $_POST['tags'] : [];

    // Set up the query
    $args = [
        'post_type' => 'movie',
        'posts_per_page' => -1,
        's' => $search,
        'tax_query' => [
            'relation' => 'AND',
        ],
    ];

    if (!empty($category)) {
        $args['tax_query'][] = [
            'taxonomy' => 'movies-category',
            'field' => 'slug',
            'terms' => $category,
        ];
    }

    if (!empty($tags)) {
        $args['tax_query'][] = [
            'taxonomy' => 'movie-tag',
            'field' => 'slug',
            'terms' => $tags,
            'operator' => 'IN',
        ];
    }

    $movies = new WP_Query($args);

    if ($movies->have_posts()) {
        while ($movies->have_posts()) {
            $movies->the_post();
            ?>
            <div class="movie-item">
                <a href="<?php the_permalink(); ?>">
                    <img src="<?php the_post_thumbnail_url('medium'); ?>" alt="<?php the_title(); ?>">
                    <h3><?php the_title(); ?></h3>
                    <p><?php the_excerpt(); ?></p>
                </a>
            </div>
            <?php
        }
    } else {
        echo '<p>No movies found.</p>';
    }

    wp_die();
}
add_action('wp_ajax_filter_movies', 'filter_movies_ajax_handler');
add_action('wp_ajax_nopriv_filter_movies', 'filter_movies_ajax_handler');

// AJAX Handler for updating tags based on selected category
function get_tags_by_category_ajax_handler() {
    $category = !empty($_POST['category']) ? sanitize_text_field($_POST['category']) : '';

    // Find all movies within the selected category
    $args = [
        'post_type' => 'movie',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'movies-category',
                'field' => 'slug',
                'terms' => $category,
            ],
        ],
    ];

    $movie_ids = get_posts($args);

    if ($movie_ids) {
        // Get tags associated with these movies
        $tags = wp_get_object_terms($movie_ids, 'movie-tag', ['fields' => 'all']);

        if ($tags && !is_wp_error($tags)) {
            foreach ($tags as $tag) {
                echo '<label><input type="checkbox" value="' . esc_attr($tag->slug) . '"> ' . esc_html($tag->name) . '</label><br>';
            }
        } else {
            echo '<p>No tags found for this category.</p>';
        }
    } else {
        echo '<p>No movies found in this category.</p>';
    }

    wp_die();
}
add_action('wp_ajax_get_tags_by_category', 'get_tags_by_category_ajax_handler');
add_action('wp_ajax_nopriv_get_tags_by_category', 'get_tags_by_category_ajax_handler');
