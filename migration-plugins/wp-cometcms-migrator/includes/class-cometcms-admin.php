<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CometCMS_Migrator_Admin
{
    private const OPTION = 'cometcms_migrator_settings';
    private const NONCE = 'cometcms_migrator_nonce';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_ajax_cometcms_test_connection', [self::class, 'ajax_test_connection']);
        add_action('wp_ajax_cometcms_preview_counts', [self::class, 'ajax_preview_counts']);
        add_action('wp_ajax_cometcms_migrate_batch', [self::class, 'ajax_migrate_batch']);
    }

    public static function menu(): void
    {
        add_management_page(
            __('CometCMS Migrator', 'cometcms-migrator'),
            __('CometCMS Migrator', 'cometcms-migrator'),
            'manage_options',
            'cometcms-migrator',
            [self::class, 'render']
        );
    }

    public static function register_settings(): void
    {
        register_setting('cometcms_migrator', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [CometCMS_Migrator::class, 'sanitize_settings'],
            'default' => CometCMS_Migrator::default_settings(),
        ]);
    }

    public static function enqueue(string $hook): void
    {
        if ($hook !== 'tools_page_cometcms-migrator') {
            return;
        }

        wp_enqueue_style(
            'cometcms-migrator',
            COMETCMS_MIGRATOR_URL . 'assets/admin.css',
            [],
            COMETCMS_MIGRATOR_VERSION
        );

        wp_enqueue_script(
            'cometcms-migrator',
            COMETCMS_MIGRATOR_URL . 'assets/admin.js',
            ['jquery'],
            COMETCMS_MIGRATOR_VERSION,
            true
        );

        wp_localize_script('cometcms-migrator', 'CometCMSMigrator', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'batchSize' => (int) self::settings()['batch_size'],
            'strings' => [
                'running' => __('Migration running...', 'cometcms-migrator'),
                'done' => __('Migration finished.', 'cometcms-migrator'),
                'failed' => __('Migration failed.', 'cometcms-migrator'),
            ],
        ]);
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'cometcms-migrator'));
        }

        $settings = self::settings();
        $post_types = self::available_post_types();
        $acf_option_pages = CometCMS_Migrator::available_acf_option_pages();
        ?>
        <div class="wrap cometcms-migrator">
            <h1><?php esc_html_e('CometCMS Migrator', 'cometcms-migrator'); ?></h1>

            <form method="post" action="options.php" class="cometcms-panel">
                <?php settings_fields('cometcms_migrator'); ?>

                <h2><?php esc_html_e('Connection', 'cometcms-migrator'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="cometcms-base-url"><?php esc_html_e('CometCMS URL', 'cometcms-migrator'); ?></label></th>
                        <td>
                            <input id="cometcms-base-url" type="url" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[base_url]" value="<?php echo esc_attr($settings['base_url']); ?>" placeholder="https://cms.example.com" required>
                            <p class="description"><?php esc_html_e('Use the CometCMS site root, not the /admin dashboard URL. Pasted /admin URLs are normalized when settings are saved.', 'cometcms-migrator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cometcms-workspace"><?php esc_html_e('Workspace', 'cometcms-migrator'); ?></label></th>
                        <td><input id="cometcms-workspace" type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[workspace]" value="<?php echo esc_attr($settings['workspace']); ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cometcms-api-key"><?php esc_html_e('API key', 'cometcms-migrator'); ?></label></th>
                        <td><input id="cometcms-api-key" type="password" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[api_key]" value="<?php echo esc_attr($settings['api_key']); ?>" autocomplete="off" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cometcms-timeout"><?php esc_html_e('Request timeout', 'cometcms-migrator'); ?></label></th>
                        <td><input id="cometcms-timeout" type="number" min="10" max="300" name="<?php echo esc_attr(self::OPTION); ?>[timeout]" value="<?php echo esc_attr((string) $settings['timeout']); ?>"> <?php esc_html_e('seconds', 'cometcms-migrator'); ?></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Content Mapping', 'cometcms-migrator'); ?></h2>
                <table class="widefat striped cometcms-map-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Migrate', 'cometcms-migrator'); ?></th>
                            <th><?php esc_html_e('WordPress source', 'cometcms-migrator'); ?></th>
                            <th><?php esc_html_e('CometCMS content type', 'cometcms-migrator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($post_types as $post_type => $label) : ?>
                            <?php $enabled = in_array($post_type, (array) $settings['post_types'], true); ?>
                            <tr>
                                <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[post_types][]" value="<?php echo esc_attr($post_type); ?>" <?php checked($enabled); ?>></td>
                                <td><?php echo esc_html($label); ?> <code><?php echo esc_html($post_type); ?></code></td>
                                <td><input type="text" name="<?php echo esc_attr(self::OPTION); ?>[collections][<?php echo esc_attr($post_type); ?>]" value="<?php echo esc_attr((string) ($settings['collections'][$post_type] ?? 'wordpress-' . $post_type)); ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($acf_option_pages as $page) : ?>
                            <?php
                            $page_slug = (string) $page['slug'];
                            $enabled = in_array($page_slug, (array) $settings['acf_option_pages'], true);
                            $collection = (string) ($settings['acf_option_collections'][$page_slug] ?? 'wordpress-acf-options-' . $page_slug);
                            ?>
                            <tr>
                                <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[acf_option_pages][]" value="<?php echo esc_attr($page_slug); ?>" <?php checked($enabled); ?>></td>
                                <td><?php echo esc_html((string) $page['title']); ?> <code><?php echo esc_html((string) $page['post_id']); ?></code> <span class="description"><?php esc_html_e('ACF option page', 'cometcms-migrator'); ?></span></td>
                                <td><input type="text" name="<?php echo esc_attr(self::OPTION); ?>[acf_option_collections][<?php echo esc_attr($page_slug); ?>]" value="<?php echo esc_attr($collection); ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2><?php esc_html_e('Migration Options', 'cometcms-migrator'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Schemas', 'cometcms-migrator'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[create_schema]" value="1" <?php checked(!empty($settings['create_schema'])); ?>> <?php esc_html_e('Create missing CometCMS content types', 'cometcms-migrator'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Existing entries', 'cometcms-migrator'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[update_existing]" value="1" <?php checked(!empty($settings['update_existing'])); ?>> <?php esc_html_e('Update entries that were migrated before or have the same slug', 'cometcms-migrator'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Media', 'cometcms-migrator'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[migrate_media]" value="1" <?php checked(!empty($settings['migrate_media'])); ?>> <?php esc_html_e('Upload featured images and attached media', 'cometcms-migrator'); ?></label>
                            <p><label><?php esc_html_e('Media category', 'cometcms-migrator'); ?> <input type="text" name="<?php echo esc_attr(self::OPTION); ?>[media_category]" value="<?php echo esc_attr((string) $settings['media_category']); ?>"></label></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('ACF fields', 'cometcms-migrator'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[migrate_acf]" value="1" <?php checked(!empty($settings['migrate_acf'])); ?>> <?php esc_html_e('Import Advanced Custom Fields values and add missing CometCMS schema fields', 'cometcms-migrator'); ?></label>
                            <p class="description"><?php esc_html_e('Simple ACF fields are mapped to native CometCMS fields. Complex ACF Pro fields such as repeaters and flexible content are stored as JSON.', 'cometcms-migrator'); ?></p>
                            <p class="description"><?php esc_html_e('ACF option pages are selected in Content Mapping and are created as single-entry CometCMS content types.', 'cometcms-migrator'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cometcms-batch-size"><?php esc_html_e('Batch size', 'cometcms-migrator'); ?></label></th>
                        <td><input id="cometcms-batch-size" type="number" min="1" max="50" name="<?php echo esc_attr(self::OPTION); ?>[batch_size]" value="<?php echo esc_attr((string) $settings['batch_size']); ?>"></td>
                    </tr>
                </table>

                <?php submit_button(__('Save settings', 'cometcms-migrator')); ?>
            </form>

            <div class="cometcms-panel">
                <h2><?php esc_html_e('Run Migration', 'cometcms-migrator'); ?></h2>
                <p><?php esc_html_e('Save settings first, then test the connection and run batches. The plugin stores migrated CometCMS entry IDs in post meta so later runs can update entries.', 'cometcms-migrator'); ?></p>
                <p class="cometcms-actions">
                    <button type="button" class="button" id="cometcms-test"><?php esc_html_e('Test connection', 'cometcms-migrator'); ?></button>
                    <button type="button" class="button" id="cometcms-preview"><?php esc_html_e('Preview counts', 'cometcms-migrator'); ?></button>
                    <button type="button" class="button button-primary" id="cometcms-run"><?php esc_html_e('Run migration', 'cometcms-migrator'); ?></button>
                </p>
                <div class="cometcms-progress" hidden>
                    <div class="cometcms-progress-bar"><span></span></div>
                    <p class="cometcms-progress-text"></p>
                </div>
                <pre id="cometcms-log" class="cometcms-log" aria-live="polite"></pre>
            </div>
        </div>
        <?php
    }

    public static function ajax_test_connection(): void
    {
        self::guard_ajax();
        self::send_result(self::run_safely(static fn() => (new CometCMS_Migrator(self::settings()))->test_connection()));
    }

    public static function ajax_preview_counts(): void
    {
        self::guard_ajax();
        self::send_result(self::run_safely(static function (): array {
            $migrator = new CometCMS_Migrator(self::settings());

            return [
                'counts' => $migrator->counts(),
                'labels' => $migrator->migration_labels(),
            ];
        }));
    }

    public static function ajax_migrate_batch(): void
    {
        self::guard_ajax();
        @set_time_limit(max(60, (int) self::settings()['timeout'] + 30));

        $post_type = sanitize_key((string) ($_POST['post_type'] ?? ''));
        $offset = max(0, (int) ($_POST['offset'] ?? 0));
        $limit = max(1, min(50, (int) ($_POST['limit'] ?? self::settings()['batch_size'])));

        self::send_result(self::run_safely(static fn() => (new CometCMS_Migrator(self::settings()))->migrate_batch($post_type, $offset, $limit)));
    }

    private static function settings(): array
    {
        return wp_parse_args((array) get_option(self::OPTION, []), CometCMS_Migrator::default_settings());
    }

    private static function available_post_types(): array
    {
        $types = get_post_types(['public' => true], 'objects');
        unset($types['attachment']);
        $mapped = [];

        foreach ($types as $name => $type) {
            $mapped[$name] = (string) ($type->labels->name ?? $name);
        }

        return $mapped;
    }

    private static function guard_ajax(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'cometcms-migrator')], 403);
        }

        check_ajax_referer(self::NONCE, 'nonce');
    }

    private static function send_result(array|WP_Error $result): void
    {
        if (is_wp_error($result)) {
            wp_send_json_error([
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
                'data' => $result->get_error_data(),
            ], 400);
        }

        wp_send_json_success($result);
    }

    private static function run_safely(callable $callback): array|WP_Error
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            error_log('[CometCMS Migrator] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return new WP_Error('cometcms_migrator_exception', $e->getMessage(), [
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
            ]);
        }
    }
}
