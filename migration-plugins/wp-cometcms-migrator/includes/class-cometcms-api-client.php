<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CometCMS_Migrator_Api_Client
{
    private string $base_url;
    private string $workspace;
    private string $api_key;
    private int $timeout;

    public function __construct(array $settings)
    {
        $this->base_url = self::normalize_base_url((string) ($settings['base_url'] ?? ''));
        $this->workspace = sanitize_title((string) ($settings['workspace'] ?? 'default'));
        $this->api_key = (string) ($settings['api_key'] ?? '');
        $this->timeout = max(10, (int) ($settings['timeout'] ?? 60));
    }

    public static function normalize_base_url(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return untrailingslashit($url);
        }

        $base = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base .= ':' . (int) $parts['port'];
        }

        $path = (string) ($parts['path'] ?? '');
        $path = preg_replace('#/(admin|api|mcp)(/.*)?$#', '', $path) ?? $path;

        return untrailingslashit($base . $path);
    }

    public function health(): array|WP_Error
    {
        return $this->request('GET', '/health');
    }

    public function get_content_type(string $collection): array|WP_Error
    {
        return $this->request('GET', '/content-types/' . rawurlencode($collection));
    }

    public function create_content_type(array $schema): array|WP_Error
    {
        return $this->request('POST', '/content-types', $schema);
    }

    public function update_content_type(string $collection, array $schema): array|WP_Error
    {
        return $this->request('PUT', '/content-types/' . rawurlencode($collection), $schema);
    }

    public function get_entry(string $collection, string $identifier): array|WP_Error
    {
        return $this->request('GET', '/content/' . rawurlencode($collection) . '/' . rawurlencode($identifier));
    }

    public function create_entry(string $collection, array $payload): array|WP_Error
    {
        return $this->request('POST', '/content/' . rawurlencode($collection), $payload);
    }

    public function update_entry(string $collection, string $identifier, array $payload): array|WP_Error
    {
        return $this->request('PUT', '/content/' . rawurlencode($collection) . '/' . rawurlencode($identifier), $payload);
    }

    public function upload_media(string $path, string $filename, string $category = ''): array|WP_Error
    {
        if (!is_readable($path)) {
            return new WP_Error('cometcms_file_unreadable', __('The media file is not readable.', 'cometcms-migrator'));
        }

        $filetype = wp_check_filetype($filename);
        $mime = (string) ($filetype['type'] ?? '');
        $contents = file_get_contents($path);
        if ($contents === false) {
            return new WP_Error('cometcms_file_unreadable', __('The media file could not be read.', 'cometcms-migrator'));
        }

        $parts = [
            [
                'name' => 'media[]',
                'filename' => $filename,
                'type' => $mime !== '' ? $mime : 'application/octet-stream',
                'contents' => $contents,
            ],
        ];

        if ($category !== '') {
            $parts[] = [
                'name' => 'category',
                'contents' => $category,
            ];
        }

        return $this->request('POST', '/media', $parts, true);
    }

    public function update_media_meta(string $filename, string $alt, string $title): array|WP_Error
    {
        return $this->request('PUT', '/media/' . rawurlencode($filename) . '/meta', [
            'alt' => $alt,
            'title' => $title,
        ]);
    }

    private function request(string $method, string $path, ?array $body = null, bool $multipart = false): array|WP_Error
    {
        if ($this->base_url === '' || $this->workspace === '' || $this->api_key === '') {
            return new WP_Error('cometcms_missing_settings', __('CometCMS URL, workspace, and API key are required.', 'cometcms-migrator'));
        }

        $url = $this->base_url . '/api/v1/workspaces/' . rawurlencode($this->workspace) . $path;
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept' => 'application/json',
            ],
        ];

        if ($body !== null) {
            if ($multipart) {
                $multipart_body = $this->multipart_body($body);
                $args['headers']['Content-Type'] = 'multipart/form-data; boundary=' . $multipart_body['boundary'];
                $args['body'] = $multipart_body['body'];
            } else {
                $args['headers']['Content-Type'] = 'application/json';
                $args['body'] = wp_json_encode($body);
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = $raw !== '' ? json_decode($raw, true) : [];

        if (!is_array($decoded)) {
            return new WP_Error('cometcms_invalid_response', __('CometCMS returned invalid JSON.', 'cometcms-migrator'), ['status' => $status, 'body' => $raw]);
        }

        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['error']['message'] ?? sprintf(__('CometCMS request failed with HTTP %d.', 'cometcms-migrator'), $status));
            $fields = is_array($decoded['error']['fields'] ?? null) ? $decoded['error']['fields'] : [];
            if ($fields !== []) {
                $field_messages = [];
                foreach ($fields as $field => $field_message) {
                    $field_messages[] = sprintf('%s: %s', (string) $field, is_scalar($field_message) ? (string) $field_message : wp_json_encode($field_message));
                }

                $message .= ' ' . sprintf(__('Fields: %s', 'cometcms-migrator'), implode('; ', $field_messages));
            }
            if ($status >= 500) {
                $message .= ' ' . sprintf(__('Endpoint: %s', 'cometcms-migrator'), $path);
            }
            $code = (string) ($decoded['error']['code'] ?? 'cometcms_http_error');

            return new WP_Error($code, $message, ['status' => $status, 'response' => $decoded]);
        }

        return $decoded;
    }

    private function multipart_body(array $parts): array
    {
        $boundary = 'cometcms-' . wp_generate_uuid4();
        $body = '';

        foreach ($parts as $part) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . addcslashes((string) $part['name'], "\"\\") . '"';

            if (!empty($part['filename'])) {
                $body .= '; filename="' . addcslashes((string) $part['filename'], "\"\\") . '"' . "\r\n";
                $body .= 'Content-Type: ' . (string) ($part['type'] ?? 'application/octet-stream') . "\r\n";
            } else {
                $body .= "\r\n";
            }

            $body .= "\r\n" . (string) ($part['contents'] ?? '') . "\r\n";
        }

        $body .= '--' . $boundary . "--\r\n";

        return [
            'boundary' => $boundary,
            'body' => $body,
        ];
    }
}
