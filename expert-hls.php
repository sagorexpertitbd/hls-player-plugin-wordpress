<?php
/**
 * Plugin Name: Expert Clappr HLS Player
 * Description: A WordPress plugin for playing HLS streams using Clappr.io.
 * Version: 1.0
 * Author: Md Saiful Islam
 */

// Enqueue scripts
function clappr_hls_enqueue_scripts() {
    wp_enqueue_script('clappr', 'https://cdn.jsdelivr.net/npm/clappr@latest/dist/clappr.min.js', array(), 'latest', true);
    wp_enqueue_script('clipboard', 'https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js', array('jquery'), '2.0.8', true);
    wp_enqueue_script('clappr-hls-player', plugin_dir_url(__FILE__) . 'assets/js/main.js', array('jquery', 'clappr'), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'clappr_hls_enqueue_scripts');

// Admin menu
function clappr_hls_add_admin_menu() {
    add_menu_page('HLS Player Settings', 'HLS Player', 'manage_options', 'clappr-hls-player', 'clappr_hls_admin_page');
}
add_action('admin_menu', 'clappr_hls_add_admin_menu');

// Admin page
function clappr_hls_admin_page() {
    if (isset($_POST['add_channel'])) {
        // Validate and sanitize input
        $channel_name = sanitize_text_field($_POST['channel_name']);
        $channel_url = sanitize_text_field($_POST['channel_url']);
        $channel_logo_id = intval($_POST['channel_logo_id']);
        
        // Retrieve existing channels
        $channels = get_option('clappr_hls_channels', array());
        
        // Add new channel
        $new_channel_id = count($channels) + 1;
        $channels[$new_channel_id] = array(
            'name' => $channel_name,
            'url' => $channel_url,
            'logo_id' => $channel_logo_id,
        );
        
        // Update channels option
        update_option('clappr_hls_channels', $channels);
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['channel_id'])) {
        $channel_id = intval($_GET['channel_id']);
        
        // Retrieve existing channels
        $channels = get_option('clappr_hls_channels', array());
        
        // Remove the specified channel
        if (isset($channels[$channel_id])) {
            unset($channels[$channel_id]);
            update_option('clappr_hls_channels', $channels);
        }
    }
    
    if (isset($_POST['update_channel'])) {
        $channel_id = isset($_POST['channel_id']) ? intval($_POST['channel_id']) : 0;
        $channel_name = sanitize_text_field($_POST['channel_name']);
        $channel_url = sanitize_text_field($_POST['channel_url']);
        $channel_logo_id = intval($_POST['channel_logo_id']);
        
        $channels = get_option('clappr_hls_channels', array());
        
        if ($channel_id > 0 && isset($channels[$channel_id])) {
            $channels[$channel_id] = array(
                'name' => $channel_name,
                'url' => $channel_url,
                'logo_id' => $channel_logo_id,
            );
            update_option('clappr_hls_channels', $channels);
        }
    }
    
    ?>
    <div class="wrap clappr-hls-admin">
        <h2>HLS Player Settings</h2>
        <!-- Display Add Channel Form -->
        <form method="post" action="" enctype="multipart/form-data" class="clappr-hls-form">
            <h3>Add Channel</h3>
            <div class="clappr-hls-form-row">
                <label for="channel_name">Channel Name</label>
                <input type="text" name="channel_name" placeholder="Channel Name" required />
            </div>
            <div class="clappr-hls-form-row">
                <label for="channel_url">Channel URL</label>
                <input type="text" name="channel_url" placeholder="Channel URL" required />
            </div>
            <div class="clappr-hls-form-row">
                <label for="channel_logo">Channel Logo</label>
                <div class="clappr-hls-upload">
                    <input type="hidden" name="channel_logo_id" value="" />
                    <input type="file" id="upload-logo-btn" class="clappr-hls-upload-button" accept="image/*" />
                    <div id="logo-preview" class="clappr-hls-logo-preview">
                        <?php
                        if ($channel['logo_id']) {
                            echo wp_get_attachment_image($channel['logo_id'], 'thumbnail');
                        }
                        ?>
                    </div>
                </div>
            </div>
            <input type="submit" name="add_channel" value="Add Channel" class="button-primary" />
        </form>
        
        <!-- Display Channel List -->
        <h3>Channel List</h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Channel Name</th>
                   
                    <th>Logo</th>
                    <th>Shortcode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php clappr_hls_display_channel_list(); ?>
            </tbody>
        </table>
    </div>
    <?php
    
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['channel_id'])) {
        clappr_hls_display_edit_page();
    }
}

