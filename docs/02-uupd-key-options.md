# Set UUPD key rotation and child licence options in a FluentCart mapping

UUPD Server supports key rotation and subordinate child keys with shared activations. Licence Gateway for FluentCart can set these permissions and the child key maximum for licences it creates. Hoster does not support these features, so the fields are available only while UUPD is the active provider. These controls are included in Licence Gateway for FluentCart 1.0.0 and require a compatible UUPD Server version with customer key rotation and child-key support (introduced in UUPD Server 2.2.0).

## Choose permissions for a mapping

Open **FluentCart Gateway**, add or edit a mapping, and look in **UUPD customer controls** for **Customer key rotation** and **Customer child keys**. Each field has three choices:

| Choice | What happens |
| --- | --- |
| **Use UUPD default** | The licence inherits the current UUPD site setting. This is the default for new and existing mappings. |
| **Allow** | The licence gets an explicit Yes setting, even if the UUPD site default is off. |
| **Disallow** | The licence gets an explicit No setting, even if the UUPD site default is on. |

UUPD's initial site defaults are **rotation on** and **child licences off**. The child key maximum is **automatic**: it follows a finite master's activation limit or is unlimited for an unlimited master. Change these defaults in **Settings → UUPD Server**. A licence that inherits a default follows later changes to it.

Mapping choices apply when a new UUPD licence is issued. Editing the mapping does not rewrite licences already issued. If a licence inherited the UUPD default, changing the UUPD default still affects it.

These permissions control customer actions. Store administrators can rotate keys and create child keys on a licence even when the corresponding customer permission is off.

## What customers see

In the gateway's licence list, UUPD customers see **Rotate key** when rotation is allowed and **Create child key** when child creation is allowed for an active master. The controls do not appear on the Hoster licence list.

A rotated key replaces the old key on the same licence record. A child has a separate key and activation list, but its validity and entitlement come from the master. Customers set a cap and note for each child, can edit them, and can revoke or delete the child. All active keys draw from the master's total activation allowance. Suspending, expiring, or cancelling the master also stops its children from validating.

**Maximum child keys** is a free number field. Leave it blank to inherit UUPD's site default; a number cannot exceed the mapping's finite activation limit. Mappings for unlimited licences also offer **Unlimited children**. This count includes revoked child records until they are deleted. It is separate from each child's activation cap, while active child sites still share the master's total. Mapping settings apply to newly issued licences; editing a mapping does not rewrite existing ones. An individual licence's maximum can be changed in its UUPD editor.

Turning child creation off for an issued licence revokes its existing children by default unless the administrator selects **Keep existing child keys active**. Changing a mapping to Disallow affects future licences only. Children follow their master's status and expiry and cannot validate when the master is unusable.

## If the fields are missing

Check **Licence Provider** in gateway settings and confirm the current detected provider says **UUPD Server**. Confirm UUPD Server is active and includes this unreleased key management work. When Hoster is the active provider, the fields are hidden and saving a Hoster mapping leaves stored UUPD options alone.
