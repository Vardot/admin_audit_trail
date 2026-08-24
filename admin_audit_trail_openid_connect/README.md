# Admin Audit Trail OpenID Connect

Admin Audit Trail OpenID Connect is a Drupal module that extends the Admin Audit Trail module by logging OpenID Connect authentication activities, specifically Active Directory logins via the Windows AAD plugin.

Provides audit tracking for OpenID Connect based authentication events, helping administrators maintain detailed security records of SSO authentication.

## Features

- **Active Directory Login Tracking**: Logs successful logins via OpenID Connect Windows AAD plugin
- **Detailed Event Descriptions**: Provides human-readable descriptions of OpenID Connect authentication operations
- **User Identification**: Records user display name and user ID for easy reference and tracking
- **Integration with Admin Audit Trail**: Seamlessly integrates with the Admin Audit Trail module for centralized audit logging

## Requirements

- Drupal
- Admin Audit Trail module (`admin_audit_trail`)
- OpenID Connect module (`openid_connect`)
- OpenID Connect Windows AAD plugin (`openid_connect_windows_aad`)

## Installation

1. Download or clone this module into your Drupal `modules` directory

2. Enable the module via the Drupal admin interface or using Drush:

```bash
drush en admin_audit_trail_openid_connect
```

3. Ensure the Admin Audit Trail module is enabled

4. Clear the Drupal cache

## Configuration

This module requires no additional configuration. Once enabled, it automatically begins logging OpenID Connect authentication events through the Admin Audit Trail system.

## Logged Events

### OpenID Connect Authentication Operations

The module logs OpenID Connect authentication activities through the following events:

- **ad_login**: Triggered when a user successfully logs in via OpenID Connect Windows AAD plugin
  - Logs the user display name and user ID
  - Specifically tracks Active Directory based authentication

## Log Entry Details

Each audit trail entry includes:

- **Type**: Always "authentication"
- **Operation**: The specific operation performed (ad_login)
- **Description**: A human-readable message describing the event (e.g., "Admin (uid 1) logged in via Active Directory")
- **Reference (numeric)**: User ID for easy reference and filtering
- **Reference (char)**: User display name for easy searching and identification

## Usage

All OpenID Connect authentication events are automatically logged. To view the audit trail:

1. Navigate to Administration > Reports > Audit Trail (or your configured audit trail location)
2. Filter by the "Authentication" log type to view authentication-related events
3. View detailed information about each OpenID Connect authentication operation

## Use Cases

- **SSO Monitoring**: Track OpenID Connect login activities for single sign-on systems
- **Active Directory Integration**: Monitor AD-based authentication through OpenID Connect
- **Security Auditing**: Maintain records of external authentication provider usage
- **Compliance Requirements**: Track SSO authentication for regulatory compliance
