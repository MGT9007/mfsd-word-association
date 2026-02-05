<?php
/**
 * Plugin Name: MFSD Word Association
 * Description: Rapid word association game with AI-powered insights
 * Version: 1.0.2
 * Author: MisterT9007
 */

if (!defined('ABSPATH')) exit;

final class MFSD_Word_Association {
    const VERSION = '1.0.2';
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
            'timer' => intval($atts['timer'])
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
        
        $category = sanitize_text_field($req->get_param('category'));
        
        $where = "active = 1";
        if (!empty($category)) {
            $where .= $wpdb->prepare(" AND category = %s", $category);
        }
        
        // Get random word
        $word = $wpdb->get_row(
            "SELECT * FROM $table WHERE $where ORDER BY RAND() LIMIT 1"
        );
        
        if (!$word) {
            return new WP_Error('no_words', 'No words available', array('status' => 404));
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'word' => $word
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
        $ai_summary = $this->generate_ai_summary($word, $assoc1, $assoc2, $assoc3);
        
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
        
        return rest_ensure_response(array(
            'success' => true,
            'summary' => $ai_summary
        ));
    }
    
    private function generate_ai_summary($word, $assoc1, $assoc2, $assoc3) {
        $anthropic_key = get_option('mfsd_anthropic_api_key');
        
        if (empty($anthropic_key)) {
            return "AI summary unavailable. Please configure your Anthropic API key in plugin settings.";
        }
        
        $prompt = "The user was shown the word \"{$word}\" and asked to quickly provide 3 word associations. They responded with:\n\n";
        $prompt .= "1. {$assoc1}\n";
        $prompt .= "2. {$assoc2}\n";
        $prompt .= "3. {$assoc3}\n\n";
        $prompt .= "Based on these associations, write a brief 2-3 sentence insight about what this word means to this person or how they relate to it. Be empathetic, thoughtful, and specific. Focus on the emotional or personal connection rather than dictionary definitions.";
        
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $anthropic_key,
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode(array(
                'model' => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 300,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                )
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return "AI summary generation failed. Please try again.";
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['content'][0]['text'])) {
            return $body['content'][0]['text'];
        }
        
        return "AI summary generation failed. Please check your API key.";
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
        
        return rest_ensure_response(array(
            'success' => true,
            'history' => $history
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
        
        add_submenu_page(
            'mfsd-word-assoc',
            'Settings',
            'Settings',
            'manage_options',
            'mfsd-word-assoc-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        global $wpdb;
        $cards_table = $wpdb->prefix . self::TBL_CARDS;
        
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
                        <tr>
                            <td><strong><?php echo esc_html($word->word); ?></strong></td>
                            <td><?php echo esc_html($word->category ?: '—'); ?></td>
                            <td><?php echo $word->active ? 'Active' : 'Inactive'; ?></td>
                            <td>
                                <a href="?page=mfsd-word-assoc&delete=<?php echo $word->id; ?>&_wpnonce=<?php echo wp_create_nonce('mfsd_word_assoc_delete_' . $word->id); ?>" 
                                   onclick="return confirm('Delete this word?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function settings_page() {
        if (isset($_POST['action']) && $_POST['action'] === 'save_settings' && 
            check_admin_referer('mfsd_word_assoc_settings')) {
            
            update_option('mfsd_anthropic_api_key', sanitize_text_field($_POST['anthropic_key']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $api_key = get_option('mfsd_anthropic_api_key', '');
        
        ?>
        <div class="wrap">
            <h1>Word Association Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('mfsd_word_assoc_settings'); ?>
                <input type="hidden" name="action" value="save_settings">
                
                <table class="form-table">
                    <tr>
                        <th><label for="anthropic_key">Anthropic API Key</label></th>
                        <td>
                            <input type="password" name="anthropic_key" id="anthropic_key" 
                                   value="<?php echo esc_attr($api_key); ?>" 
                                   class="regular-text">
                            <p class="description">
                                Required for AI-generated summaries. Get your key from 
                                <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        <?php
    }
}

MFSD_Word_Association::instance();