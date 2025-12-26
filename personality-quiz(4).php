<?php
/**
 * Plugin Name: Personality Quiz
 * Description: Simple personality quiz with outcome-based results
 * Version: 1.0.0
 * Author: Seket
 */

if (!defined('ABSPATH')) exit;

class Personality_Quiz {
    
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('add_meta_boxes', [$this, 'remove_unwanted_meta_boxes'], 99);
        add_action('save_post_personality_quiz', [$this, 'save_quiz'], 10, 2);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_filter('manage_personality_quiz_posts_columns', [$this, 'set_columns']);
        add_action('manage_personality_quiz_posts_custom_column', [$this, 'render_columns'], 10, 2);
        add_shortcode('personality_quiz', [$this, 'render_quiz']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
    }
    
    // =========================================================================
    // CUSTOM POST TYPE
    // =========================================================================
    
    public function register_post_type() {
        if (post_type_exists('personality_quiz')) return;
        register_post_type('personality_quiz', [
            'labels' => [
                'name' => __('Quizzes', 'personality-quiz'),
                'singular_name' => __('Quiz', 'personality-quiz'),
                'menu_name' => __('Quizzes', 'personality-quiz'),
                'add_new' => __('Add New', 'personality-quiz'),
                'add_new_item' => __('Add New Quiz', 'personality-quiz'),
                'edit_item' => __('Edit Quiz', 'personality-quiz'),
                'new_item' => __('New Quiz', 'personality-quiz'),
                'view_item' => __('View Quiz', 'personality-quiz'),
                'search_items' => __('Search Quizzes', 'personality-quiz'),
                'not_found' => __('No quizzes found', 'personality-quiz'),
                'not_found_in_trash' => __('No quizzes found in trash', 'personality-quiz'),
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'quiz'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-forms',
            'supports' => ['title'],
            'show_in_rest' => false,
        ]);
    }
    
    public function remove_unwanted_meta_boxes() {
        foreach (['slugdiv','authordiv','commentsdiv','commentstatusdiv','trackbacksdiv','postcustom','revisionsdiv'] as $box) {
            remove_meta_box($box, 'personality_quiz', 'normal');
        }
    }
    
    // =========================================================================
    // ADMIN LIST COLUMNS
    // =========================================================================
    
    public function set_columns($columns) {
        return [
            'cb' => $columns['cb'],
            'title' => __('Quiz Name', 'personality-quiz'),
            'shortcode' => __('Shortcode', 'personality-quiz'),
            'questions' => __('Questions', 'personality-quiz'),
            'date' => __('Date', 'personality-quiz'),
        ];
    }
    
    public function render_columns($column, $post_id) {
        if ($column === 'shortcode') {
            $sc = '[personality_quiz id="' . $post_id . '"]';
            echo '<div class="pq-shortcode-cell"><input type="text" value="' . esc_attr($sc) . '" class="pq-shortcode-input" readonly onclick="this.select();"><button type="button" class="button button-small pq-copy-btn" data-shortcode="' . esc_attr($sc) . '">Copy</button><span class="pq-copied-msg">Copied!</span></div>';
        } elseif ($column === 'questions') {
            $q = get_post_meta($post_id, '_quiz_questions', true);
            echo is_array($q) ? count($q) : 0;
        }
    }
    
    // =========================================================================
    // META BOXES
    // =========================================================================
    
    public function add_meta_boxes() {
        add_meta_box('pq_settings', __('Quiz Settings', 'personality-quiz'), [$this, 'render_settings_box'], 'personality_quiz', 'normal', 'high');
        add_meta_box('pq_results', __('Quiz Results', 'personality-quiz'), [$this, 'render_results_box'], 'personality_quiz', 'normal', 'high');
        add_meta_box('pq_questions', __('Quiz Questions', 'personality-quiz'), [$this, 'render_questions_box'], 'personality_quiz', 'normal', 'high');
    }
    
    public function render_settings_box($post) {
        wp_nonce_field('pq_save', 'pq_nonce');
        $per_page = get_post_meta($post->ID, '_quiz_questions_per_page', true) ?: 5;
        $sc = '[personality_quiz id="' . $post->ID . '"]';
        ?>
        <div class="pq-settings-grid">
            <div class="pq-setting-item">
                <label for="pq_per_page"><?php _e('Questions Per Page', 'personality-quiz'); ?></label>
                <input type="number" id="pq_per_page" name="pq_per_page" value="<?php echo esc_attr($per_page); ?>" min="1" max="20" class="small-text">
            </div>
            <?php if ($post->post_status !== 'auto-draft'): ?>
            <div class="pq-setting-item">
                <label><?php _e('Shortcode', 'personality-quiz'); ?></label>
                <div class="pq-shortcode-wrap">
                    <input type="text" value="<?php echo esc_attr($sc); ?>" class="pq-shortcode-field" readonly onclick="this.select();">
                    <button type="button" class="button pq-copy-shortcode" data-shortcode="<?php echo esc_attr($sc); ?>"><?php _e('Copy', 'personality-quiz'); ?></button>
                    <span class="pq-copy-feedback"><?php _e('Copied!', 'personality-quiz'); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function render_results_box($post) {
        $results = get_post_meta($post->ID, '_quiz_results', true);
        if (empty($results)) $results = [['name'=>'','slug'=>'','priority'=>1,'description'=>'','image_id'=>0]];
        ?>
        <div class="pq-results-wrap">
            <p class="pq-section-intro"><?php _e('Define possible outcomes. Lower priority wins ties.', 'personality-quiz'); ?></p>
            <div id="pq-results-container">
                <?php foreach ($results as $i => $r) $this->render_result_row($i, $r); ?>
            </div>
            <button type="button" class="button pq-add-result"><?php _e('+ Add Result', 'personality-quiz'); ?></button>
            <script type="text/html" id="pq-result-template"><?php $this->render_result_row('{{INDEX}}', ['name'=>'','slug'=>'','priority'=>1,'description'=>'','image_id'=>0]); ?></script>
        </div>
        <?php
    }
    
    private function render_result_row($i, $r) {
        $img = !empty($r['image_id']) ? wp_get_attachment_image_url($r['image_id'], 'thumbnail') : '';
        $title = $r['name'] ?: __('New Result', 'personality-quiz');
        $collapsed = $r['name'] ? 'collapsed' : '';
        ?>
        <div class="pq-result-row <?php echo $collapsed; ?>" data-index="<?php echo esc_attr($i); ?>">
            <div class="pq-row-header">
                <span class="pq-row-title"><?php echo esc_html($title); ?></span>
                <div class="pq-row-actions">
                    <button type="button" class="pq-toggle button-link"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
                    <button type="button" class="pq-remove-result button-link"><span class="dashicons dashicons-trash"></span></button>
                </div>
            </div>
            <div class="pq-row-content">
                <div class="pq-field"><label><?php _e('Name', 'personality-quiz'); ?> <span class="required">*</span></label>
                    <input type="text" name="pq_results[<?php echo esc_attr($i); ?>][name]" value="<?php echo esc_attr($r['name']); ?>" class="regular-text pq-result-name" maxlength="100"></div>
                <div class="pq-field pq-field-small"><label><?php _e('Priority', 'personality-quiz'); ?></label>
                    <input type="number" name="pq_results[<?php echo esc_attr($i); ?>][priority]" value="<?php echo esc_attr($r['priority']); ?>" class="small-text" min="1" max="100"></div>
                <div class="pq-field"><label><?php _e('Description', 'personality-quiz'); ?></label>
                    <textarea name="pq_results[<?php echo esc_attr($i); ?>][description]" rows="3" class="large-text"><?php echo esc_textarea($r['description']); ?></textarea></div>
                <div class="pq-field"><label><?php _e('Image', 'personality-quiz'); ?> <span class="required">*</span></label>
                    <div class="pq-image-field">
                        <input type="hidden" name="pq_results[<?php echo esc_attr($i); ?>][image_id]" value="<?php echo esc_attr($r['image_id']); ?>" class="pq-image-id">
                        <div class="pq-image-preview"><?php if ($img): ?><img src="<?php echo esc_url($img); ?>" alt=""><?php endif; ?></div>
                        <button type="button" class="button pq-select-image"><?php _e('Select Image', 'personality-quiz'); ?></button>
                        <button type="button" class="button pq-remove-image" <?php if (!$img) echo 'style="display:none;"'; ?>><?php _e('Remove', 'personality-quiz'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_questions_box($post) {
        $questions = get_post_meta($post->ID, '_quiz_questions', true);
        $results = get_post_meta($post->ID, '_quiz_results', true);
        if (empty($questions)) $questions = [['text'=>'','image_id'=>0,'answers'=>[['text'=>'','result_slug'=>''],['text'=>'','result_slug'=>'']]]];
        ?>
        <div class="pq-questions-wrap">
            <p class="pq-section-intro"><?php _e('Each question needs text, an image, and at least 2 answers.', 'personality-quiz'); ?></p>
            <div id="pq-questions-container">
                <?php foreach ($questions as $qi => $q) $this->render_question_row($qi, $q, $results); ?>
            </div>
            <button type="button" class="button pq-add-question"><?php _e('+ Add Question', 'personality-quiz'); ?></button>
            <script type="text/html" id="pq-question-template"><?php $this->render_question_row('{{Q_INDEX}}', ['text'=>'','image_id'=>0,'answers'=>[['text'=>'','result_slug'=>''],['text'=>'','result_slug'=>'']]], $results); ?></script>
            <script type="text/html" id="pq-answer-template"><?php $this->render_answer_row('{{Q_INDEX}}', '{{A_INDEX}}', ['text'=>'','result_slug'=>''], $results); ?></script>
        </div>
        <?php
    }
    
    private function render_question_row($qi, $q, $results) {
        $img = !empty($q['image_id']) ? wp_get_attachment_image_url($q['image_id'], 'thumbnail') : '';
        $title = $q['text'] ? wp_trim_words($q['text'], 10, '...') : __('New Question', 'personality-quiz');
        $num = is_numeric($qi) ? ($qi + 1) : '#';
        $collapsed = $q['text'] ? 'collapsed' : '';
        ?>
        <div class="pq-question-row <?php echo $collapsed; ?>" data-index="<?php echo esc_attr($qi); ?>">
            <div class="pq-row-header">
                <span class="pq-row-number"><?php echo esc_html($num); ?>.</span>
                <span class="pq-row-title pq-question-title"><?php echo esc_html($title); ?></span>
                <div class="pq-row-actions">
                    <button type="button" class="pq-toggle button-link"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
                    <button type="button" class="pq-remove-question button-link"><span class="dashicons dashicons-trash"></span></button>
                </div>
            </div>
            <div class="pq-row-content">
                <div class="pq-field"><label><?php _e('Question Text', 'personality-quiz'); ?> <span class="required">*</span></label>
                    <textarea name="pq_questions[<?php echo esc_attr($qi); ?>][text]" rows="2" class="large-text pq-question-text" maxlength="500"><?php echo esc_textarea($q['text']); ?></textarea></div>
                <div class="pq-field"><label><?php _e('Image', 'personality-quiz'); ?> <span class="required">*</span></label>
                    <div class="pq-image-field">
                        <input type="hidden" name="pq_questions[<?php echo esc_attr($qi); ?>][image_id]" value="<?php echo esc_attr($q['image_id']); ?>" class="pq-image-id">
                        <div class="pq-image-preview"><?php if ($img): ?><img src="<?php echo esc_url($img); ?>" alt=""><?php endif; ?></div>
                        <button type="button" class="button pq-select-image"><?php _e('Select Image', 'personality-quiz'); ?></button>
                        <button type="button" class="button pq-remove-image" <?php if (!$img) echo 'style="display:none;"'; ?>><?php _e('Remove', 'personality-quiz'); ?></button>
                    </div>
                </div>
                <div class="pq-field pq-answers-field"><label><?php _e('Answers', 'personality-quiz'); ?> <span class="required">*</span></label>
                    <div class="pq-answers-list"><?php if (!empty($q['answers'])) foreach ($q['answers'] as $ai => $a) $this->render_answer_row($qi, $ai, $a, $results); ?></div>
                    <button type="button" class="button button-small pq-add-answer"><?php _e('+ Add Answer', 'personality-quiz'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_answer_row($qi, $ai, $a, $results) {
        ?>
        <div class="pq-answer-row" data-index="<?php echo esc_attr($ai); ?>">
            <input type="text" name="pq_questions[<?php echo esc_attr($qi); ?>][answers][<?php echo esc_attr($ai); ?>][text]" value="<?php echo esc_attr($a['text']); ?>" placeholder="<?php esc_attr_e('Answer text', 'personality-quiz'); ?>" class="pq-answer-text" maxlength="200">
            <select name="pq_questions[<?php echo esc_attr($qi); ?>][answers][<?php echo esc_attr($ai); ?>][result_slug]" class="pq-result-select">
                <option value=""><?php _e('— Result —', 'personality-quiz'); ?></option>
                <?php if ($results): foreach ($results as $r): if ($r['name']): $slug = $r['slug'] ?: sanitize_title($r['name']); ?>
                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($a['result_slug'], $slug); ?>><?php echo esc_html($r['name']); ?></option>
                <?php endif; endforeach; endif; ?>
            </select>
            <button type="button" class="pq-remove-answer button-link"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <?php
    }
    
    // =========================================================================
    // SAVE
    // =========================================================================
    
    public function save_quiz($post_id, $post) {
        if (!isset($_POST['pq_nonce']) || !wp_verify_nonce($_POST['pq_nonce'], 'pq_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $errors = [];
        $per_page = isset($_POST['pq_per_page']) ? max(1, min(20, absint($_POST['pq_per_page']))) : 5;
        update_post_meta($post_id, '_quiz_questions_per_page', $per_page);
        
        // Results
        $results = [];
        $used_slugs = [];
        if (isset($_POST['pq_results']) && is_array($_POST['pq_results'])) {
            foreach ($_POST['pq_results'] as $r) {
                $name = sanitize_text_field($r['name'] ?? '');
                if (!$name) continue;
                $slug = sanitize_title($name);
                $base = $slug; $c = 2;
                while (in_array($slug, $used_slugs)) $slug = $base . '-' . $c++;
                $used_slugs[] = $slug;
                $image_id = absint($r['image_id'] ?? 0);
                if (!$image_id) $errors[] = sprintf(__('Result "%s" needs an image.', 'personality-quiz'), $name);
                $results[] = [
                    'name' => $name,
                    'slug' => $slug,
                    'priority' => max(1, min(100, absint($r['priority'] ?? 1))),
                    'description' => wp_kses_post($r['description'] ?? ''),
                    'image_id' => $image_id,
                ];
            }
        }
        if (!$results) $errors[] = __('Add at least one result.', 'personality-quiz');
        update_post_meta($post_id, '_quiz_results', $results);
        
        // Questions
        $valid_slugs = array_column($results, 'slug');
        $questions = [];
        $qn = 0;
        if (isset($_POST['pq_questions']) && is_array($_POST['pq_questions'])) {
            foreach ($_POST['pq_questions'] as $q) {
                $qn++;
                $text = sanitize_textarea_field($q['text'] ?? '');
                if (!$text) continue;
                $image_id = absint($q['image_id'] ?? 0);
                if (!$image_id) $errors[] = sprintf(__('Question %d needs an image.', 'personality-quiz'), $qn);
                $answers = [];
                if (isset($q['answers']) && is_array($q['answers'])) {
                    foreach ($q['answers'] as $a) {
                        $atext = sanitize_text_field($a['text'] ?? '');
                        if (!$atext) continue;
                        $aslug = sanitize_title($a['result_slug'] ?? '');
                        if ($aslug && !in_array($aslug, $valid_slugs)) $aslug = '';
                        $answers[] = ['text' => $atext, 'result_slug' => $aslug];
                    }
                }
                if (count($answers) < 2) $errors[] = sprintf(__('Question %d needs at least 2 answers.', 'personality-quiz'), $qn);
                $questions[] = ['text' => $text, 'image_id' => $image_id, 'answers' => $answers];
            }
        }
        if (!$questions) $errors[] = __('Add at least one question.', 'personality-quiz');
        update_post_meta($post_id, '_quiz_questions', $questions);
        
        if ($errors) set_transient('pq_errors_' . $post_id, $errors, 60);
        else delete_transient('pq_errors_' . $post_id);
    }
    
    public function display_admin_notices() {
        global $post;
        if (!$post || $post->post_type !== 'personality_quiz') return;
        $errors = get_transient('pq_errors_' . $post->ID);
        if ($errors) {
            foreach ($errors as $e) echo '<div class="notice notice-error"><p>' . esc_html($e) . '</p></div>';
            delete_transient('pq_errors_' . $post->ID);
        }
    }
    
    // =========================================================================
    // FRONTEND: QUIZ SHORTCODE (with inline results)
    // =========================================================================
    
    public function render_quiz($atts) {
        $atts = shortcode_atts(['id' => 0], $atts, 'personality_quiz');
        $quiz_id = absint($atts['id']);
        
        if (!$quiz_id) return '<div class="pq-error"><p>' . __('Quiz ID required.', 'personality-quiz') . '</p></div>';
        
        $quiz = get_post($quiz_id);
        if (!$quiz || $quiz->post_type !== 'personality_quiz' || $quiz->post_status !== 'publish') {
            return '<div class="pq-error"><p>' . __('Quiz not found.', 'personality-quiz') . '</p></div>';
        }
        
        $questions = get_post_meta($quiz_id, '_quiz_questions', true);
        $results = get_post_meta($quiz_id, '_quiz_results', true);
        $per_page = get_post_meta($quiz_id, '_quiz_questions_per_page', true) ?: 5;
        
        if (empty($questions)) return '<div class="pq-error"><p>' . __('This quiz has no questions.', 'personality-quiz') . '</p></div>';
        if (empty($results)) return '<div class="pq-error"><p>' . __('This quiz has no results configured.', 'personality-quiz') . '</p></div>';
        
        // Build results data for JS (inline display - no separate page needed)
        $results_json = [];
        foreach ($results as $r) {
            $img = !empty($r['image_id']) ? wp_get_attachment_image_url($r['image_id'], 'large') : '';
            $results_json[$r['slug']] = [
                'name' => $r['name'],
                'description' => $r['description'],
                'image' => $img,
                'priority' => $r['priority'],
            ];
        }
        
        ob_start();
        ?>
        <div class="pq-quiz" 
             data-quiz-id="<?php echo esc_attr($quiz_id); ?>" 
             data-per-page="<?php echo esc_attr($per_page); ?>" 
             data-total="<?php echo count($questions); ?>"
             data-results="<?php echo esc_attr(json_encode($results_json, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)); ?>">
            
            <div class="pq-progress" aria-live="polite"><span class="pq-progress-text"></span></div>
            
            <div class="pq-questions">
                <?php foreach ($questions as $i => $q): 
                    $img = !empty($q['image_id']) ? wp_get_attachment_image_url($q['image_id'], 'large') : '';
                    $alt = !empty($q['image_id']) ? get_post_meta($q['image_id'], '_wp_attachment_image_alt', true) : '';
                ?>
                <div class="pq-question" data-question="<?php echo esc_attr($i); ?>">
                    <?php if ($img): ?><div class="pq-question-image"><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($alt); ?>" loading="lazy"></div><?php endif; ?>
                    <div class="pq-question-content">
                        <h5 class="pq-question-text"><?php echo esc_html($q['text']); ?></h5>
                        <div class="pq-answers" role="group" aria-label="<?php esc_attr_e('Answer choices', 'personality-quiz'); ?>">
                            <?php foreach ($q['answers'] as $ai => $a): ?>
                            <button type="button" class="pq-answer" data-answer="<?php echo esc_attr($ai); ?>" data-result="<?php echo esc_attr($a['result_slug']); ?>"><?php echo esc_html($a['text']); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="pq-validation" role="alert"></div>
            
            <div class="pq-navigation">
                <button type="button" class="pq-nav-btn pq-prev-btn" style="display:none;"><?php _e('Previous', 'personality-quiz'); ?></button>
                <div class="pq-nav-spacer"></div>
                <button type="button" class="pq-nav-btn pq-next-btn"><?php _e('Next', 'personality-quiz'); ?></button>
            </div>
            
            <!-- Result displays inline here -->
            <div class="pq-result" style="display:none;">
                <div class="pq-result-image"></div>
                <div class="pq-result-content">
                    <h2 class="pq-result-title"></h2>
                    <div class="pq-result-description"></div>
                    <div class="pq-share">
                        <button type="button" class="pq-share-btn pq-copy-link">
                            <span class="pq-copy-icon" aria-hidden="true">📋</span>
                            <?php _e('Share Results', 'personality-quiz'); ?>
                        </button>
                        <span class="pq-copy-success"><?php _e('Copied!', 'personality-quiz'); ?></span>
                    </div>
                    <div class="pq-retake">
                        <button type="button" class="pq-retake-btn"><?php _e('Take this Quiz', 'personality-quiz'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // =========================================================================
    // ASSETS
    // =========================================================================
    
    public function admin_assets($hook) {
        global $post_type;
        if ($post_type !== 'personality_quiz') return;
        
        wp_enqueue_media();
        wp_enqueue_style('pq-styles', plugin_dir_url(__FILE__) . 'pq-styles.css', [], '1.0.1');
        wp_enqueue_script('pq-scripts', plugin_dir_url(__FILE__) . 'pq-scripts.js', ['jquery'], '1.0.0', true);
        wp_localize_script('pq-scripts', 'pqAdmin', [
            'confirmResult' => __('Remove this result?', 'personality-quiz'),
            'confirmQuestion' => __('Remove this question?', 'personality-quiz'),
            'mediaTitle' => __('Select Image', 'personality-quiz'),
            'mediaButton' => __('Use Image', 'personality-quiz'),
        ]);
    }
    
    public function frontend_assets() {
        if (!is_singular() && !has_shortcode(get_post()->post_content ?? '', 'personality_quiz')) return;
        wp_enqueue_style('pq-styles', plugin_dir_url(__FILE__) . 'pq-styles.css', [], '1.0.1');
        wp_enqueue_script('pq-scripts', plugin_dir_url(__FILE__) . 'pq-scripts.js', [], '1.0.0', true);
    }
}

new Personality_Quiz();
