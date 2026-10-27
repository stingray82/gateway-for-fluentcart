# Show licences in the FluentCart customer dashboard

Licence Gateway for FluentCart can add a **Licences** tab to the FluentCart customer dashboard. The tab displays licences belonging to the logged-in customer, including UUPD key controls when UUPD is the active provider.

## Enable the dashboard tab

1. Open **FluentCart Gateway** in WordPress admin and click **Settings**.
2. Enable **Add Licence Tab to FluentCart Dashboard**. It is enabled by default.
3. Optionally change the tab name, title, endpoint, icon, or menu position.
4. Save and sign in as a customer with a licence to check the tab.

By default, the tab is hidden for customers who have no licences. There is also a separate setting to hide it when a customer has only expired or inactive licences.

## Add the licence list to another page

You can place either shortcode in a WordPress Shortcode block:

```text
[gateway_licences]
[gateway_licenses]
```

Both render the same customer licence list. The visitor must be logged in. The list shows the key, status, expiry, activation information, and available download link. UUPD key controls are added only for UUPD licences; Hoster customers continue to see the existing Hoster management view.

## Customer actions

For a UUPD licence, expand the licence details, then open the key-management section. Its heading reflects the available actions: **Rotate key**, **Manage child keys**, or **Rotate key or manage child keys**. Rotation-only customers do not see child or shared-activation counters in that section. **Rotate key** replaces the current key; installed copies using the old key must be updated. **Create a child key** appears only for an active master with permission and an available child slot. Customers set a child activation cap and purpose note, then can edit, disconnect individual sites, revoke, or delete the child under its master. A disconnected site's next child-key check fails until it is activated again. Revoked children retain a child slot until deleted. The automatic child maximum matches the master's finite activation limit or is unlimited for an unlimited master; the store owner can set a site, mapping, or licence maximum. Children are not separate purchases.

The gateway also provides **Manage Sites** for licence activations. The key-management section is hidden when a customer has no key actions or existing children to manage. If customer child creation is disabled but existing children remain, their management controls stay visible. Key rotation and child creation are checked by UUPD for licence ownership and permission.

Within the UUPD key-management section, child keys are collapsed by default. Customers can search by key, note, or connected site and filter by status or site connection before opening a child to copy or manage it.

## If a licence is missing

Check that the customer is signed in to the WordPress account attached to the licence's **User** field. Then check the selected licence provider, the product mapping, and the licence status. A child whose master is inactive may still exist in the list, but UUPD reports it as inactive and refuses validation until the master is usable again.
