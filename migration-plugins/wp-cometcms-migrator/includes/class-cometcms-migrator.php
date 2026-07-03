<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CometCMS_Migrator
{
    private const ACF_OPTIONS_PREFIX = 'acf_options__';

    private CometCMS_Migrator_Api_Client $client;
    private array $settings;
    private array $media_cache = [];

    public function __construct(array $settings)
    {
        $this->settings = $settings;
        $this->client = new CometCMS_Migrator_Api_Client($settings);
    }

    public static function default_settings(): array
    {
        return [
            'base_url' => '',
            'workspace' => 'default',
            'api_key' => '',
            'timeout' => 60,
            'batch_size' => 10,
            'post_types' => ['post', 'page'],
            'collections' => [
                'post' => 'wordpress-posts',
                'page' => 'wordpress-pages',
            ],
            'create_schema' => true,
            'update_existing' => true,
            'migrate_media' => true,
            'migrate_acf' => true,
            'acf_option_pages' => [],
            'acf_option_collections' => [],
            'media_category' => 'WordPress Migration',
        ];
    }

    public static function sanitize_settings(array $input): array
    {
        $defaults = self::default_settings();
        $post_types = array_values(array_filter(array_map('sanitize_key', (array) ($input['post_types'] ?? []))));
        $collections = [];
        $acf_option_pages = array_values(array_filter(array_map('sanitize_key', (array) ($input['acf_option_pages'] ?? []))));
        $acf_option_collections = [];

        foreach ((array) ($input['collections'] ?? []) as $post_type => $collection) {
            $post_type = sanitize_key((string) $post_type);
            if ($post_type === '') {
                continue;
            }

            $collections[$post_type] = sanitize_title((string) $collection);
        }

        foreach ($post_types as $post_type) {
            if (!isset($collections[$post_type])) {
                $collections[$post_type] = sanitize_title((string) ($defaults['collections'][$post_type] ?? 'wordpress-' . $post_type));
            }
        }

        foreach ((array) ($input['acf_option_collections'] ?? []) as $page_slug => $collection) {
            $page_slug = sanitize_key((string) $page_slug);
            if ($page_slug === '') {
                continue;
            }

            $acf_option_collections[$page_slug] = sanitize_title((string) $collection);
        }

        foreach ($acf_option_pages as $page_slug) {
            if (!isset($acf_option_collections[$page_slug])) {
                $acf_option_collections[$page_slug] = sanitize_title('wordpress-acf-options-' . $page_slug);
            }
        }

        return [
            'base_url' => esc_url_raw(CometCMS_Migrator_Api_Client::normalize_base_url((string) ($input['base_url'] ?? ''))),
            'workspace' => sanitize_title((string) ($input['workspace'] ?? $defaults['workspace'])),
            'api_key' => sanitize_text_field((string) ($input['api_key'] ?? '')),
            'timeout' => max(10, min(300, (int) ($input['timeout'] ?? $defaults['timeout']))),
            'batch_size' => max(1, min(50, (int) ($input['batch_size'] ?? $defaults['batch_size']))),
            'post_types' => $post_types,
            'collections' => array_replace($defaults['collections'], $collections),
            'create_schema' => !empty($input['create_schema']),
            'update_existing' => !empty($input['update_existing']),
            'migrate_media' => !empty($input['migrate_media']),
            'migrate_acf' => !empty($input['migrate_acf']),
            'acf_option_pages' => $acf_option_pages,
            'acf_option_collections' => $acf_option_collections,
            'media_category' => sanitize_text_field((string) ($input['media_category'] ?? $defaults['media_category'])),
        ];
    }

    public static function acf_option_type(string $page_slug): string
    {
        return self::ACF_OPTIONS_PREFIX . sanitize_key($page_slug);
    }

    public static function available_acf_option_pages(): array
    {
        if (!function_exists('get_field_objects')) {
            return [];
        }

        $pages = [];

        if (function_exists('acf_get_options_pages')) {
            $registered = acf_get_options_pages();
            foreach (is_array($registered) ? $registered : [] as $page) {
                if (!is_array($page)) {
                    continue;
                }

                $post_id = (string) ($page['post_id'] ?? 'option');
                if ($post_id === '') {
                    $post_id = 'option';
                }

                $slug = sanitize_title((string) ($page['menu_slug'] ?? $post_id));
                if ($slug === '') {
                    $slug = sanitize_title($post_id);
                }

                $pages[$slug] = [
                    'post_id' => $post_id,
                    'slug' => $slug,
                    'title' => (string) ($page['page_title'] ?? $page['menu_title'] ?? ucfirst(str_replace('-', ' ', $slug))),
                ];
            }
        }

        foreach (['option', 'options'] as $post_id) {
            if ($pages !== [] || self::acf_field_objects_for_raw_source($post_id, false) === []) {
                continue;
            }

            $pages['acf-options'] = [
                'post_id' => $post_id,
                'slug' => 'acf-options',
                'title' => __('ACF Options', 'cometcms-migrator'),
            ];
        }

        return array_values(array_filter($pages, static function (array $page): bool {
            return self::acf_field_objects_for_raw_source((string) $page['post_id'], false) !== [];
        }));
    }

    public function test_connection(): array|WP_Error
    {
        return $this->client->health();
    }

    public function counts(): array
    {
        $counts = [];

        foreach ((array) ($this->settings['post_types'] ?? []) as $post_type) {
            $post_type = sanitize_key($post_type);
            $counts[$post_type] = (int) wp_count_posts($post_type)->publish
                + (int) wp_count_posts($post_type)->draft
                + (int) wp_count_posts($post_type)->private
                + (int) wp_count_posts($post_type)->future;
        }

        foreach ($this->enabled_acf_option_pages() as $page) {
            $counts[self::acf_option_type((string) $page['slug'])] = 1;
        }

        return $counts;
    }

    public function migration_labels(): array
    {
        $labels = [];

        foreach ((array) ($this->settings['post_types'] ?? []) as $post_type) {
            $post_type = sanitize_key($post_type);
            $labels[$post_type] = get_post_type_object($post_type)->labels->name ?? ucfirst($post_type);
        }

        foreach ($this->enabled_acf_option_pages() as $page) {
            $labels[self::acf_option_type((string) $page['slug'])] = sprintf(
                __('ACF options: %s', 'cometcms-migrator'),
                (string) $page['title']
            );
        }

        return $labels;
    }

    public function migrate_batch(string $post_type, int $offset, int $limit): array|WP_Error
    {
        $post_type = sanitize_key($post_type);

        if ($this->is_acf_option_type($post_type)) {
            return $this->migrate_acf_option_page_batch($post_type, $offset);
        }

        $collection = $this->collection_for($post_type);

        if ($collection === '') {
            return new WP_Error('cometcms_collection_missing', __('No CometCMS collection is configured for this post type.', 'cometcms-migrator'));
        }

        $query = new WP_Query([
            'post_type' => $post_type,
            'post_status' => ['publish', 'draft', 'private', 'future'],
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'no_found_rows' => false,
        ]);

        if (!empty($this->settings['create_schema'])) {
            $schema_result = $this->ensure_schema($post_type, $collection, $query->posts);
            if (is_wp_error($schema_result)) {
                return $schema_result;
            }
        }

        $result = [
            'post_type' => $post_type,
            'collection' => $collection,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'total' => (int) $query->found_posts,
            'next_offset' => $offset + count($query->posts),
            'done' => ($offset + count($query->posts)) >= (int) $query->found_posts,
            'messages' => [],
        ];

        foreach ($query->posts as $post) {
            $migration = $this->migrate_post($post, $collection);
            $result['processed']++;

            if (is_wp_error($migration)) {
                $result['failed']++;
                $result['messages'][] = sprintf('#%d %s: %s', (int) $post->ID, $post->post_title, $migration->get_error_message());
                continue;
            }

            $result[$migration['action']]++;
            $result['messages'][] = sprintf('#%d %s: %s', (int) $post->ID, $post->post_title, $migration['action']);
        }

        wp_reset_postdata();

        return $result;
    }

    private function migrate_acf_option_page_batch(string $type, int $offset): array|WP_Error
    {
        $page = $this->acf_option_page_for_type($type);
        if ($page === null) {
            return new WP_Error('cometcms_acf_options_disabled', __('This ACF option page is not enabled or ACF is unavailable.', 'cometcms-migrator'));
        }

        $collection = $this->acf_option_collection_for((string) $page['slug']);
        if ($collection === '') {
            return new WP_Error('cometcms_collection_missing', __('No CometCMS collection is configured for ACF option pages.', 'cometcms-migrator'));
        }

        if (!empty($this->settings['create_schema'])) {
            $schema_result = $this->ensure_acf_options_schema($collection, $page);
            if (is_wp_error($schema_result)) {
                return $schema_result;
            }
        }

        $result = [
            'post_type' => $type,
            'collection' => $collection,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'total' => 1,
            'next_offset' => $offset >= 1 ? $offset : 1,
            'done' => true,
            'messages' => [],
        ];

        if ($offset < 1) {
            $migration = $this->migrate_acf_option_page($page, $collection);
            $result['processed']++;

            if (is_wp_error($migration)) {
                $result['failed']++;
                $result['messages'][] = sprintf('%s: %s', (string) $page['title'], $migration->get_error_message());
                return $result;
            }

            $result[$migration['action']]++;
            $result['messages'][] = sprintf('%s: %s', (string) $page['title'], $migration['action']);
        }

        return $result;
    }

    private function ensure_schema(string $post_type, string $collection, array $posts = []): bool|WP_Error
    {
        $acf_fields = $this->acf_schema_fields($posts);
        $existing = $this->client->get_content_type($collection);
        if (!is_wp_error($existing)) {
            if ($acf_fields === []) {
                return true;
            }

            $schema = is_array($existing['data'] ?? null) ? $existing['data'] : [];
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            $missing = array_diff_key($acf_fields, $fields);

            if ($missing === []) {
                return true;
            }

            $schema['fields'] = array_replace($fields, $missing);
            $updated = $this->client->update_content_type($collection, $schema);

            return is_wp_error($updated) ? $updated : true;
        }

        if ($acf_fields === [] && !empty($this->settings['migrate_acf']) && $this->acf_available()) {
            $acf_fields['acf'] = [
                'type' => 'json',
                'required' => false,
            ];
        }

        $error_data = $existing->get_error_data();
        if ((int) ($error_data['status'] ?? 0) !== 404) {
            return $existing;
        }

        $label = get_post_type_object($post_type)->labels->name ?? ucfirst($post_type);
        $schema = [
            'name' => $collection,
            'label' => 'WordPress ' . $label,
            'icon' => $post_type === 'page' ? 'mdi:file-document-outline' : 'mdi:post-outline',
            'singleton' => false,
            'slug_field' => 'slug',
            'slug_source' => 'title',
            'fields' => [
                'title' => ['type' => 'text', 'required' => true],
                'slug' => ['type' => 'slug', 'required' => true, 'unique' => true],
                'wordpress_id' => ['type' => 'number', 'required' => false],
                'wordpress_type' => ['type' => 'text', 'required' => false],
                'excerpt' => ['type' => 'textarea', 'required' => false],
                'content' => ['type' => 'textarea', 'required' => false],
                'featured_image' => ['type' => 'media', 'multiple' => false],
                'attachments' => ['type' => 'media', 'multiple' => true],
                'original_url' => ['type' => 'text', 'required' => false],
            ] + $acf_fields,
        ];

        $created = $this->client->create_content_type($schema);

        return is_wp_error($created) ? $created : true;
    }

    private function ensure_acf_options_schema(string $collection, array $page): bool|WP_Error
    {
        $acf_fields = $this->acf_schema_fields_for_sources([(string) $page['post_id']]);

        $existing = $this->client->get_content_type($collection);
        if (!is_wp_error($existing)) {
            $schema = is_array($existing['data'] ?? null) ? $existing['data'] : [];
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            $changed = [];

            foreach ($acf_fields as $key => $acf_field) {
                if (($fields[$key] ?? null) !== $acf_field) {
                    $changed[$key] = $acf_field;
                }
            }

            if ($changed === [] && !empty($schema['singleton'])) {
                return true;
            }

            $schema['singleton'] = true;
            $schema['fields'] = array_replace($fields, $changed);
            $updated = $this->client->update_content_type($collection, $schema);

            return is_wp_error($updated) ? $updated : true;
        }

        if ($acf_fields === []) {
            $acf_fields['acf'] = [
                'type' => 'json',
                'required' => false,
            ];
        }

        $error_data = $existing->get_error_data();
        if ((int) ($error_data['status'] ?? 0) !== 404) {
            return $existing;
        }

        $schema = [
            'name' => $collection,
            'label' => sprintf(__('WordPress ACF Options: %s', 'cometcms-migrator'), (string) $page['title']),
            'icon' => 'mdi:cog-outline',
            'singleton' => true,
            'slug_field' => 'slug',
            'slug_source' => 'title',
            'fields' => [
                'title' => ['type' => 'text', 'required' => true],
                'slug' => ['type' => 'slug', 'required' => true, 'unique' => true],
                'wordpress_type' => ['type' => 'text', 'required' => false],
                'acf_option_post_id' => ['type' => 'text', 'required' => false],
            ] + $acf_fields,
        ];

        $created = $this->client->create_content_type($schema);

        return is_wp_error($created) ? $created : true;
    }

    private function migrate_post(WP_Post $post, string $collection): array|WP_Error
    {
        $payload = $this->post_payload($post);
        $identifier = (string) $payload['slug'];
        $existing_id = (string) get_post_meta($post->ID, $this->meta_key($collection), true);
        $action = 'created';

        if (!empty($this->settings['update_existing'])) {
            $lookup = $existing_id !== '' ? $this->client->get_entry($collection, $existing_id) : $this->client->get_entry($collection, $identifier);
            if (!is_wp_error($lookup) && !empty($lookup['data']['id'])) {
                $updated = $this->client->update_entry($collection, (string) $lookup['data']['id'], $payload);
                if (is_wp_error($updated)) {
                    return $updated;
                }

                update_post_meta($post->ID, $this->meta_key($collection), (string) ($updated['data']['id'] ?? ''));
                return ['action' => 'updated', 'entry' => $updated['data'] ?? []];
            }
        }

        $created = $this->client->create_entry($collection, $payload);
        if (is_wp_error($created)) {
            return $created;
        }

        update_post_meta($post->ID, $this->meta_key($collection), (string) ($created['data']['id'] ?? ''));

        return ['action' => $action, 'entry' => $created['data'] ?? []];
    }

    private function migrate_acf_option_page(array $page, string $collection): array|WP_Error
    {
        $payload = $this->acf_option_page_payload($page, $collection);
        $updated = $this->client->update_entry($collection, $collection, $payload);
        if (!is_wp_error($updated)) {
            $verified = $this->verify_acf_option_page_entry($collection, $payload, $updated);

            return is_wp_error($verified) ? $verified : ['action' => 'updated', 'entry' => $verified['data'] ?? []];
        }

        $update_error_data = $updated->get_error_data();
        if ((int) ($update_error_data['status'] ?? 0) !== 404) {
            return $updated;
        }

        $created = $this->client->create_entry($collection, $payload);
        if (is_wp_error($created)) {
            return $created;
        }

        $verified = $this->verify_acf_option_page_entry($collection, $payload, $created);

        return is_wp_error($verified) ? $verified : ['action' => 'created', 'entry' => $verified['data'] ?? []];
    }

    private function verify_acf_option_page_entry(string $collection, array $payload, array $result): array|WP_Error
    {
        if ($this->acf_option_page_entry_has_payload($result, $payload)) {
            return $result;
        }

        $fetched = $this->client->get_entry($collection, $collection);
        if (!is_wp_error($fetched) && $this->acf_option_page_entry_has_payload($fetched, $payload)) {
            return $fetched;
        }

        $retry = $this->client->update_entry($collection, $collection, $payload);
        if (!is_wp_error($retry) && $this->acf_option_page_entry_has_payload($retry, $payload)) {
            return $retry;
        }

        if (is_wp_error($retry)) {
            return $retry;
        }

        return new WP_Error(
            'cometcms_acf_options_entry_missing',
            __('CometCMS accepted the option page migration request, but the singleton entry still did not contain the migrated fields.', 'cometcms-migrator'),
            ['collection' => $collection]
        );
    }

    private function acf_option_page_entry_has_payload(array $result, array $payload): bool
    {
        $entry = is_array($result['data'] ?? null) ? $result['data'] : [];
        $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];

        foreach (['wordpress_type', 'acf_option_post_id'] as $field) {
            if (array_key_exists($field, $entry) && (string) $entry[$field] === (string) ($payload[$field] ?? '')) {
                continue;
            }

            if (array_key_exists($field, $data) && (string) $data[$field] === (string) ($payload[$field] ?? '')) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function acf_option_page_payload(array $page, string $collection): array
    {
        $payload = [
            'title' => (string) $page['title'],
            'slug' => $collection,
            'status' => 'published',
            'wordpress_type' => 'acf_options_page',
            'acf_option_post_id' => (string) $page['post_id'],
        ];

        return array_replace($payload, $this->acf_payload_for_source((string) $page['post_id']));
    }

    private function post_payload(WP_Post $post): array
    {
        $featured = [];
        $attachments = [];

        if (!empty($this->settings['migrate_media'])) {
            $featured_id = get_post_thumbnail_id($post);
            if ($featured_id) {
                $filename = $this->upload_attachment((int) $featured_id);
                if ($filename !== '') {
                    $featured[] = $filename;
                }
            }

            foreach (get_attached_media('', $post->ID) as $attachment) {
                $filename = $this->upload_attachment((int) $attachment->ID);
                if ($filename !== '' && !in_array($filename, $featured, true)) {
                    $attachments[] = $filename;
                }
            }
        }

        $payload = [
            'title' => html_entity_decode(get_the_title($post), ENT_QUOTES, get_bloginfo('charset')),
            'slug' => $post->post_name ?: sanitize_title($post->post_title),
            'status' => $this->map_status($post->post_status),
            'published_at' => $post->post_date_gmt !== '0000-00-00 00:00:00' ? gmdate('c', strtotime($post->post_date_gmt)) : null,
            'wordpress_id' => (int) $post->ID,
            'wordpress_type' => (string) $post->post_type,
            'excerpt' => wp_strip_all_tags((string) ($post->post_excerpt ?: wp_trim_words($post->post_content, 55, ''))),
            'content' => apply_filters('the_content', $post->post_content),
            'featured_image' => $featured,
            'attachments' => array_values(array_unique($attachments)),
            'original_url' => get_permalink($post),
        ];

        return array_replace($payload, $this->acf_payload($post));
    }

    private function upload_attachment(int $attachment_id): string
    {
        if (isset($this->media_cache[$attachment_id])) {
            return $this->media_cache[$attachment_id];
        }

        $path = get_attached_file($attachment_id);
        if (!is_string($path) || $path === '' || !is_readable($path)) {
            $this->media_cache[$attachment_id] = '';
            return '';
        }

        $filename = basename($path);
        $uploaded = $this->client->upload_media($path, $filename, (string) ($this->settings['media_category'] ?? ''));
        if (is_wp_error($uploaded) || empty($uploaded['data'][0]['filename'])) {
            $this->media_cache[$attachment_id] = '';
            return '';
        }

        $stored = (string) $uploaded['data'][0]['filename'];
        $alt = (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $title = (string) get_the_title($attachment_id);

        if ($alt !== '' || $title !== '') {
            $this->client->update_media_meta($stored, $alt, $title);
        }

        $this->media_cache[$attachment_id] = $stored;

        return $stored;
    }

    private function acf_available(): bool
    {
        return function_exists('get_field_objects');
    }

    private function acf_schema_fields(array $posts): array
    {
        if (empty($this->settings['migrate_acf']) || !$this->acf_available()) {
            return [];
        }

        $sources = [];

        foreach ($posts as $post) {
            if ($post instanceof WP_Post) {
                $sources[] = (int) $post->ID;
            }
        }

        return $this->acf_schema_fields_for_sources($sources);
    }

    private function acf_schema_fields_for_sources(array $sources): array
    {
        if (empty($this->settings['migrate_acf']) || !$this->acf_available()) {
            return [];
        }

        $fields = [];

        foreach ($sources as $source) {
            foreach ($this->acf_field_objects_for_source($source, false) as $field) {
                $key = $this->acf_payload_key($field);
                if ($key === '' || isset($fields[$key])) {
                    continue;
                }

                $fields[$key] = $this->acf_field_schema($field);
            }
        }

        return $fields;
    }

    private function acf_payload(WP_Post $post): array
    {
        if (empty($this->settings['migrate_acf']) || !$this->acf_available()) {
            return [];
        }

        return $this->acf_payload_for_source($post->ID);
    }

    private function acf_payload_for_source(int|string $source): array
    {
        if (empty($this->settings['migrate_acf']) || !$this->acf_available()) {
            return [];
        }

        $payload = [];
        $raw = [];

        foreach ($this->acf_field_objects_for_source($source, true) as $field) {
            $key = $this->acf_payload_key($field);
            if ($key === '') {
                continue;
            }

            $value = $this->acf_field_source_value($field, $source);
            $payload[$key] = $this->acf_field_value($value, $field);
            $raw[(string) ($field['name'] ?? $key)] = $this->acf_json_value($value);
        }

        if ($raw !== [] && !array_key_exists('acf', $payload)) {
            $payload['acf'] = $raw;
        }

        return $payload;
    }

    private function acf_field_objects_for_source(int|string $source, bool $load_value): array
    {
        if (!$this->acf_available()) {
            return [];
        }

        return self::acf_field_objects_for_raw_source($source, $load_value);
    }

    private function acf_field_source_value(array $field, int|string $source): mixed
    {
        if (!function_exists('get_field')) {
            return $field['value'] ?? null;
        }

        $field_ref = (string) ($field['key'] ?? $field['name'] ?? '');
        $name = (string) ($field['name'] ?? '');
        $sources = [$source];

        if (in_array((string) $source, ['option', 'options'], true)) {
            $sources = array_values(array_unique([(string) $source, 'option', 'options']));
        }

        foreach ($sources as $candidate_source) {
            if ($field_ref !== '') {
                $value = get_field($field_ref, $candidate_source, true);
                if ($value !== null) {
                    return $value;
                }
            }

            if ($name !== '' && $name !== $field_ref) {
                $value = get_field($name, $candidate_source, true);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return $field['value'] ?? null;
    }

    private function acf_payload_key(array $field): string
    {
        $name = sanitize_key((string) ($field['name'] ?? ''));
        if ($name === '') {
            return '';
        }

        $reserved = [
            'title',
            'slug',
            'status',
            'published_at',
            'wordpress_id',
            'wordpress_type',
            'excerpt',
            'content',
            'featured_image',
            'attachments',
            'original_url',
        ];

        return in_array($name, $reserved, true) ? 'acf_' . $name : $name;
    }

    private function acf_field_schema(array $field): array
    {
        $type = (string) ($field['type'] ?? 'text');
        $label = trim((string) ($field['label'] ?? $field['name'] ?? ''));

        if ($type === 'repeater') {
            $subfields = $this->acf_repeater_subfields($field);
            $schema = $subfields !== []
                ? ['type' => 'repeater', 'subfields' => $subfields]
                : ['type' => 'json'];

            if ($label !== '') {
                $schema['label'] = $label;
            }

            return $schema;
        }

        $schema = match ($type) {
            'textarea', 'wysiwyg' => ['type' => 'textarea'],
            'number' => ['type' => 'number'],
            'range' => array_filter([
                'type' => 'range',
                'min' => is_numeric($field['min'] ?? null) ? (float) $field['min'] : null,
                'max' => is_numeric($field['max'] ?? null) ? (float) $field['max'] : null,
                'step' => is_numeric($field['step'] ?? null) ? (float) $field['step'] : null,
            ], static fn(mixed $value): bool => $value !== null),
            'true_false' => ['type' => 'boolean'],
            'select', 'radio', 'button_group', 'checkbox' => [
                'type' => 'select',
                'multiple' => $type === 'checkbox' || !empty($field['multiple']),
                'options' => $this->acf_select_options($field),
            ],
            'date_picker' => ['type' => 'date'],
            'date_time_picker' => ['type' => 'datetime'],
            'color_picker' => ['type' => 'color'],
            'image', 'file' => ['type' => 'media', 'multiple' => false],
            'gallery' => ['type' => 'media', 'multiple' => true],
            'flexible_content', 'group', 'clone', 'relationship', 'post_object', 'page_link', 'taxonomy', 'user', 'link' => ['type' => 'json'],
            default => ['type' => 'text'],
        };

        if ($label !== '') {
            $schema['label'] = $label;
        }

        $this->apply_acf_field_description($schema, $field);

        return $schema;
    }

    private function acf_repeater_subfields(array $field): array
    {
        $subfields = [];

        foreach (is_array($field['sub_fields'] ?? null) ? $field['sub_fields'] : [] as $subfield) {
            if (!is_array($subfield)) {
                continue;
            }

            $key = sanitize_key((string) ($subfield['name'] ?? ''));
            if ($key === '') {
                continue;
            }

            $schema = $this->acf_repeater_subfield_schema($subfield);
            $schema['key'] = $key;
            $subfields[] = $schema;
        }

        return $subfields;
    }

    private function acf_repeater_subfield_schema(array $field): array
    {
        $type = (string) ($field['type'] ?? 'text');
        $label = trim((string) ($field['label'] ?? $field['name'] ?? ''));

        $schema = match ($type) {
            'textarea', 'wysiwyg' => ['type' => 'textarea'],
            'number' => ['type' => 'number'],
            'range' => array_filter([
                'type' => 'range',
                'min' => is_numeric($field['min'] ?? null) ? (float) $field['min'] : null,
                'max' => is_numeric($field['max'] ?? null) ? (float) $field['max'] : null,
                'step' => is_numeric($field['step'] ?? null) ? (float) $field['step'] : null,
            ], static fn(mixed $value): bool => $value !== null),
            'true_false' => ['type' => 'boolean'],
            'select', 'radio', 'button_group', 'checkbox' => [
                'type' => 'select',
                'multiple' => $type === 'checkbox' || !empty($field['multiple']),
                'options' => $this->acf_select_options($field),
            ],
            'date_picker' => ['type' => 'date'],
            'date_time_picker' => ['type' => 'datetime'],
            'color_picker' => ['type' => 'color'],
            'image', 'file' => ['type' => 'media', 'multiple' => false],
            'gallery' => ['type' => 'media', 'multiple' => true],
            default => ['type' => in_array($type, ['flexible_content', 'repeater', 'group', 'clone', 'relationship', 'post_object', 'page_link', 'taxonomy', 'user', 'link'], true) ? 'json' : 'text'],
        };

        if ($label !== '') {
            $schema['label'] = $label;
        }

        $this->apply_acf_field_description($schema, $field);

        return $schema;
    }

    private function apply_acf_field_description(array &$schema, array $field): void
    {
        $description = trim(wp_strip_all_tags((string) ($field['instructions'] ?? '')));
        if ($description !== '') {
            $schema['description'] = $description;
        }
    }

    private function acf_select_options(array $field): array
    {
        $choices = is_array($field['choices'] ?? null) ? $field['choices'] : [];
        $options = [];

        foreach ($choices as $value => $label) {
            $options[(string) $value] = is_scalar($label) ? (string) $label : (string) $value;
        }

        return $options;
    }

    private function acf_field_value(mixed $value, array $field): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        return match ($type) {
            'number', 'range' => is_numeric($value) ? (float) $value : null,
            'true_false' => (bool) $value,
            'checkbox' => array_values(array_map('strval', is_array($value) ? $value : [])),
            'select' => !empty($field['multiple'])
                ? array_values(array_map('strval', is_array($value) ? $value : []))
                : (is_array($value) ? (string) reset($value) : (string) $value),
            'date_picker' => $this->acf_date_value($value, $field),
            'date_time_picker' => $this->acf_datetime_value($value, $field),
            'image', 'file' => $this->acf_media_value($value, false),
            'gallery' => $this->acf_media_value($value, true),
            'repeater' => $this->acf_repeater_value($value, $field),
            'flexible_content', 'group', 'clone', 'relationship', 'post_object', 'page_link', 'taxonomy', 'user', 'link' => $this->acf_json_value($value),
            default => is_scalar($value) || $value === null ? $value : $this->acf_json_value($value),
        };
    }

    private function acf_repeater_value(mixed $value, array $field): array
    {
        if (!is_array($value)) {
            return [];
        }

        $subfields = is_array($field['sub_fields'] ?? null) ? $field['sub_fields'] : [];

        if ($subfields === []) {
            return array_values(array_filter(array_map(
                fn(mixed $row): mixed => $this->acf_json_value($row),
                $value
            ), 'is_array'));
        }

        $rows = [];

        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mapped = [];

            foreach ($subfields as $subfield) {
                if (!is_array($subfield)) {
                    continue;
                }

                $name = (string) ($subfield['name'] ?? '');
                $key = sanitize_key($name);
                if ($key === '') {
                    continue;
                }

                $mapped[$key] = $this->acf_field_value($row[$name] ?? $row[$key] ?? null, $subfield);
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    private function acf_media_value(mixed $value, bool $multiple): array
    {
        if (empty($this->settings['migrate_media'])) {
            return [];
        }

        $items = $multiple ? (is_array($value) ? $value : []) : [$value];
        $files = [];

        foreach ($items as $item) {
            $attachment_id = $this->acf_attachment_id($item);
            if ($attachment_id <= 0) {
                continue;
            }

            $filename = $this->upload_attachment($attachment_id);
            if ($filename !== '') {
                $files[] = $filename;
            }
        }

        return array_values(array_unique($files));
    }

    private function acf_attachment_id(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_array($value) && is_numeric($value['ID'] ?? null)) {
            return (int) $value['ID'];
        }

        if (is_array($value) && is_numeric($value['id'] ?? null)) {
            return (int) $value['id'];
        }

        if (is_array($value) && is_string($value['url'] ?? null)) {
            return (int) attachment_url_to_postid((string) $value['url']);
        }

        if (is_string($value) && preg_match('#^https?://#i', $value)) {
            return (int) attachment_url_to_postid($value);
        }

        return 0;
    }

    private function acf_date_value(mixed $value, array $field): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{8}$/', $raw)) {
            return substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
        }

        $date = $this->acf_parse_datetime($raw, array_merge($this->acf_date_formats($field), [
            'Y-m-d',
            'd.m.Y',
            'd/m/Y',
            'm/d/Y',
            'F j, Y',
            'j F Y',
        ]));

        return $date !== null ? $date->format('Y-m-d') : null;
    }

    private function acf_datetime_value(mixed $value, array $field): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $date = $this->acf_parse_datetime($raw, array_merge($this->acf_date_formats($field), [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'd.m.Y H:i:s',
            'd.m.Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y g:i a',
            'd/m/Y g:i A',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'm/d/Y g:i a',
            'm/d/Y g:i A',
            'F j, Y g:i a',
            'F j, Y g:i A',
            'j F Y H:i',
        ]));

        return $date !== null ? $date->format('Y-m-d\TH:i') : null;
    }

    private function acf_parse_datetime(string $raw, array $formats): ?DateTimeImmutable
    {
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $raw);

            if ($date instanceof DateTimeImmutable) {
                $errors = DateTimeImmutable::getLastErrors();
                if (($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0)) && $date->format($format) === $raw) {
                    return $date;
                }
            }
        }

        $time = strtotime($raw);

        return $time === false ? null : (new DateTimeImmutable('@' . $time))->setTimezone($this->wordpress_timezone());
    }

    private function acf_date_formats(array $field): array
    {
        $formats = [];

        foreach (['return_format', 'display_format'] as $key) {
            $format = trim((string) ($field[$key] ?? ''));
            if ($format !== '') {
                $formats[] = $format;
            }
        }

        return array_values(array_unique($formats));
    }

    private function wordpress_timezone(): DateTimeZone
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        return new DateTimeZone(wp_timezone_string() ?: 'UTC');
    }

    private function acf_json_value(mixed $value): mixed
    {
        if ($value instanceof WP_Post) {
            return [
                'id' => (int) $value->ID,
                'type' => (string) $value->post_type,
                'title' => get_the_title($value),
                'slug' => (string) $value->post_name,
                'url' => get_permalink($value),
            ];
        }

        if ($value instanceof WP_Term) {
            return [
                'id' => (int) $value->term_id,
                'taxonomy' => (string) $value->taxonomy,
                'name' => (string) $value->name,
                'slug' => (string) $value->slug,
            ];
        }

        if ($value instanceof WP_User) {
            return [
                'id' => (int) $value->ID,
                'name' => (string) $value->display_name,
                'login' => (string) $value->user_login,
            ];
        }

        if (is_array($value)) {
            $mapped = [];
            foreach ($value as $key => $item) {
                $mapped[$key] = $this->acf_json_value($item);
            }

            return array_is_list($mapped) ? array_values($mapped) : $mapped;
        }

        return $value;
    }

    private function collection_for(string $post_type): string
    {
        return sanitize_title((string) ($this->settings['collections'][$post_type] ?? ''));
    }

    private function is_acf_option_type(string $type): bool
    {
        return str_starts_with($type, self::ACF_OPTIONS_PREFIX);
    }

    private function acf_option_slug_from_type(string $type): string
    {
        return sanitize_key(substr($type, strlen(self::ACF_OPTIONS_PREFIX)));
    }

    private function enabled_acf_option_pages(): array
    {
        if (empty($this->settings['migrate_acf']) || !$this->acf_available()) {
            return [];
        }

        $enabled = array_flip(array_map('sanitize_key', (array) ($this->settings['acf_option_pages'] ?? [])));
        if ($enabled === []) {
            return [];
        }

        return array_values(array_filter(self::available_acf_option_pages(), static function (array $page) use ($enabled): bool {
            return isset($enabled[(string) $page['slug']]);
        }));
    }

    private function acf_option_page_for_type(string $type): ?array
    {
        $slug = $this->acf_option_slug_from_type($type);

        foreach ($this->enabled_acf_option_pages() as $page) {
            if ((string) $page['slug'] === $slug) {
                return $page;
            }
        }

        return null;
    }

    private function acf_option_collection_for(string $page_slug): string
    {
        return sanitize_title((string) ($this->settings['acf_option_collections'][$page_slug] ?? ''));
    }

    private static function acf_field_objects_for_raw_source(int|string $source, bool $load_value): array
    {
        $fields = get_field_objects($source, true, $load_value);

        return is_array($fields) ? array_values(array_filter($fields, 'is_array')) : [];
    }

    private function map_status(string $status): string
    {
        return match ($status) {
            'publish' => 'published',
            'private' => 'protected',
            default => 'draft',
        };
    }

    private function meta_key(string $collection): string
    {
        return '_cometcms_migration_' . md5((string) ($this->settings['workspace'] ?? '') . '|' . $collection);
    }
}