// Display Channel List
function clappr_hls_display_channel_list() {
    $channels = get_option('clappr_hls_channels', array());
    
    foreach ($channels as $channel_id => $channel) {
        echo '<tr>';
        echo '<td>' . esc_html($channel_id) . '</td>';
        echo '<td>' . esc_html($channel['name']) . '</td>';
       
        echo '<td>';
        if ($channel['logo_id']) {
            echo wp_get_attachment_image($channel['logo_id'], 'thumbnail');
        } else {
            echo 'N/A';
        }
        echo '</td>';
        echo '<td><input type="text" value="[clappr_hls_player url=' . $channel['url'] . ' autoplay=true]" readonly /><button class="copy-shortcode-btn" data-clipboard-text="[clappr_hls_player url=' . $channel['url'] . ' autoplay=true]">Copy</button></td>';
        echo '<td>
            <a href="?page=clappr-hls-player&action=edit&channel_id=' . $channel_id . '">Edit</a> |
            <a href="?page=clappr-hls-player&action=delete&channel_id=' . $channel_id . '">Delete</a>
        </td>';
        echo '</tr>';
    }
}

// Display Edit Channel Page
function clappr_hls_display_edit_page() {
    $channel_id = isset($_GET['channel_id']) ? intval($_GET['channel_id']) : 0;
    $channels = get_option('clappr_hls_channels', array());
    
    if ($channel_id > 0 && isset($channels[$channel_id])) {
        $channel = $channels[$channel_id];
        ?>
        <div class="wrap clappr-hls-admin">
            <h2>Edit Channel</h2>
            <form method="post" action="" enctype="multipart/form-data" class="clappr-hls-form">
                <input type="hidden" name="channel_id" value="<?php echo $channel_id; ?>" />
                <div class="clappr-hls-form-row">
                    <label for="channel_name">Channel Name</label>
                    <input type="text" name="channel_name" value="<?php echo esc_attr($channel['name']); ?>" required />
                </div>
                <div class="clappr-hls-form-row">
                    <label for="channel_url">Channel URL</label>
                    <input type="text" name="channel_url" value="<?php echo esc_attr($channel['url']); ?>" required />
                </div>
                <div class="clappr-hls-form-row">
                    <label for="channel_logo">Channel Logo</label>
                    <div class="clappr-hls-upload">
                        <input type="hidden" name="channel_logo_id" value="<?php echo $channel['logo_id']; ?>" />
                        <input type="file" id="upload-logo-btn" class="clappr-hls-upload-button" accept="image/*" />
                        <div id="logo-preview" class="clappr-hls-logo-preview">
                            <?php
                            if ($channel['logo_id']) {
                                echo wp_get_attachment_image($channel['logo_id'], 'thumbnail');
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <input type="submit" name="update_channel" value="Update Channel" class="button-primary" />
            </form>
        </div>
        <?php
    } else {
        echo 'Invalid channel ID.';
    }
}

// Handle logo upload and update
function clappr_hls_handle_logo_upload($channel_id) {
    if (!empty($_FILES['channel_logo']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        $attachment_id = media_handle_upload('channel_logo', 0);
        if (is_wp_error($attachment_id)) {
            echo 'Error uploading logo: ' . $attachment_id->get_error_message();
        } else {
            // Update channel logo ID
            $channels = get_option('clappr_hls_channels', array());
            $channels[$channel_id]['logo_id'] = $attachment_id;
            update_option('clappr_hls_channels', $channels);
        }
    }
}

// Shortcode
function clappr_hls_player_shortcode($atts) {
    $atts = shortcode_atts(array(
        'url' => '',
        'autoplay' => 'false',
    ), $atts);
    
    $channel_url = esc_url($atts['url']);
    $autoplay = $atts['autoplay'] === 'true' ? 'true' : 'false';
    
    if ($channel_url) {
        $output = '<div class="clappr-hls-player" data-poster="' . get_poster_url($channel_url) . '"></div>';
        $output .= '<script>
            jQuery(document).ready(function($) {
                var player = new Clappr.Player({
                    source: "' . $channel_url . '",
                    parentId: ".clappr-hls-player",
                    height: "700px",
                    width: "100%",
                    autoPlay: ' . $autoplay . ',
                    poster: "https://mytimetv.online/wp-content/uploads/2023/08/Screenshot_1.jpg",
                });
            });
        </script>';
        return $output;
    } else {
        return 'Invalid or missing channel URL.';
    }
}
add_shortcode('clappr_hls_player', 'clappr_hls_player_shortcode');

// Get poster URL from channel URL
function get_poster_url($channel_url) {
    // Implement your logic to fetch or generate the poster URL based on the channel URL
    // For example, you can use YouTube API or other methods to fetch a thumbnail as the poster
    // For simplicity, this example assumes the same URL with .jpg extension
    return $channel_url . '.jpg';
}

// Clipboard functionality
function clappr_hls_add_clipboard() {
    echo '<script>
        jQuery(document).ready(function($) {
            new ClipboardJS(".copy-shortcode-btn");
        });
        </script>';
}
add_action('admin_footer', 'clappr_hls_add_clipboard');

// Display Main Admin Page
function clappr_hls_display_main_page() {
    echo '<div class="wrap clappr-hls-admin">';
    echo '<h2>HLS Player Settings</h2>';
    echo '<p>Welcome to the HLS Player plugin settings page. Here you can manage your HLS channels and display the player using shortcodes.</p>';
    echo '</div>';
}
