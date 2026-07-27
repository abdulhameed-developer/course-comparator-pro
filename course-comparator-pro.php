<?php
/**
 * Plugin Name: Course Comparator
 * Plugin URI: https://github.com/abdulhameed-developer/course-comparator-pro
 * Description: Zero‑dependency course comparison engine with configurable JetEngine taxonomy meta mapping, server‑side PDF generation, and a modern AJAX‑driven settings dashboard.
 * Version: 2.1.0
 * Author: Abdul Hameed
 * Author URI: https://www.linkedin.com/in/abdul-hameed-website-developer
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

class CourseComparatorPro {

    private $default_accent = '#BC1C24';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('template_redirect', [$this, 'handle_native_pdf_generation']);
        add_action('wp_ajax_cc_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_head', [$this, 'output_accent_color_css']);
    }

    /* ------------------------------------------------------------------ */
    /*  Admin menu                                                         */
    /* ------------------------------------------------------------------ */
    public function add_admin_menu() {
        add_menu_page(
            'Course Comparator',
            'Course Comparator',
            'manage_options',
            'course-comparator-settings',
            [$this, 'render_settings_page'],
            'dashicons-columns',
            85
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Register settings                                                  */
    /* ------------------------------------------------------------------ */
    public function register_settings() {
        register_setting('cc_pro_settings_group', 'cc_max_limit');
        register_setting('cc_pro_settings_group', 'cc_max_cols_per_page');
        register_setting('cc_pro_settings_group', 'cc_pdf_logo_url');
        register_setting('cc_pro_settings_group', 'cc_accent_color');
        register_setting('cc_pro_settings_group', 'cc_taxonomy_slug');
        register_setting('cc_pro_settings_group', 'cc_meta_key_bg');
        register_setting('cc_pro_settings_group', 'cc_meta_key_text');
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX save handler                                                  */
    /* ------------------------------------------------------------------ */
    public function ajax_save_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        check_ajax_referer('cc_save_all_settings_nonce', 'cc_nonce');

        update_option('cc_max_limit', sanitize_text_field($_POST['cc_max_limit']));
        update_option('cc_max_cols_per_page', sanitize_text_field($_POST['cc_max_cols_per_page']));
        update_option('cc_pdf_logo_url', esc_url_raw($_POST['cc_pdf_logo_url']));
        update_option('cc_accent_color', sanitize_hex_color($_POST['cc_accent_color']));
        update_option('cc_taxonomy_slug', sanitize_text_field($_POST['cc_taxonomy_slug']));
        update_option('cc_meta_key_bg', sanitize_text_field($_POST['cc_meta_key_bg']));
        update_option('cc_meta_key_text', sanitize_text_field($_POST['cc_meta_key_text']));

        wp_send_json_success(['message' => 'Settings saved successfully.']);
    }

    /* ------------------------------------------------------------------ */
    /*  Settings page – modern dashboard                                   */
    /* ------------------------------------------------------------------ */
    public function render_settings_page() {
        $max_limit      = get_option('cc_max_limit', '8');
        $max_cols       = get_option('cc_max_cols_per_page', '4');
        $logo_url       = get_option('cc_pdf_logo_url', '');
        $accent_color   = get_option('cc_accent_color', $this->default_accent);
        $taxonomy_slug  = get_option('cc_taxonomy_slug', 'course-levels');
        $meta_key_bg    = get_option('cc_meta_key_bg', 'badge_bg_color');
        $meta_key_text  = get_option('cc_meta_key_text', 'badge_text_color');
        ?>
        <style>
            :root {
                --cc-admin-accent: <?php echo esc_attr($accent_color); ?>;
            }

            /* Toast notification */
            #cc-toast {
                visibility: hidden;
                min-width: 280px;
                background: #111827;
                color: #fff;
                text-align: center;
                border-radius: 8px;
                padding: 14px 20px;
                position: fixed;
                z-index: 99999;
                right: 30px;
                top: 30px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                font-size: 14px;
                font-weight: 500;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                opacity: 0;
                transition: visibility 0s 0.4s, opacity 0.3s ease;
            }
            #cc-toast.show {
                visibility: visible;
                opacity: 1;
                transition: opacity 0.3s ease;
            }
            #cc-toast .toast-progress {
                height: 3px;
                background: var(--cc-admin-accent);
                border-radius: 0 0 8px 8px;
                margin-top: 10px;
                width: 100%;
                animation: toastProgress 2.2s linear forwards;
            }
            @keyframes toastProgress {
                from { width: 100%; }
                to { width: 0%; }
            }

            /* Dashboard */
            .cc-dashboard {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                max-width: 860px;
                margin: 20px 20px 0 0;
            }
            .cc-dash-header {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 24px 28px;
                margin-bottom: 24px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            }
            .cc-dash-header h1 {
                font-size: 22px !important;
                font-weight: 700 !important;
                color: #111827 !important;
                margin: 0 0 4px !important;
                letter-spacing: -0.3px;
            }
            .cc-dash-header p {
                margin: 0;
                color: #6b7280;
                font-size: 14px;
            }

            .cc-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 24px;
                margin-bottom: 24px;
            }
            @media (max-width: 782px) {
                .cc-grid { grid-template-columns: 1fr; }
            }

            .cc-card {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 24px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            }
            .cc-card h3 {
                font-size: 15px !important;
                font-weight: 600 !important;
                color: #111827 !important;
                margin: 0 0 18px !important;
                padding-bottom: 10px;
                border-bottom: 1px solid #f3f4f6;
            }

            .cc-field {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-bottom: 18px;
            }
            .cc-field label {
                font-weight: 500;
                color: #374151;
                font-size: 13px;
            }
            .cc-input-text,
            .cc-color-input {
                padding: 9px 12px !important;
                border: 1px solid #d1d5db !important;
                border-radius: 6px !important;
                background: #f9fafb !important;
                color: #111827 !important;
                font-size: 14px !important;
                transition: border 0.2s, box-shadow 0.2s;
                width: 100%;
                box-sizing: border-box;
            }
            .cc-input-text:focus,
            .cc-color-input:focus {
                border-color: var(--cc-admin-accent) !important;
                background: #fff !important;
                box-shadow: 0 0 0 3px rgba(188,28,36,0.08);
                outline: none;
            }

            .cc-color-pick-wrapper {
                display: flex;
                align-items: center;
                gap: 10px;
                background: #f3f4f6;
                padding: 6px 10px;
                border-radius: 6px;
                border: 1px solid #e5e7eb;
                width: fit-content;
            }
            .cc-color-picker {
                border: none !important;
                padding: 0 !important;
                width: 30px !important;
                height: 30px !important;
                background: transparent !important;
                cursor: pointer;
                border-radius: 4px;
                overflow: hidden;
            }
            .cc-color-hex-text {
                border: none !important;
                background: transparent !important;
                font-family: monospace;
                font-size: 13px;
                width: 75px;
                color: #374151;
                padding: 0;
                outline: none;
            }

            .cc-btn-primary {
                background: var(--cc-admin-accent);
                color: #fff;
                border: none;
                padding: 10px 22px;
                font-size: 13px;
                font-weight: 600;
                border-radius: 6px;
                cursor: pointer;
                transition: opacity 0.2s, transform 0.1s;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .cc-btn-primary:hover {
                opacity: 0.92;
                transform: translateY(-1px);
            }

            .cc-btn-save-wrap {
                text-align: right;
                margin-top: 10px;
            }

            .cc-section-reference {
                margin-top: 40px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 24px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            }
            .cc-section-reference h3 {
                font-size: 15px !important;
                font-weight: 600 !important;
                color: #111827 !important;
                margin: 0 0 14px !important;
                padding-bottom: 8px;
                border-bottom: 1px solid #f3f4f6;
            }
            .cc-mapping-table {
                width: 100%;
                border-collapse: collapse;
            }
            .cc-mapping-table th {
                background: #fafafa;
                padding: 10px 12px;
                font-weight: 600;
                text-align: left;
                color: #6b7280;
                font-size: 13px;
                border-bottom: 1px solid #e5e7eb;
            }
            .cc-mapping-table td {
                padding: 10px 12px;
                font-size: 13.5px;
                border-bottom: 1px solid #f3f4f6;
                color: #374151;
            }
            .cc-mapping-table code {
                background: #f3f4f6;
                color: var(--cc-admin-accent);
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 12.5px;
            }
        </style>

        <div id="cc-toast">Settings saved<span class="toast-progress"></span></div>

        <div class="cc-dashboard">
            <div class="cc-dash-header">
                <h1>Course Comparator Settings</h1>
                <p>Configure comparison limits, brand styling, PDF output, and JetEngine taxonomy meta mapping.</p>
            </div>

            <form id="cc-settings-form">
                <?php wp_nonce_field('cc_save_all_settings_nonce', 'cc_nonce'); ?>

                <div class="cc-grid">
                    <!-- Selection & PDF Limits -->
                    <div class="cc-card">
                        <h3>Selection & PDF Limits</h3>
                        <div class="cc-field">
                            <label>Max Selection Cardinality</label>
                            <input type="number" name="cc_max_limit" value="<?php echo esc_attr($max_limit); ?>" min="1" max="20" class="cc-input-text" />
                            <p class="description" style="font-size:12px; color:#6b7280; margin:4px 0 0;">Maximum courses a user can add to the compare list.</p>
                        </div>
                        <div class="cc-field">
                            <label>Max Columns per PDF Page</label>
                            <input type="number" name="cc_max_cols_per_page" value="<?php echo esc_attr($max_cols); ?>" min="2" max="6" class="cc-input-text" />
                            <p class="description" style="font-size:12px; color:#6b7280; margin:4px 0 0;">Number of course columns printed before a page break.</p>
                        </div>
                    </div>

                    <!-- Brand & Accent -->
                    <div class="cc-card">
                        <h3>Brand & Accent</h3>
                        <div class="cc-field">
                            <label>PDF Header Logo URL</label>
                            <input type="text" name="cc_pdf_logo_url" value="<?php echo esc_url($logo_url); ?>" class="cc-input-text" placeholder="https://yoursite.com/logo.png" />
                        </div>
                        <div class="cc-field">
                            <label>Accent Colour</label>
                            <div class="cc-color-pick-wrapper">
                                <input type="color" name="cc_accent_color" value="<?php echo esc_attr($accent_color); ?>" class="cc-color-picker" id="cc-accent-picker" />
                                <input type="text" value="<?php echo esc_attr($accent_color); ?>" class="cc-color-hex-text" id="cc-accent-hex" readonly />
                            </div>
                            <p class="description" style="font-size:12px; color:#6b7280; margin:4px 0 0;">Used for selection borders, buttons, and PDF header accent.</p>
                        </div>
                    </div>
                </div>

                <!-- Taxonomy Meta Mapping -->
                <div class="cc-card" style="margin-bottom:24px;">
                    <h3>Taxonomy Meta Mapping</h3>
                    <p style="font-size:12px; color:#6b7280; margin:-12px 0 18px;">Specify which JetEngine taxonomy and which meta keys hold the badge colours.</p>
                    <div class="cc-grid">
                        <div class="cc-field">
                            <label>Taxonomy Slug</label>
                            <input type="text" name="cc_taxonomy_slug" value="<?php echo esc_attr($taxonomy_slug); ?>" class="cc-input-text" placeholder="course-levels" />
                        </div>
                        <div class="cc-field">
                            <label>Badge Background Color Meta Key</label>
                            <input type="text" name="cc_meta_key_bg" value="<?php echo esc_attr($meta_key_bg); ?>" class="cc-input-text" placeholder="badge_bg_color" />
                        </div>
                        <div class="cc-field">
                            <label>Badge Text Color Meta Key</label>
                            <input type="text" name="cc_meta_key_text" value="<?php echo esc_attr($meta_key_text); ?>" class="cc-input-text" placeholder="badge_text_color" />
                        </div>
                    </div>
                </div>

                <div class="cc-btn-save-wrap">
                    <button type="submit" class="cc-btn-primary" id="cc-save-btn">Save All Settings</button>
                </div>
            </form>

            <!-- JetEngine Architecture Reference (unchanged but accent used) -->
            <div class="cc-section-reference">
                <h3>JetEngine Architecture Core Mappings</h3>
                <p class="description" style="font-size:13px; color:#6b7280; margin-bottom:14px;">Use these exact class names in your Elementor listings:</p>
                <table class="cc-mapping-table">
                    <thead>
                        <tr><th style="width: 220px;">Element</th><th>CSS Class</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Card Wrapper</strong></td><td><code>.course-listing-card</code></td><td>Main container for each course item.</td></tr>
                        <tr><td><strong>Selected State</strong></td><td><code>.cc-selected-card</code></td><td>Applied automatically; border uses accent colour <code style="background:<?php echo esc_attr($accent_color); ?>; color:#fff;"><?php echo esc_html($accent_color); ?></code>.</td></tr>
                        <tr><td><strong>Toggle Compare</strong></td><td><code>.jet-add-to-store[data-store="course-compare"]</code></td><td>JetEngine add‑to‑store link.</td></tr>
                        <tr><td><strong>Sticky Bar</strong></td><td><code>.course-compare-bar</code></td><td>Shows when items are selected.</td></tr>
                        <tr><td><strong>Item Count</strong></td><td><code>.course-count</code></td><td>Displays number of selected courses.</td></tr>
                        <tr><td><strong>Clear All</strong></td><td><code>.clear-course-compare</code></td><td>Resets selection.</td></tr>
                        <tr><td><strong>Open Modal</strong></td><td><code>.open-course-comparison-popup</code></td><td>Opens the comparison modal.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        (function() {
            const form = document.getElementById('cc-settings-form');
            const toast = document.getElementById('cc-toast');
            const saveBtn = document.getElementById('cc-save-btn');

            // Sync accent colour picker with hex text and CSS variable
            const accentPicker = document.getElementById('cc-accent-picker');
            const accentHex = document.getElementById('cc-accent-hex');
            accentPicker.addEventListener('input', function() {
                accentHex.value = this.value;
                document.documentElement.style.setProperty('--cc-admin-accent', this.value);
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving…';

                const formData = new FormData(form);
                formData.append('action', 'cc_save_settings');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show toast
                        toast.classList.add('show');
                        // Reset toast animation by cloning the progress bar
                        const progress = toast.querySelector('.toast-progress');
                        const newProgress = progress.cloneNode(true);
                        progress.parentNode.replaceChild(newProgress, progress);
                        setTimeout(() => {
                            toast.classList.remove('show');
                        }, 2500);
                    } else {
                        alert('Error: ' + (data.data || 'Could not save settings.'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Network error. Please try again.');
                })
                .finally(() => {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save All Settings';
                });
            });
        })();
        </script>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /*  Front‑end assets                                                   */
    /* ------------------------------------------------------------------ */
    public function enqueue_frontend_assets() {
        wp_enqueue_style('cc-pro-frontend-css', plugins_url('assets/css/style.css', __FILE__), [], '2.1.0');
        wp_enqueue_script('cc-pro-frontend-js', plugins_url('assets/js/app.js', __FILE__), [], '2.1.0', true);

        wp_localize_script('cc-pro-frontend-js', 'CCProEngineSettings', [
            'maxLimit'    => intval(get_option('cc_max_limit', '8')),
            'accentColor' => get_option('cc_accent_color', $this->default_accent),
            'taxonomySlug' => get_option('cc_taxonomy_slug', 'course-levels'),
            'metaKeyBg'   => get_option('cc_meta_key_bg', 'badge_bg_color'),
            'metaKeyText' => get_option('cc_meta_key_text', 'badge_text_color'),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Inject accent colour as CSS variable                               */
    /* ------------------------------------------------------------------ */
    public function output_accent_color_css() {
        $accent = get_option('cc_accent_color', $this->default_accent);
        echo '<style id="cc-accent-style">:root { --cc-accent: ' . esc_attr($accent) . '; }</style>' . "\n";
    }

    /* ------------------------------------------------------------------ */
    /*  Native PDF generation (PHP template)                               */
    /* ------------------------------------------------------------------ */
    public function handle_native_pdf_generation() {
        if (!isset($_GET['generate_comparison_pdf']) || $_GET['generate_comparison_pdf'] !== 'native') {
            return;
        }

        $raw_ids = isset($_GET['ids']) ? sanitize_text_field($_GET['ids']) : '';
        if (empty($raw_ids)) {
            wp_die('<h1>No courses selected</h1>', 'Selection Missing', ['response' => 400]);
        }

        $max_allowed      = intval(get_option('cc_max_limit', '8'));
        $max_cols         = intval(get_option('cc_max_cols_per_page', '4'));
        $logo_url         = get_option('cc_pdf_logo_url', '');
        $accent           = get_option('cc_accent_color', $this->default_accent);
        $taxonomy_slug    = get_option('cc_taxonomy_slug', 'course-levels');
        $meta_key_bg      = get_option('cc_meta_key_bg', 'badge_bg_color');
        $meta_key_text    = get_option('cc_meta_key_text', 'badge_text_color');

        $course_ids = array_slice(array_map('intval', explode(',', $raw_ids)), 0, $max_allowed);

        // Gather course data
        $courses = [];
        foreach ($course_ids as $id) {
            if (!$id) continue;
            $post = get_post($id);
            if (!$post) continue;

            $course_name = get_post_meta($id, 'course_name', true) ?: $post->post_title;

            // Level via taxonomy term
            $level_term_id = get_post_meta($id, 'course_level', true);
            $level_name    = 'N/A';
            $badge_bg      = '#e4f0fb';
            $badge_text    = '#2c7be5';
            if ($level_term_id && term_exists((int)$level_term_id, $taxonomy_slug)) {
                $term = get_term((int)$level_term_id, $taxonomy_slug);
                if ($term && !is_wp_error($term)) {
                    $level_name = $term->name;
                    $term_bg    = get_term_meta($term->term_id, $meta_key_bg, true);
                    $term_text  = get_term_meta($term->term_id, $meta_key_text, true);
                    if (!empty($term_bg)) $badge_bg = $term_bg;
                    if (!empty($term_text)) $badge_text = $term_text;
                }
            }

            // Duration
            $dur_id   = get_post_meta($id, 'course_duration', true);
            $duration = 'N/A';
            if ($dur_id && term_exists((int)$dur_id, 'course-durations')) {
                $term = get_term((int)$dur_id, 'course-durations');
                if ($term && !is_wp_error($term)) $duration = $term->name;
            }

            // Fee
            $fee_raw = get_post_meta($id, 'course_fee', true);
            $fee     = $fee_raw ? 'RM ' . number_format(floatval($fee_raw)) : 'N/A';

            // University
            $uni_id   = get_post_meta($id, 'university_ref', true);
            $uni_name = 'N/A';
            $location = 'N/A';
            $initials = 'C';
            $uni_logo = '';
            if ($uni_id) {
                $uni_post = get_post($uni_id);
                if ($uni_post) {
                    $uni_name = $uni_post->post_title;
                    $location = get_post_meta($uni_id, 'location', true) ?: 'N/A';
                    $words = explode(' ', $uni_name);
                    $initials = '';
                    foreach ($words as $w) $initials .= strtoupper(substr($w, 0, 1));
                    $initials = substr($initials, 0, 2);
                    $thumb_id = get_post_thumbnail_id($uni_id);
                    if ($thumb_id) {
                        $img = wp_get_attachment_image_src($thumb_id, 'medium');
                        if ($img) $uni_logo = $img[0];
                    }
                }
            }

            // Intakes
            $intake_ids   = get_post_meta($id, 'course_intakes', true);
            $intake_names = [];
            if (!empty($intake_ids) && is_array($intake_ids)) {
                $terms = get_terms([
                    'taxonomy'   => 'course-intakes',
                    'include'    => $intake_ids,
                    'hide_empty' => false,
                    'orderby'    => 'meta_value_num',
                    'meta_key'   => 'intake_order',
                    'order'      => 'ASC'
                ]);
                if (!is_wp_error($terms) && !empty($terms)) {
                    foreach ($terms as $t) $intake_names[] = $t->name;
                }
            }

            $courses[] = [
                'title'    => $course_name,
                'level'    => $level_name,
                'level_bg' => $badge_bg,
                'level_tx' => $badge_text,
                'duration' => $duration,
                'fee'      => $fee,
                'intakes'  => $intake_names,
                'uni'      => $uni_name,
                'location' => $location,
                'initials' => $initials,
                'uni_logo' => $uni_logo
            ];
        }

        $chunks = array_chunk($courses, $max_cols);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Course Comparison Report</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap');
                :root { --pdf-accent: <?php echo esc_attr($accent); ?>; }
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                    font-size: 12px;
                    line-height: 1.25;
                    color: #111827;
                    background: #fff;
                    padding: 18px 14px;
                }
                .pdf-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 16px;
                    border-bottom: 3px solid var(--pdf-accent);
                    padding-bottom: 10px;
                }
                .pdf-header h1 { font-family: 'Poppins', sans-serif; font-size: 20px; font-weight: 800; color: #111827; margin: 0; }
                .pdf-header p { font-size: 11px; color: #6b7280; margin: 2px 0 0; }
                .brand-logo-slot img { max-height: 38px; width: auto; }
                .compare-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    table-layout: fixed;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    overflow: hidden;
                    page-break-inside: avoid;
                }
                .td-cell {
                    padding: 8px 10px;
                    border-bottom: 1px solid #f3f4f6;
                    border-right: 1px solid #f3f4f6;
                    vertical-align: middle;
                    font-size: 11px;
                    color: #374151;
                    text-align: left;
                    word-break: break-word;
                }
                .td-cell:last-child { border-right: none; }
                tr:last-child .td-cell { border-bottom: none; }
                .label-col {
                    width: 90px;
                    font-weight: 600;
                    color: #6b7280;
                    background: #fafafa;
                    font-size: 10.5px;
                    white-space: nowrap;
                }
                .header-cell { vertical-align: top; padding: 12px 10px; }
                .uni-logo {
                    width: 42px; height: 42px; line-height: 42px; text-align: center;
                    color: #fff; font-weight: 700; font-size: 14px; border-radius: 8px;
                    margin-bottom: 6px; overflow: hidden;
                }
                .uni-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
                .course-title { font-size: 12px; font-weight: 700; color: #111827; margin: 0 0 3px; line-height: 1.3; }
                .course-uni { font-size: 10.5px; color: #6b7280; margin: 0; }
                .badge-qual {
                    padding: 3px 8px; border-radius: 9999px; font-size: 10px; font-weight: 700;
                    display: inline-block; text-transform: uppercase; letter-spacing: 0.2px;
                }
                .badge-intake {
                    background: #fff; color: #374151; border: 1px solid #e5e7eb;
                    padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 500;
                    margin-right: 3px; margin-bottom: 3px; display: inline-block;
                }
                .fee-text { font-size: 12px; font-weight: 800; color: #111827; }
                .page-break { page-break-before: always; }
                .print-btn-wrapper { text-align: center; margin: 20px 0; }
                .print-btn {
                    background: var(--pdf-accent); color: #fff; border: none; padding: 10px 24px;
                    font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; font-family: 'Inter', sans-serif;
                }
                @media print {
                    html, body { background: #fff !important; padding: 0 !important; margin: 0 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                    body { padding: 8mm 6mm !important; }
                    @page { size: landscape; margin: 6mm; }
                    .print-btn-wrapper { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="pdf-header">
                <div>
                    <h1>Course Comparison Report</h1>
                    <p>Generated on <?php echo date('d M Y'); ?></p>
                </div>
                <?php if(!empty($logo_url)): ?>
                <div class="brand-logo-slot"><img src="<?php echo esc_url($logo_url); ?>" alt="Logo" /></div>
                <?php endif; ?>
            </div>

            <?php foreach ($chunks as $chunk_idx => $current_chunk): 
                if ($chunk_idx > 0) { echo '<div class="page-break"></div>'; }
                ?>
                <table class="compare-table">
                    <tr>
                        <td class="td-cell label-col"></td>
                        <?php foreach ($current_chunk as $idx => $c): 
                            $bg_logo = ($idx % 2 === 0) ? '#1e398a' : '#045037'; ?>
                            <td class="td-cell header-cell">
                                <div class="uni-logo" style="background:<?php echo !empty($c['uni_logo']) ? 'transparent' : $bg_logo; ?>;">
                                    <?php if(!empty($c['uni_logo'])): ?>
                                        <img src="<?php echo esc_url($c['uni_logo']); ?>" alt="<?php echo esc_attr($c['uni']); ?>" />
                                    <?php else: ?>
                                        <?php echo $c['initials']; ?>
                                    <?php endif; ?>
                                </div>
                                <h3 class="course-title"><?php echo esc_html($c['title']); ?></h3>
                                <p class="course-uni"><?php echo esc_html($c['uni']); ?></p>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="td-cell label-col">Qualification</td>
                        <?php foreach ($current_chunk as $c): ?>
                            <td class="td-cell">
                                <span class="badge-qual" style="background:<?php echo $c['level_bg']; ?>; color:<?php echo $c['level_tx']; ?>;">
                                    <?php echo esc_html($c['level']); ?>
                                </span>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="td-cell label-col">University</td>
                        <?php foreach ($current_chunk as $c): ?>
                            <td class="td-cell"><?php echo esc_html($c['uni']); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="td-cell label-col">Location</td>
                        <?php foreach ($current_chunk as $c): ?>
                            <td class="td-cell"><?php echo esc_html($c['location']); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="td-cell label-col">Duration</td>
                        <?php foreach ($current_chunk as $c): 
                            $d_text = $c['duration'];
                            if (stripos($d_text, 'year') === false && stripos($d_text, 'month') === false) $d_text .= ' Years';
                            ?>
                            <td class="td-cell"><?php echo esc_html($d_text); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="td-cell label-col">Intakes</td>
                        <?php foreach ($current_chunk as $c): ?>
                            <td class="td-cell">
                                <?php if (!empty($c['intakes'])): foreach ($c['intakes'] as $intake): ?>
                                    <span class="badge-intake"><?php echo esc_html($intake); ?></span>
                                <?php endforeach; else: ?>
                                    <span style="color:#9ca3af;">N/A</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <td class="td-cell label-col">Tuition Fee</td>
                        <?php foreach ($current_chunk as $c): ?>
                            <td class="td-cell"><span class="fee-text"><?php echo esc_html($c['fee']); ?></span></td>
                        <?php endforeach; ?>
                    </tr>
                </table>
            <?php endforeach; ?>

            <div class="print-btn-wrapper">
                <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
            </div>
            <script>
                setTimeout(function() { window.print(); }, 600);
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

new CourseComparatorPro();