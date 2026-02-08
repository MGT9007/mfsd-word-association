<?php
/**
 * Plugin Name: MFSD Word Association
 * Description: Rapid word association game with AI-powered insights
 * Version: 1.5.13
 * Author: MisterT9007
 */

if (!defined('ABSPATH')) exit;

final class MFSD_Word_Association {
    const VERSION = '1.5.13';
    const NONCE_ACTION = 'mfsd_word_assoc_nonce';
    
    const TBL_CARDS = 'mfsd_flashcards_cards';
    const TBL_ASSOCIATIONS = 'mfsd_word_associations';
    
    public static function instance() {
        static $i = null;
        return $i ?: $i = new self();
    }
    
    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'install'));
        add_action('init', array($this, 'assets'));
        add_shortcode('mfsd_word_association', array($this, 'shortcode'));
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('admin_menu', array($this, 'admin_menu'));
    }
    
    public function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        $cards = $wpdb->prefix . self::TBL_CARDS;
        $assoc = $wpdb->prefix . self::TBL_ASSOCIATIONS;
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Word cards table (reusing your flashcards structure)
        dbDelta("CREATE TABLE $cards (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            word VARCHAR(100) NOT NULL,
            category VARCHAR(100) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (active),
            KEY idx_category (category)
        ) $charset;");
        
        // Associations table
        dbDelta("CREATE TABLE $assoc (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            card_id BIGINT UNSIGNED NOT NULL,
            word VARCHAR(100) NOT NULL,
            association_1 TEXT NOT NULL,
            association_2 TEXT NOT NULL,
            association_3 TEXT NOT NULL,
            time_taken INT NOT NULL DEFAULT 0,
            ai_summary TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_card (card_id),
            KEY idx_created (created_at)
        ) $charset;");
        
        // Insert sample words if table is empty
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $cards");
        if ($count == 0) {
            $this->insert_sample_words();
        }
    }
    
    private function insert_sample_words() {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_CARDS;
        
        $words = array(
            // Growth & Development
            array('Success', 'Growth & Development'),
            array('Failure', 'Growth & Development'),
            array('Challenge', 'Growth & Development'),
            array('Growth', 'Growth & Development'),
            array('Learning', 'Growth & Development'),
            array('Practice', 'Growth & Development'),
            
            // Emotions & Feelings
            array('Fear', 'Emotions & Feelings'),
            array('Joy', 'Emotions & Feelings'),
            array('Anxiety', 'Emotions & Feelings'),
            array('Confidence', 'Emotions & Feelings'),
            array('Hope', 'Emotions & Feelings'),
            array('Pride', 'Emotions & Feelings'),
            
            // Relationships
            array('Friendship', 'Relationships'),
            array('Trust', 'Relationships'),
            array('Communication', 'Relationships'),
            array('Teamwork', 'Relationships'),
            array('Support', 'Relationships'),
            array('Conflict', 'Relationships'),
            
            // Future & Goals
            array('Dream', 'Future & Goals'),
            array('Goal', 'Future & Goals'),
            array('Career', 'Future & Goals'),
            array('Ambition', 'Future & Goals'),
            array('Plan', 'Future & Goals'),
            array('Vision', 'Future & Goals'),
            
            // Self & Identity
            array('Identity', 'Self & Identity'),
            array('Strength', 'Self & Identity'),
            array('Weakness', 'Self & Identity'),
            array('Talent', 'Self & Identity'),
            array('Passion', 'Self & Identity'),
            array('Value', 'Self & Identity'),
        );
        
        foreach ($words as $word) {
            $wpdb->insert($table, array(
                'word' => $word[0],
                'category' => $word[1],
                'active' => 1
            ));
        }
    }
    
    public function assets() {
        $ver = self::VERSION;
        $url = plugin_dir_url(__FILE__);
        $path = plugin_dir_path(__FILE__);
        
        // Debug: Check if files exist
        $css_path = $path . 'assets/mfsd-word-association.css';
        $js_path = $path . 'assets/mfsd-word-association.js';
        
        if (current_user_can('manage_options') && isset($_GET['debug_wa'])) {
            echo '<div style="background: #fff; border: 2px solid red; padding: 20px; margin: 20px;">';
            echo '<h3>Word Association Debug Info</h3>';
            echo '<p><strong>Plugin URL:</strong> ' . $url . '</p>';
            echo '<p><strong>Plugin Path:</strong> ' . $path . '</p>';
            echo '<p><strong>CSS exists:</strong> ' . (file_exists($css_path) ? 'YES ✓' : 'NO ✗') . '</p>';
            echo '<p><strong>CSS path:</strong> ' . $css_path . '</p>';
            echo '<p><strong>JS exists:</strong> ' . (file_exists($js_path) ? 'YES ✓' : 'NO ✗') . '</p>';
            echo '<p><strong>JS path:</strong> ' . $js_path . '</p>';
            echo '<p><strong>Assets folder exists:</strong> ' . (is_dir($path . 'assets') ? 'YES ✓' : 'NO ✗') . '</p>';
            if (is_dir($path . 'assets')) {
                echo '<p><strong>Assets folder contents:</strong></p><pre>';
                print_r(scandir($path . 'assets'));
                echo '</pre>';
            }
            echo '</div>';
        }
        
        wp_register_style('mfsd-word-assoc-css', $url . 'assets/mfsd-word-association.css', array(), $ver);
        wp_register_script('mfsd-word-assoc-js', $url . 'assets/mfsd-word-association.js', array(), $ver, true);
    }
    
    public function shortcode($atts) {
        $atts = shortcode_atts(array(
            'category' => '',
            'timer' => 20
        ), $atts);
        
        if (!is_user_logged_in()) {
            return '<p class="wa-error">Please log in to play word association.</p>';
        }
        
        wp_enqueue_style('mfsd-word-assoc-css');
        wp_enqueue_script('mfsd-word-assoc-js');
        
        $user_id = get_current_user_id();
        $rest_url = rest_url('mfsd-word-assoc/v1/');
        $nonce = wp_create_nonce('wp_rest');
        
        wp_localize_script('mfsd-word-assoc-js', 'MFSD_WA_CFG', array(
            'userId' => $user_id,
            'restUrl' => $rest_url,
            'nonce' => $nonce,
            'category' => $atts['category'],
            'timer' => intval($atts['timer']),
            'mode' => get_option('mfsd_wa_mode', 1),
            'wordCount' => get_option('mfsd_wa_word_count', 1)
        ));
        
        return '<div id="mfsd-word-assoc-root"></div>';
    }
    
    public function register_routes() {
        $ns = 'mfsd-word-assoc/v1';
        
        // Get random word
        register_rest_route($ns, '/word', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_word'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Save associations and get AI summary
        register_rest_route($ns, '/save', array(
            'methods' => 'POST',
            'callback' => array($this, 'api_save_associations'),
            'permission_callback' => array($this, 'check_permission')
        ));
        
        // Get user history
        register_rest_route($ns, '/history', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_history'),
            'permission_callback' => array($this, 'check_permission')
        ));
    }
    
    public function check_permission() {
        return is_user_logged_in();
    }
    
    public function api_get_word($req) {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_CARDS;
        $user_id = get_current_user_id();
        
        // Get mode settings
        $mode = get_option('mfsd_wa_mode', 1);
        $selected_words = get_option('mfsd_wa_selected_words', array());
        
        if ($mode == 2 && !empty($selected_words)) {
            // Mode 2: Return next word from selected list that user hasn't completed
            $assoc_table = $wpdb->prefix . self::TBL_ASSOCIATIONS;
            
            // Get words user has already completed
            $completed_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT card_id FROM $assoc_table WHERE user_id = %d",
                $user_id
            ));
            
            // Find first word in selected list that hasn't been completed
            $remaining = array_diff($selected_words, $completed_ids);
            
            if (empty($remaining)) {
                return new WP_Error('all_complete', 'All words completed', array('status' => 404));
            }
            
            // Get the next word from remaining
            $next_id = reset($remaining);
            $word = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $next_id
            ));
            
        } else {
            // Mode 1: Random selection from active words
            $category = sanitize_text_field($req->get_param('category'));
            
            $where = "active = 1";
            if (!empty($category)) {
                $where .= $wpdb->prepare(" AND category = %s", $category);
            }
            
            $word = $wpdb->get_row(
                "SELECT * FROM $table WHERE $where ORDER BY RAND() LIMIT 1"
            );
            
            $completed_ids = array(); // Not used in Mode 1
        }
        
        if (!$word) {
            return new WP_Error('no_words', 'No words available', array('status' => 404));
        }
        
        // Include mode info in response
        return rest_ensure_response(array(
            'success' => true,
            'word' => $word,
            'mode' => $mode,
            'total_words' => $mode == 2 ? count($selected_words) : null,
            'completed' => $mode == 2 ? count($completed_ids) : null
        ));
    }
    
    public function api_save_associations($req) {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_ASSOCIATIONS;
        
        $user_id = get_current_user_id();
        $card_id = intval($req->get_param('card_id'));
        $word = sanitize_text_field($req->get_param('word'));
        $assoc1 = sanitize_textarea_field($req->get_param('association_1'));
        $assoc2 = sanitize_textarea_field($req->get_param('association_2'));
        $assoc3 = sanitize_textarea_field($req->get_param('association_3'));
        $time_taken = intval($req->get_param('time_taken'));
       
        
        // Generate AI summary
        $ai_summary = $this->generate_ai_summary($word, $assoc1, $assoc2, $assoc3, $user_id);
        
        // Save to database
        $wpdb->insert($table, array(
            'user_id' => $user_id,
            'card_id' => $card_id,
            'word' => $word,
            'association_1' => $assoc1,
            'association_2' => $assoc2,
            'association_3' => $assoc3,
            'time_taken' => $time_taken,
            'ai_summary' => $ai_summary,
            'created_at' => current_time('mysql')
        ));
        
        // Get mode settings and progress
        $mode = get_option('mfsd_wa_mode', 1);
        $selected_words = get_option('mfsd_wa_selected_words', array());
        
        // DEBUG
        error_log('SAVE API DEBUG - mode: ' . $mode . ', selected_words: ' . json_encode($selected_words) . ', count: ' . count($selected_words));
        
        // Calculate completed count
        $completed_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT card_id FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        return rest_ensure_response(array(
            'success' => true,
            'summary' => $ai_summary,
            'mode' => $mode,
            'total_words' => $mode == 2 ? count($selected_words) : null,
            'completed' => $mode == 2 ? count($completed_ids) : null
        ));
    }
    
    private function generate_ai_summary($word, $assoc1, $assoc2, $assoc3, $user_id) {
        // Use AI Engine (same as RAG plugin)
        if (!isset($GLOBALS['mwai'])) {
            return "AI Engine not available. Please install and configure the AI Engine plugin.";
        }
        
        try {
            $mwai = $GLOBALS['mwai'];
            
            $username = um_get_display_name($user_id);

            $prompt = "You are a warm, supportive coach speaking directly to {$username}, a student aged 11-14.\n\n";
            
            $prompt .= "{$username} saw the word \"{$word}\" and gave these associations:\n";
            $prompt .= "1. {$assoc1}\n";
            $prompt .= "2. {$assoc2}\n";
            $prompt .= "3. {$assoc3}\n\n";
            
            $prompt .= "Write a summary with this EXACT structure (use double line breaks between each section):\n\n";
            
            $prompt .= "SECTION 1 (Introduction - 1-2 sentences):\n";
            $prompt .= "Briefly welcome {$username} and mention you'll explore their associations.\n\n";
            
            $prompt .= "SECTION 2 (Association 1 - 2-3 sentences):\n";
            $prompt .= "Start with: **\"{$word} and {$assoc1}\":**\n";
            $prompt .= "Explore what this connection reveals about {$username}'s perspective.\n\n";
            
            $prompt .= "SECTION 3 (Association 2 - 2-3 sentences):\n";
            $prompt .= "Start with: **\"{$word} and {$assoc2}\":**\n";
            $prompt .= "Explore what this connection reveals.\n\n";
            
            $prompt .= "SECTION 4 (Association 3 - 2-3 sentences):\n";
            $prompt .= "Start with: **\"{$word} and {$assoc3}\":**\n";
            $prompt .= "Explore what this connection reveals.\n\n";
            
            $prompt .= "SECTION 5 (Conclusion - 3-4 sentences):\n";
            $prompt .= "Tie all three together showing the bigger picture of how {$username} relates to \"{$word}\".\n\n";
            
            $prompt .= "CRITICAL FORMATTING RULES:\n";
            $prompt .= "- Use TWO line breaks (\\n\\n) between each section\n";
            $prompt .= "- Bold the association headers like: **\"Communication and excellent\":**\n";
            $prompt .= "- Use 'you' and 'your' - speak directly to {$username}\n";
            $prompt .= "- Keep each section short (mobile-friendly)\n";
            $prompt .= "- Age-appropriate language for 11-14 year olds\n";
            $prompt .= "- UK spelling and context\n";
            $prompt .= "- Be warm, encouraging, and growth-focused\n\n";
            
            $prompt .= "Start writing now:";

            // Actually call the AI
            $summary = $mwai->simpleTextQuery($prompt);
            
            // Post-process to ensure proper formatting
            $summary = $this->format_ai_summary($summary, $word, $assoc1, $assoc2, $assoc3);

            return $summary;
            
        } catch (Exception $e) {
            error_log('MFSD Word Association: AI summary error: ' . $e->getMessage());
            return "I'm having trouble generating insights right now. Your associations have been saved!";
        }
    }
    
    private function format_ai_summary($summary, $word, $assoc1, $assoc2, $assoc3) {
        // Clean up AI response to ensure proper formatting
        
        // Remove any ### headers if present
        $summary = preg_replace('/^###\s+.*$/m', '', $summary);
        
        // Ensure bold markers use ** not just *
        $summary = preg_replace('/\*([^*]+)\*/', '**$1**', $summary);
        
        // Try to identify sections and add line breaks
        // Look for patterns like "Communication and excellent" or bold headers
        $patterns = [
            "/(\*\*\"$word and [^\"]+\"\*\*:)/i",
            "/(\*\*$word and [^:]+:\*\*)/i",
            "/(When you associate)/i",
            "/(Linking \"$word\")/i",
            "/(Bringing all)/i",
            "/(In conclusion)/i"
        ];
        
        foreach ($patterns as $pattern) {
            $summary = preg_replace($pattern, "\n\n$1", $summary);
        }
        
        // Ensure association headers are bold if they're not already
        $summary = preg_replace(
            "/\"$word and ($assoc1|$assoc2|$assoc3)\":/i",
            "**\"$word and $1\":**",
            $summary
        );
        
        // Clean up excessive line breaks (more than 2 in a row)
        $summary = preg_replace("/\n{3,}/", "\n\n", $summary);
        
        // Trim whitespace
        $summary = trim($summary);
        
        return $summary;
    }
    
    public function api_get_history($req) {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_ASSOCIATIONS;
        
        $user_id = get_current_user_id();
        $limit = intval($req->get_param('limit')) ?: 10;
        
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
        
        // Get mode settings and progress
        $mode = get_option('mfsd_wa_mode', 1);
        $selected_words = get_option('mfsd_wa_selected_words', array());
        
        // Get total completed count (distinct words)
        $completed_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT card_id) FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        return rest_ensure_response(array(
            'success' => true,
            'history' => $history,
            'mode' => $mode,
            'total_words' => $mode == 2 ? count($selected_words) : null,
            'completed' => $mode == 2 ? intval($completed_count) : null
        ));
    }

    public function admin_menu() {
        add_menu_page(
            'Word Association',
            'Word Association',
            'manage_options',
            'mfsd-word-assoc',
            array($this, 'admin_page'),
            'dashicons-editor-quote',
            30
        );
    }
    
    public function admin_page() {
        global $wpdb;
        $cards_table = $wpdb->prefix . self::TBL_CARDS;
        
        // Handle mode settings save
        if (isset($_POST['action']) && $_POST['action'] === 'save_mode_settings' && 
            check_admin_referer('mfsd_word_assoc_mode_settings')) {
            
            $mode = intval($_POST['mode']); // 1 or 2
            $word_count = intval($_POST['word_count']); // 1-5
            $selected_words = isset($_POST['selected_words']) ? array_map('intval', $_POST['selected_words']) : array();
            
            update_option('mfsd_wa_mode', $mode);
            update_option('mfsd_wa_word_count', $word_count);
            update_option('mfsd_wa_selected_words', $selected_words);
            
            echo '<div class="notice notice-success"><p>Mode settings saved successfully!</p></div>';
        }
        
        // Handle add word
        if (isset($_POST['action']) && $_POST['action'] === 'add_word' && 
            check_admin_referer('mfsd_word_assoc_add_word')) {
            
            $wpdb->insert($cards_table, array(
                'word' => sanitize_text_field($_POST['word']),
                'category' => sanitize_text_field($_POST['category']),
                'active' => 1
            ));
            
            echo '<div class="notice notice-success"><p>Word added successfully!</p></div>';
        }
        
        // Handle toggle active/inactive
        if (isset($_GET['toggle']) && check_admin_referer('mfsd_word_assoc_toggle_' . $_GET['toggle'])) {
            $word_id = intval($_GET['toggle']);
            $current = $wpdb->get_var($wpdb->prepare(
                "SELECT active FROM $cards_table WHERE id = %d",
                $word_id
            ));
            $new_status = $current ? 0 : 1;
            $wpdb->update(
                $cards_table,
                array('active' => $new_status),
                array('id' => $word_id)
            );
            $status_text = $new_status ? 'activated' : 'deactivated';
            echo '<div class="notice notice-success"><p>Word ' . $status_text . ' successfully!</p></div>';
        }

        // Handle delete
        if (isset($_GET['delete']) && check_admin_referer('mfsd_word_assoc_delete_' . $_GET['delete'])) {
            $wpdb->delete($cards_table, array('id' => intval($_GET['delete'])));
            echo '<div class="notice notice-success"><p>Word deleted!</p></div>';
        }
        
        // Get all words
        $words = $wpdb->get_results("SELECT * FROM $cards_table ORDER BY category, word");
        $categories = $wpdb->get_col("SELECT DISTINCT category FROM $cards_table WHERE category IS NOT NULL ORDER BY category");
        
        ?>
        <div class="wrap">
            <h1>Manage Word Association Cards</h1>
            
            <?php
            // Get current settings
            $current_mode = get_option('mfsd_wa_mode', 1);
            $current_word_count = get_option('mfsd_wa_word_count', 1);
            $current_selected_words = get_option('mfsd_wa_selected_words', array());
            ?>
            
            <h2>Mode Settings</h2>
            <form method="post" action="" id="mode-settings-form">
                <?php wp_nonce_field('mfsd_word_assoc_mode_settings'); ?>
                <input type="hidden" name="action" value="save_mode_settings">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Mode</th>
                        <td>
                            <label>
                                <input type="radio" name="mode" value="1" <?php checked($current_mode, 1); ?> 
                                       onchange="toggleModeSettings()">
                                <strong>Mode 1:</strong> Random Words (Unlimited)
                            </label>
                            <p class="description">Users can do unlimited word associations with randomly selected active words.</p>
                            
                            <br><br>
                            
                            <label>
                                <input type="radio" name="mode" value="2" <?php checked($current_mode, 2); ?>
                                       onchange="toggleModeSettings()">
                                <strong>Mode 2:</strong> Fixed Word Set (Limited)
                            </label>
                            <p class="description">Users complete a specific set of words (1-5) that you choose.</p>
                        </td>
                    </tr>
                    
                    <tr id="word-count-row" style="display: <?php echo $current_mode == 2 ? 'table-row' : 'none'; ?>;">
                        <th scope="row"><label for="word_count">Number of Words</label></th>
                        <td>
                            <select name="word_count" id="word_count" onchange="updateWordPicker()">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($current_word_count, $i); ?>>
                                        <?php echo $i; ?> Word<?php echo $i > 1 ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="description">Select how many words users must complete in Mode 2.</p>
                        </td>
                    </tr>
                    
                    <tr id="word-picker-row" style="display: <?php echo $current_mode == 2 ? 'table-row' : 'none'; ?>;">
                        <th scope="row">Select Words</th>
                        <td>
                            <p class="description" style="margin-top: 0;">Choose exactly <span id="required-count"><?php echo $current_word_count; ?></span> word(s):</p>
                            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                <?php foreach ($words as $word): ?>
                                    <?php if ($word->active): ?>
                                        <label style="display: block; margin: 5px 0;">
                                            <input type="checkbox" name="selected_words[]" value="<?php echo $word->id; ?>"
                                                   <?php echo in_array($word->id, $current_selected_words) ? 'checked' : ''; ?>
                                                   class="word-checkbox">
                                            <strong><?php echo esc_html($word->word); ?></strong>
                                            <?php if ($word->category): ?>
                                                <span style="color: #666;">(<?php echo esc_html($word->category); ?>)</span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <p class="description" id="selection-status"></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save Mode Settings" id="save-mode-btn">
                </p>
            </form>
            
            <script>
            function toggleModeSettings() {
                const mode = document.querySelector('input[name="mode"]:checked').value;
                const wordCountRow = document.getElementById('word-count-row');
                const wordPickerRow = document.getElementById('word-picker-row');
                
                if (mode == '2') {
                    wordCountRow.style.display = 'table-row';
                    wordPickerRow.style.display = 'table-row';
                    updateWordPicker();
                } else {
                    wordCountRow.style.display = 'none';
                    wordPickerRow.style.display = 'none';
                }
            }
            
            function updateWordPicker() {
                const requiredCount = document.getElementById('word_count').value;
                document.getElementById('required-count').textContent = requiredCount;
                updateSelectionStatus();
            }
            
            function updateSelectionStatus() {
                const checkboxes = document.querySelectorAll('.word-checkbox:checked');
                const required = parseInt(document.getElementById('word_count').value);
                const selected = checkboxes.length;
                const status = document.getElementById('selection-status');
                const saveBtn = document.getElementById('save-mode-btn');
                
                if (document.querySelector('input[name="mode"]:checked').value == '1') {
                    saveBtn.disabled = false;
                    status.textContent = '';
                    return;
                }
                
                if (selected < required) {
                    status.innerHTML = '<span style="color: #d63638;">⚠️ Please select ' + (required - selected) + ' more word(s)</span>';
                    saveBtn.disabled = true;
                } else if (selected > required) {
                    status.innerHTML = '<span style="color: #d63638;">⚠️ Too many selected! Please unselect ' + (selected - required) + ' word(s)</span>';
                    saveBtn.disabled = true;
                } else {
                    status.innerHTML = '<span style="color: #00a32a;">✓ Perfect! ' + selected + ' word(s) selected</span>';
                    saveBtn.disabled = false;
                }
            }
            
            // Add listeners to checkboxes
            document.querySelectorAll('.word-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSelectionStatus);
            });
            
            // Initialize on load
            updateSelectionStatus();
            </script>
            
            <hr style="margin: 30px 0;">
            
            <h2>Add New Word</h2>
            <form method="post" action="">
                <?php wp_nonce_field('mfsd_word_assoc_add_word'); ?>
                <input type="hidden" name="action" value="add_word">
                
                <table class="form-table">
                    <tr>
                        <th><label for="word">Word</label></th>
                        <td><input type="text" name="word" id="word" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="category">Category (optional)</label></th>
                        <td>
                            <input type="text" name="category" id="category" class="regular-text" list="existing-categories">
                            <datalist id="existing-categories">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Add Word">
                </p>
            </form>
            
            <hr>
            
            <h2>Existing Words</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Word</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($words as $word): ?>
                       <tr style="<?php echo !$word->active ? 'opacity: 0.5;' : ''; ?>">
                            <td><strong><?php echo esc_html($word->word); ?></strong></td>
                            <td><?php echo esc_html($word->category ?: '—'); ?></td>
                            <td>
                                <span style="<?php echo $word->active ? 'color: green;' : 'color: gray;'; ?>">
                                    <?php echo $word->active ? '✓ Active' : '✗ Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="?page=mfsd-word-assoc&toggle=<?php echo $word->id; ?>&_wpnonce=<?php echo wp_create_nonce('mfsd_word_assoc_toggle_' . $word->id); ?>" 
                                style="margin-right: 10px;">
                                    <?php echo $word->active ? 'Deactivate' : 'Activate'; ?>
                                </a>
                                |
                                <a href="?page=mfsd-word-assoc&delete=<?php echo $word->id; ?>&_wpnonce=<?php echo wp_create_nonce('mfsd_word_assoc_delete_' . $word->id); ?>" 
                                onclick="return confirm('Delete this word?')"
                                style="color: #a00; margin-left: 10px;">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
}

MFSD_Word_Association::instance();