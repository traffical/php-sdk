# Security Policy

## Supported versions

The Traffical PHP SDK is pre-1.0; security fixes are applied to the latest
released minor. Pin a version range you can update promptly.

| Version | Supported |
|---------|-----------|
| 0.2.x   | ✅        |
| < 0.2   | ❌        |

## Reporting a vulnerability

Please report suspected vulnerabilities privately — do **not** open a public
GitHub issue for security reports.

- Email **security@traffical.io** with a description, affected version(s), and
  reproduction steps or a proof of concept.
- You will receive an acknowledgement within 3 business days.
- Please allow a reasonable disclosure window before any public disclosure; we
  will coordinate a fix and release, and credit reporters who wish to be named.

## Handling of secrets

- The SDK API key is passed as a constructor option and sent only as a `Bearer`
  authorization header to the configured `baseUrl`. Never commit SDK keys to
  source control; load them from environment/secret storage.
- On an HTTP `401` the event transport permanently disables delivery for the
  process (dropping buffered events) rather than resending a credential that
  will not succeed.
- Context fields are only included on events when a matched policy's
  `contextLogging.allowedFields` opts them in; avoid placing secrets or PII in
  evaluation context beyond what you intend to log.

## Scope

This policy covers the code in this repository. Vulnerabilities in the Traffical
control plane or other SDKs should be reported through the same contact.
