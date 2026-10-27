# Connect FluentCart to UUPD Server or Hoster

Licence Gateway for FluentCart creates licences when a mapped FluentCart order is paid. It can use UUPD Server or Hoster as the licence provider.

## Choose the provider

1. Activate FluentCart and the licence provider you intend to use.
2. Open **FluentCart Gateway** in WordPress admin, then click **Settings**. The menu sits under UUPD Server or Hoster when that provider is active.
3. Set **Licence Provider** to **Auto-detect**, **Force UUPD Server**, or **Force Hoster**.
4. Save and check the **Current detected provider** label.

Auto-detect uses UUPD Server if both providers are available. Select **Force Hoster** when both are installed but Hoster should issue the licences. The UUPD key options appear only when UUPD is the active provider and its licence creation function is available.

## Create a product mapping

1. Open **FluentCart Gateway** and click **Add New**.
2. Give the mapping a **Friendly Name**.
3. Select the **FluentCart Product / Variation** that customers buy.
4. Select the matching **Licence Product**. This is an UUPD `update` post when UUPD is active, or a Hoster `downloads` post when Hoster is active.
5. Set the initial licence **Status**, activation limit, and expiry, then save.

The mapping editor has sections for **Product link**, **Licence terms**, and, when UUPD is active, **UUPD customer controls**. The last section contains customer rotation, customer child creation, and **Maximum child keys**. These choices are explained in the next article. They are not shown or used for Hoster licences.

## Before selling

Check that the selected provider is active and that the licence product belongs to it. Make a test purchase and confirm that a licence is created for the customer's WordPress account. If the wrong provider is detected, choose it explicitly in gateway settings before creating or changing mappings.
