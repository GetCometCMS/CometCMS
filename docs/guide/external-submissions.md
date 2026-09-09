# External Submissions

External submissions let a public form create draft entries without exposing a content-write API token in the browser. The gateway is opt-in per collection and uses the collection schema as its validation contract.

## Configure a collection

Open a collection under **Content types**, then configure **External submissions**:

- Enable external submissions.
- Select only the fields the public form may send.
- Set the number of attempts allowed per visitor and the time window.
- Add allowed browser origins, one per line. Leave this empty for same-origin or server-to-server requests. Use `*` only for intentionally public browser access.
- Keep the `_gotcha` honeypot enabled unless another anti-spam layer replaces it.

Only `title`, text, textarea, markdown, number, range, boolean, select, date, datetime, and color fields can be exposed. Media, relation, slug, and other privileged or structured fields are excluded. Single-page content types do not support submissions.

Required fields remain required. If the collection has a required field that is not exposed and has no usable default, requests will fail validation.

## Submit from a client

Send JSON to the workspace-scoped submission endpoint:

```http
POST /api/v1/workspaces/default/content/contact-messages/submissions
Content-Type: application/json
Idempotency-Key: form-018f925d-acde-7b91

{
  "email": "jane@example.com",
  "message": "Could you tell me more?",
  "_gotcha": ""
}
```

```js
const response = await fetch(
  "https://cms.example.com/api/v1/workspaces/default/content/contact-messages/submissions",
  {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Idempotency-Key": crypto.randomUUID(),
    },
    body: JSON.stringify({ email, message, _gotcha: "" }),
  },
);

const { data } = await response.json();
```

An accepted request returns HTTP `202` with a non-secret receipt:

```json
{
  "data": {
    "accepted": true,
    "receipt": "sub_7k4p9xq2mr"
  }
}
```

The response deliberately does not echo submitted personal data. Entries appear in the normal content list with an **External** badge and are always created as drafts. Editors can review, edit, publish, archive, or delete them with the existing content workflow.

## Safety behavior

- No bearer token is accepted or required for the submission capability.
- Unknown and non-selected fields are rejected, including `status`, `slug`, IDs, and ownership fields.
- Request values pass through the normal collection validation and normalization.
- JSON bodies are bounded globally; oversized requests return `413`.
- Per-collection/per-IP and global fixed-window limits return `429` with `Retry-After` when exceeded.
- Browser origins are checked before accepting a cross-origin request. CORS is not an authentication mechanism; it complements the field allowlist and rate limits.
- A non-empty or malformed `_gotcha` receives the same generic `202` response but creates no entry, which avoids giving simple bots useful feedback.
- An optional `Idempotency-Key` of 8–128 safe characters makes retries from the same IP return the original receipt for the configured TTL.

CometCMS uses `REMOTE_ADDR` for visitor limits. If the CMS is behind a reverse proxy, configure that proxy and web server so PHP receives the real client address only from trusted proxy infrastructure. Do not trust a client-supplied forwarded header directly.

## Webhooks and storage

Accepted submissions emit `submission.received`. The ordinary `content.created` event is also emitted because the gateway creates a normal entry. See [Webhooks](./webhooks).

Internally, entries retain `entry_origin: "external"` and submission metadata so the admin can distinguish their source. This metadata is not exposed by anonymous content reads.

## Global limits

Deployment-wide bounds live under `security.external_submissions` in `config/config.php`:

| Setting | Default | Purpose |
| --- | ---: | --- |
| `max_body_bytes` | `65536` | Maximum request body size |
| `global_rate_limit_attempts` | `500` | Attempts allowed across the gateway window |
| `global_rate_limit_window_seconds` | `600` | Global window length |
| `max_rate_limit_records` | `10000` | Bound for stored rate-limit buckets |
| `idempotency_ttl_seconds` | `86400` | How long retry receipts are retained |
| `max_idempotency_records` | `10000` | Bound for retained retry receipts |

These application limits complement web-server request limits and an upstream CDN or firewall. For hostile public traffic, use both layers.
