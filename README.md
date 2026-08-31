# Astuteo Pulse

Only useful for Astuteo clients. Decoupled from our Toolkit. Connecting Astuteo client sites to our monitor. Heavily "inspired" by Viget's module.

The plugin exposes the site's status as JSON. Our monitor polls it; the site pushes nothing.

## Setup

Add a key to the server's `.env`:

```
ASTUTEO_API_KEY="..."
```

Requests without a matching key get an error response and no data.

## Endpoints

| Route | Returns |
|---|---|
| `/astuteo-pulse` | The report as a JSON string |
| `/astuteo-pulse/json` | The report as a JSON response |

Send the key in the `X-Astuteo-Pulse-Key` header:

```
curl -H "X-Astuteo-Pulse-Key: $ASTUTEO_API_KEY" https://example.com/astuteo-pulse/json
```

A `?key=` query parameter is still accepted so sites and the monitor can update independently. It is deprecated and will be removed: query strings end up in web server and proxy access logs, so the header is the only position that keeps the key out of them. When both are present, the header wins.

## Host block

`data.host` reports whether the server needs a reboot. It reads files the web user can already read, so it needs no cron, no root, and no per-server setup.

| Field | Meaning |
|---|---|
| `reboot_pending` | Whether an update is staged and waiting on a reboot |
| `reboot_pending_since` | When that reboot became necessary |
| `auto_updates_enabled` | Whether the host applies updates on its own |
| `last_check_at` | When the host last checked for updates |
| `host_id` | Opaque per-machine identifier, so sites sharing a server are recognizable as one host |
| `unknown` | List of `{field, reason}` for anything that could not be read |

Every value is a boolean, a timestamp, or an opaque identifier. No OS version, package name, or update count is reported.

`unknown` is a list rather than a map so its JSON type does not change between polls: an empty PHP map encodes as `[]` and a populated one as `{}`, which would flip the type on exactly the healthy host.

A field is only `false` when the host actually said so. When a source is missing or unreadable, the field is `null` and `unknown` carries the reason. This matters most for `reboot_pending`: a host without `update-notifier-common` can never raise the reboot flag, so it reports unknown rather than reporting that no reboot is needed. The same applies when the flag's directory cannot be searched.

Reasons are additive; today the set is:

| Reason | Meaning |
|---|---|
| `reboot-notifier-absent` | `update-notifier-common` is not installed, so the flag can never appear |
| `source-missing` | The file does not exist |
| `source-unreadable` | The file or its directory exists but could not be read |
| `config-unparseable` | `20auto-upgrades` has no active `Unattended-Upgrade` directive |
| `machine-id-empty` | `/etc/machine-id` is empty |
| `machine-id-invalid` | `/etc/machine-id` is not a 32-character hex ID, including systemd's `uninitialized` |
| `reader-failed` | The reader threw and the whole block degraded |

`auto_updates_enabled` reads only `20auto-upgrades`, and takes the last matching directive because that is what apt does. A drop-in later in `apt.conf.d` that overrides it is not currently detected.

`host_id` is an HMAC of the machine ID, never the machine ID itself, which systemd requires not be exposed on a network.

## Tests

```
composer install
composer test
```

The host reader takes a root path, so the suite runs against temporary directories shaped like a real host. No server is needed.
