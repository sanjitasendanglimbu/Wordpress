//this code for AI Tools Custom shortcode 
function my_jquery_shortcode_with_navigation() {
    wp_enqueue_script('jquery');

    // Add inline JavaScript for AJAX functionality
    $script = "
        jQuery(document).ready(function($) {
            let currentPage = 1;
            let filters = { category: 'all', search: '' };

            function loadAITools(page) {
                $('.navigation-buttons button').prop('disabled', true);

                $.ajax({
                    url: '" . admin_url('admin-ajax.php') . "',
                    type: 'POST',
                    data: {
                        action: 'filter_ai_tools',
                        page: page,
                        filters: filters
                    },
                    success: function(response) {
                        if (response.html) {
                            $('.ai-tools-grid').html(response.html);
                            currentPage = page;

                            // Enable/disable buttons based on page availability
                            if (response.has_previous) {
                                $('.prev-btn').prop('disabled', false);
                            } else {
                                $('.prev-btn').prop('disabled', true);
                            }

                          if (response.has_next) {
                                $('.next-btn').prop('disabled', false);
                           } else {
                                $('.next-btn').prop('disabled', true);
                            }
               
                        }
                    }
                });
            }

            // Initial Load
            loadAITools(currentPage);

            // Category Filter
            $('.filter-btn').on('click', function() {
                currentPage = 1;
                filters.category = $(this).data('category');
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                loadAITools(currentPage);
            });

            // Live Search
            $('#ai-search').on('input', function() {
                currentPage = 1;
                filters.search = $(this).val();
                loadAITools(currentPage);
            });

            // Next and Previous Buttons
            $('.next-btn').on('click', function() {
                loadAITools(currentPage + 1);
            });

            $('.prev-btn').on('click', function() {
                loadAITools(currentPage - 1);
            });
        });
    ";
    wp_add_inline_script('jquery', $script);

    ob_start();

    // Fetch categories dynamically (with posts)
    $categories = get_terms([
        'taxonomy' => 'category',
        'hide_empty' => true, // Only show categories with posts
    ]);

    // Only render the filter if there are categories with posts
    if (!empty($categories) && !is_wp_error($categories)) {
        echo '<div class="ai-tools-navbar">';
        echo '<div class="ai-tools-search">';
        echo '<input type="text" id="ai-search" placeholder="Search AI Tools">';
        echo '</div>';
        echo '<div class="ai-tools-filter">';

        // Add "All" filter
        echo '<button class="filter-btn active" data-category="all">All</button>';

        // Dynamically add categories (excluding Uncategorized)
        foreach ($categories as $category) {
            if ($category->slug !== 'uncategorized') {
                echo '<button class="filter-btn" data-category="' . esc_attr($category->name) . '">' . esc_html($category->name) . '</button>';
            }
        }

        echo '</div>';
        echo '</div>';
    }

    // Grid and navigation buttons
    echo '<div class="ai-tools-grid"></div>';
    echo '<div class="navigation-buttons">';
    echo '<button class="prev-btn" disabled>Prev</button>';
    echo '<button class="next-btn" disabled>Next</button>';
    echo '</div>';

    return ob_get_clean();
}
add_shortcode('ai_tools_post_list', 'my_jquery_shortcode_with_navigation');



function filter_ai_tools() {
    $filters = isset($_POST['filters']) ? $_POST['filters'] : [];
    $category = isset($filters['category']) ? sanitize_text_field($filters['category']) : 'all';
    $search = isset($filters['search']) ? sanitize_text_field($filters['search']) : '';
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1; 

    $args = [
        'post_type' => 'ai',
        'posts_per_page' => 12,
        'paged' => $page,
        's' => $search,
    ];

    if ($category !== 'all') {
        $args['tax_query'] = [
            [
                'taxonomy' => 'category',
                'field' => 'name',
                'terms' => $category,
            ],
        ];
    }

    $query = new WP_Query($args);
    $response = ['html' => '', 'has_next' => false, 'has_previous' => false];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $response['html'] .= '<div class="ai-tool-card">';
            $response['html'] .= '<div class="icon"><i class="fas fa-book"></i></div>';
            $response['html'] .= '<h3>' . get_the_title() . '</h3>';
            $response['html'] .= '<p>' . get_the_excerpt() . '</p>';
            $response['html'] .= '<a href="' . get_permalink() . '" class="button">Go to ' . get_the_title() . '</a>';
            $response['html'] .= '</div>';
        }

        $response['has_previous'] = $page > 1;
        $response['has_next'] = $page < $query->max_num_pages;
    } else {
        $response['html'] .= '<p>No AI tools found.</p>';
    }

    wp_reset_postdata();

    wp_send_json($response);
}
add_action('wp_ajax_filter_ai_tools', 'filter_ai_tools');
add_action('wp_ajax_nopriv_filter_ai_tools', 'filter_ai_tools');
function ai_tools_inline_styles_with_navbar() {
    echo "
    <style>
 .ai-tools-search {
            text-align: center;
            margin-bottom: 20px;
        }
       #ai-search {
            width: 80%;
            max-width: 400px;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
	.ai-tools-filter {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-bottom: 20px;
        }

.filter-btn {
    padding: 5px 10px;
    font-size: 14px;
    color: #9967f8;
    background: transparent;
    border: none;
    border-bottom: 1px solid transparent;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-btn.active {
    color: #ff683b;
    border-color: #ff683b;
}

.filter-btn:hover {
    color: #ff683b;
}

/* Card Grid Layout */
.ai-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin: 0 auto;
    max-width: 1200px;
}

/* Individual Cards */
.ai-tool-card {
    text-align: center;
    padding: 20px;
	width: 250px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.ai-tool-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
}

/* Icon Styling */
.ai-tool-card .icon {
    font-size: 40px;
    color: #28a745;
    margin-bottom: 15px;
}

/* Title Styling */
.ai-tool-card h3 {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

/* Description Styling */
.ai-tool-card p {
    font-size: 14px;
    color: #666;
    margin-bottom: 20px;
    line-height: 1.5;
}

/* Button Styling */
.ai-tool-card .button {
    display: inline-block;
    padding: 10px 15px;
    font-size: 14px;
	font-weight: 600;
    color: #000;
    text-decoration: none;
    border: 1px solid #ddd;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.ai-tool-card .button:hover {
    background: #ff683b;
    color: #fff;
	 border: none;
}

/* Badge Styling */
.ai-tool-card .badge {
    display: inline-block;
    padding: 5px 10px;
    font-size: 12px;
    background: #ff683b;
    color: #fff;
    border-radius: 3px;
    margin-top: -10px;
    position: relative;
    top: -10px;
}


/* Next and prev Btn Styling */
.navigation-buttons {
    position: relative;
   max-width: 1200px;
    height: 60px; 
    margin-top: 20px;
}

.navigation-buttons .prev-btn {
    position: absolute;
    top: 50%;
    left: 10px;
    transform: translateY(-50%);
    padding: 10px 20px;
    width: auto;
    font-size: 14px;
    color: #9967f8;
    border: 2px solid #ddd;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.navigation-buttons .next-btn {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    padding: 10px 20px;
    width: auto;
    font-size: 14px;
    color: #9967f8;
    border: 2px solid #ddd;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
}


.navigation-buttons button:hover:not(:disabled) {
    border-color: #ff683b;
	color:#ff683b;
}

.navigation-buttons button:disabled {

     display: none;
}



    </style>
    ";
}
add_action('wp_head', 'ai_tools_inline_styles_with_navbar');
