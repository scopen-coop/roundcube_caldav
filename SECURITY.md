# Security Policy

## Reporting a vulnerability

If you discover a security issue in this plugin, please **do not** open a public GitHub issue
with exploit details.

Instead, report it privately using one of the following channels:

- [GitHub Security Advisories](https://github.com/scopen-coop/roundcube_caldav/security/advisories/new)
  (preferred)
- Contact the maintainers through [Scopen](https://www.scopen.fr/)

Please include:

- A description of the issue and its impact
- Steps to reproduce
- Affected versions (Roundcube, plugin, PHP)
- A proof of concept or patch, if available

We aim to acknowledge reports within **5 business days** and to provide a fix or mitigation plan
as soon as possible.

## Supported versions

Security fixes are provided for the latest release of the plugin running on
[Roundcube versions still supported by the Roundcube project](doc/SUPPORTED_ENVIRONMENT.md).

| Version | Supported |
| ------- | --------- |
| Latest release | Yes |
| Older releases | Best effort only |

Always run a supported Roundcube version and keep plugin dependencies up to date with
`composer update` inside the plugin directory.

## Scope

### In scope

- Vulnerabilities in this plugin's PHP, JavaScript, or CSS code
- Issues in how the plugin handles iCalendar data from email attachments
- Weaknesses in how CalDAV credentials are stored or used
- Insecure defaults in plugin configuration or documentation

### Out of scope

- Vulnerabilities in Roundcube core (report to the [Roundcube project](https://github.com/roundcube/roundcubemail))
- Vulnerabilities in the CalDAV server (Nextcloud, Baikal, etc.)
- Issues that require compromising the Roundcube host, database, or mail account outside this plugin
- Missing security headers or TLS configuration on the web server or reverse proxy

## Security model

This plugin extends Roundcube Webmail. It inherits Roundcube's authentication and session
management: plugin actions run in the context of a logged-in Roundcube user.

The plugin:

- Connects to a user-configured remote CalDAV server
- Reads calendar data from that server
- Parses `text/calendar` attachments from the user's mailbox
- Writes events back to CalDAV and sends iTIP replies by email

Each user configures their own CalDAV URL, login, and password in
**Settings > Setup CalDav**.

## Sensitive data

### CalDAV credentials

CalDAV passwords are stored in Roundcube user preferences, encrypted with AES-256-CTR using
Roundcube's installation key (`$config['des_key']`).

Implications:

- Anyone with access to the Roundcube database and `des_key` can decrypt stored CalDAV passwords
- If `des_key` changes (for example after rebuilding a Docker container without persisting
  configuration), stored passwords become unusable until the user re-enters them
- Roundcube administrators should be treated as trusted parties for credential confidentiality,
  similar to other plugins that store external account passwords

Recommendations for administrators:

- Persist `config.inc.php` (including a fixed `des_key`) across container rebuilds
- Restrict database and filesystem access to Roundcube configuration
- Use HTTPS for all Roundcube and CalDAV traffic

### Calendar and mail content

The plugin processes calendar invitations received by email. That content is **untrusted**
(it may come from arbitrary senders) and is displayed in the web interface.

Event fields such as summary, description, location, attendee names, and comments are rendered
using safe DOM APIs (`.text()`, `createTextNode`) to prevent HTML/JavaScript injection.

If you find a field that is rendered unsafely, please report it as a security issue.

## Network security

### CalDAV connections

The plugin connects to the URL configured by each user. When writing events via direct HTTP PUT,
TLS certificate verification is enabled (`CURLOPT_SSL_VERIFYPEER` and
`CURLOPT_SSL_VERIFYHOST`).

The underlying `simple-caldav-client` library may use different TLS settings for discovery
requests. Deploy CalDAV servers with valid certificates and prefer HTTPS URLs in user settings.

### User-controlled URLs

Because users supply the CalDAV base URL, a compromised Roundcube account could point the plugin
at an attacker-controlled server. This is expected behaviour for a client-side CalDAV connector
and is mitigated by standard Roundcube account security.

## Administrator checklist

- Run Roundcube and this plugin over HTTPS
- Keep Roundcube, PHP, and plugin dependencies updated
- Persist `$config['des_key']` when using Docker or ephemeral deployments
- Use correct CalDAV URLs (see [INSTALL.md](doc/INSTALL.md), especially for Nextcloud)
- Restrict access to Roundcube logs, which may contain error details
- Remove or disable the Xdebug `ini_set` directives in production if they were left enabled
  in `roundcube_caldav.php`

## Known considerations

| Topic | Notes |
| ----- | ----- |
| Email-borne iCalendar | Treat all invitation attachments as untrusted input |
| Stored credentials | Encrypted at rest, but recoverable by admins with DB + `des_key` |
| Docker rebuilds | Regenerating `des_key` invalidates stored CalDAV passwords |
| Nextcloud URL format | An incorrect base URL can cause write failures; see [issue #12](https://github.com/scopen-coop/roundcube_caldav/issues/12) |

## Acknowledgments

We thank researchers and users who report security issues responsibly. Credit will be given in
release notes when fixes are published, unless you prefer to remain anonymous.
