# Licence Gateway for FluentCart

A WordPress companion plugin linking **FluentCart** purchases to **UUPD Server** or **Hoster** licences. Version **1.0.0**.

When a mapped FluentCart order is paid, the gateway creates or links the appropriate licence, connects it to the customer, and supports licence lifecycle actions. It can show licence keys in customer receipts and provide a **Licences** tab in the FluentCart customer dashboard.

## Requirements and provider selection

- WordPress with **FluentCart** and at least one supported licence provider (**UUPD Server** or **Hoster**) active.
- For customer key rotation and child licences, use a compatible **UUPD Server 2.2.0+** installation with those features enabled. Hoster does not provide UUPD child-key controls.
- Actual supported WordPress, PHP, FluentCart and Hoster version ranges are not declared in the plugin header; confirm compatibility in your staging environment before production use.

In WordPress admin, open **FluentCart Gateway → Settings** (the menu is positioned under the active provider). Select **Auto-detect**, **Force UUPD Server**, or **Force Hoster**. Auto-detect prefers UUPD if both are present.

## Configuration

1. Activate FluentCart, the intended licence provider, and Licence Gateway for FluentCart.
2. Go to **FluentCart Gateway → Settings** and verify the detected provider.
3. Open **Product mappings** and add a mapping from a FluentCart product or variation to a UUPD update or Hoster download.
4. Configure licence status, activation limit and expiry terms. If using UUPD, optionally set customer key rotation, child creation and maximum child count (applies to **newly issued** licences).
5. Run a test purchase and verify licence issuance, customer ownership, order/subscription links and the renewal/cancellation paths used by your store.

See [Connect provider and map products](docs/01-connect-provider-and-map-products.md) for detailed steps.

## Features

### Orders, subscriptions and fulfilment

- Create licences from mapped, paid FluentCart orders, linking records to customers and purchases.
- Handle licence lifecycle events associated with orders and subscriptions, including renewal and expiry updates.
- Show linked licence keys and edit links in FluentCart order/subscription detail screens.
- Store FluentCart subscription IDs for new UUPD licences and filter UUPD licences by subscription reference, including historical Gateway-linked records.
- Use a database lock and per-item recovery records to reduce duplicate issuance from repeated payment events and expose uncertain processing for review.
- Configure an administrator email to receive order-processing failure alerts.

### Customer licences

- Add a **Licences** tab to the FluentCart customer dashboard, with configurable tab title, icon, endpoint and placement. By default the tab is hidden for customers without licences.
- Show licences on another page with `[gateway_licences]` or `[gateway_licenses]`.
- Optionally add licence keys to FluentCart receipt emails automatically; the `{{gateway_licenses}}` token is also supported for templates.
- For UUPD licences, support customer key rotation and child-key creation where allowed; child records can carry purpose notes and activation limits, and customers may manage connected sites, revoke or delete keys. All active keys share the master activation limit.
- Hide key-management actions that are not permitted for a particular licence. These UUPD controls are not shown for Hoster.

See [UUPD key options](docs/02-uupd-key-options.md) and [Customer licence dashboard](docs/03-customer-licence-dashboard.md).

### Administration and support

- Redesigned product mappings with provider-specific UUPD key controls.
- Optional Hoster admin improvements: order/subscription/expiry filters, linked purchase references, and a live subscription column, including when UUPD is the active gateway provider.
- Optional, bounded **20-event** licence history and a **View logs** panel for Hoster interactions. Gateway can also record UUPD lifecycle events when UUPD Server history is enabled.
- Debug logs stored outside the web root with randomized filenames and automatic rotation, plus protected **View log** and **Write test entry** admin controls.
- Hoster 1.4 downloads use the provider's licence-aware URL for private-storage downloads.

## Release information

For the detailed 1.0.0 changes, see [CHANGELOG.md](CHANGELOG.md). This version brings together the previously tested 0.9.x working iterations. The plugin's main file now declares `Version: 1.0.0`.

## Deployment and checks

A `deploy.sh` script is provided for configured deployment workflows. It requires a local `deploy.cfg` and relevant release credentials; review its target and configuration before use. Do not commit credential files. The distributable WordPress plugin archive should contain one top-level `gateway-for-fluentcart/` directory with `gateway-for-fluentcart.php` immediately inside it.

The PHP files and shell deployment script pass syntax checks in this packaging review. **That is not a substitute for live WordPress/FluentCart regression testing.** Before publishing, recheck order payment retries, subscriptions, customer licence permissions, emails, and the provider(s) used on your site.

## Licence

See [LICENSE](LICENSE) (GPL v3).
