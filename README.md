# MultiSafepay module for Magento

Official packages and installation instructions can be found on the [website of MultiSafePay](https://www.multisafepay.com/nl_nl/oplossingen/shop-plugins/detail/plugins/magento/).

# Support
Please contact MultiSafePay for support.

# Changelog
## Release Notes - Magento 2.4.2 (Jun. 15th, 2018)

**Fixes**

*   PLGMAGONE-384: Log refund errors to order notes
*   PLGMAGONE-391: Fix undefined variable in error log when refund exception occurs
*   PLGMAGONE-374: Update Dutch translations

## Release Notes - Magento 2.4.1 (May. 25th, 2018)

**Fixes**



*   PLGMAGONE-378 Add Support for Santander Betaalplan
*   PLGMAGONE-379 Add support for Afterpay
*   PLGMAGONE-380 Add support for Trustly
*   PLGMAGONE-381 Add Moneyou iDEAL issuer logo
*   PLGMAGONE-377: Uncaught error when saving empty grouped product while Qwindo was active
*   PLGMAGONE-382 Gateway ING not changed everywhere to INGHOME

## Release Notes - Magento 2.4.0 (Mar. 12th, 2018)

**Fixes**

*   Add support for [Qwindo](https://www.qwindo.com/)
*   PLGMAGONE-370: Updated Dutch translations
*   PLGMAGONE-369: Update Klarna payment method logo
*   PLGMAGONE-368: Add keep cart alive for ING Homepay, Belfius, KBC and iDEALQR
*   PLGMAGONE-346: Add support for prefilled gender/dob fields in Klarna/PAD
*   PLGMAGONE-195: Housenumber extension added when Onestepcheckout is used
*   PLGMAGONE-356: Support direct transactions for ING/KBC
*   PLGMAGONE-362: Update ING HomePay name within backend configuration
*   PLGMAGONE-341: Don't add payment fee twice to creditmemo
*   PLGMAGONE-331: Add handling of chargeback status
*   PLGMAGONE-354: Add iDEAL QR gateway
*   PLGMAGONE-343: Don't update an order when it's closed (due to offline refund)
*   PLGMAGONE-337: Add check to only update order status when order exists
*   PLGMAGONE-338: Undefined index error on expired pretransactions
*   PLGMAGONE-357: Update ING gateway to INGHOME
*   PLGMAGONE-340: Prevent cancel on api error when order has already been paid
*   PLGMAGONE-342: Fixes headers already send error when creditcard gateway is used
*   PLGMAGONE-336: Undefined index custom_refund_desc

## Release Notes - Magento 2.3.6 (Nov. 7th, 2017)

**Fixes**

*   PLGMAGONE-326: add daysactive/secondsactive for Klarna/PAD
*   PLGMAGONE-327: Removed Klarna quote loading to prevent infinite loop
*   PLGMAGONE-159, removed unused reverted status configurations.
*   PLGMAGONE-323, allow different billing/shipping addresses, reverted PLGMAG-304
*   PLGMAGONE-329, fixed sorting on min/max amounts
*   PLGMAGONE-96, restricted currencies used are now loaded from the correct store
*   PLGMAGONE-313, selecteer uw creditcard is now translatable.
*   PLGMAGONE-332 added support for alipay
*   PLGMAGONE-96, improvements to currency restriction in giftcards/gateways
*   PLGMAGONE-96, restricted currencies used are now loaded from the correct store

## Release Notes - Magento 2.3.5 (Oct. 23th, 2017)

**Fixes**

*   Fixed an issue causing a double iDEAL issuer selection

## Release Notes - Magento 2.3.4 (Aug. 3th, 2017)

**Fixes**

*   Fixed issue trying to get property of non-object payment_data
*   Fixed issue where manual orders could be placed with decimals
*   Fixed PLGMAGONE-132\. Some undefined index notices got fixed
*   Fixes PLGMAG-304\. Only allow Klarna when billing and shipping address are the same (Klarna regulation)
*   Fixed issues with the givacard gateway
*   Fixed PLGMAGONE-105: getShippingAmount zero leads to NAN tax table
*   Fixes an issue with de Creditcard gateway not processing the brand.

**Improvements**

*   Added missing logo used for the creditcard payment method option
*   Updated the install script
*   Updated Bancontact logo and title
*   Removed Thumbs.db from the package
*   Added delivery info to PAD/Klarna requests.
*   Fixes PLGMAGONE-311 and PLGMAGONE-312\. Added gateway codes for Paysafecard and American Express.

**Features**

*   Added support for Paysafecard
*   Added support for Belfius
*   Added support for KBC/CBC
*   Added support for ING HomePay
*   Add customizable description to refund request.
*   Support for Seconds Active PLGMAGONE-259

## Release Notes - Magento 2.3.3 (Feb. 16th, 2017)

**Fixes**

*   Resolved PHP7 deprecated warnigns occuring in the MultiSafepay class file

## Release Notes - Magento 2.3.2 (Jan 25th, 2017)

**Fixes**

*   Removed whitespace which resulted in the PHP error "headers already sent" being triggered when selecting the Creditcard gateway
*   Resolved an issue when used with OneStepCheckout causing the wrong gateway to be used.

## Release Notes - Magento 2.3.0 (Okt 12th, 2016)

**Improvements**

*   Added EPS and FerBuy as payment methods.
*   iDEAL issuerlist alignment improved.
*   Added official support for the FastCheckout productfeed v1.0
*   Added some missing German translations for Klarna.

**Fixes**

*   Fixed an issue related product quantity when partially refunding Klarna payments.

**Changes**

*   Changed the YourGift logo.

## Release Notes - Magento 2.2.9 (Aug 10, 2016)

**Improvements**

*   Status requests are now logged in multisafepay.log when debug option is enabled.

**Fixes**

*   Resolved an issue where invoices aren't being generated.

## Release Notes - Magento 2.2.8 (June 21st, 2016)

**Improvements**

*   Added E-Invoicing.
*   Payment links are now only requested when creating new orders in the Magento backend, not when editing an order, resulting in a new order.

**Fixes**

*   Fixed an undefined notice within the logs.
*   Resolved an issue resulting in the tansactional data not being set, such as; parent_id and additional_information

**Changes**

*   Updated Bancontact image
*   Changed the ideal issuer selection from dropdown to radio buttions with the bank's logo.

## Release Notes - Magento 2.2.7 (May 26th, 2016)

**Improvements**

*   Added logging of refund requests.
*   The currency is now retrieved from the order when creating a creditmemo and refunding, rather than from the store.
*   Added support for Fast Checkout product feed .
*   Improvements were made to the confirmation page URL.
*   Added improvements for the refunding of foreign currencies.

**Fixes**

*   Resolved undefined notices.
*   Resolved issues when refunding orders that have discounts.
*   Resolved a bug when using webshopgiftcard.
*   Resolved the doubled shippingtax bug causing incorrect invoice and/or creditmemo amounts.

**Changes**

*   Removed the refunding of fees.

## Release Notes - Magento 2.2.6 (March 10th, 2016)

**Fixes**

*   Resolved incorrect tax amount visible in the invoice when using a fee.

## Release Notes - Magento 2.2.5 (March 4th, 2016)

**New features**

*   Added DotPay as payment method.

**Improvements**

*   Invoices now show the correct payment method.

**Fixes**

*   Resolved issues preventing orders from being opened once paid with PayPal or Banktransfer.
*   Resolved error code 1035 occurring when refunding.
*   Resolved creditmemo issues.
*   The total order amount of orders paid with Fast Checkout now include the shipping costs.

## Release Notes - Magento 2.2.2 (Dec 28th, 2015)

**Improvements**

*   If paid amount difference from total order amount. A Note is added with extra info. No invoice is created.
*   Added (incl Tax) to totals line to make it more cleare as other lines can be set in tax totals settings. Also added this for the frontend.
*   Added configurable FastCheckout field for phonenumber.

**Fixes**

*   Fixes undefined configMain notice.
*   Added missing klarna.phtml
*   In case an order is paid by second chance and an other paymentmethod is used as the initial, the order will be updated with the correct payment method.
*   Fixes bug with directdebit using a wrong gateway code
*   Fixes for wrong creditmemo amounts that are processed.
*   Fixes Store id is now used to get the correct store urls to redirect to
*   Fixes cancelled status for PAD and Klarna notifications are now ignored as the order was already set to Paid. If set to canceled then a creditmemo can't be created anymore.
*   Fixes bug causing the order status set to "payment review" instead of "processing". This was caused because the order grandtotal had to be rounded to two so it matches the paid amount in the transaction.

## Release Notes - Magento 2.2.1 (Nov 12th, 2015)

**New features**

*   Payment fee can now be refunded
*   Added min/max amount restrictions for all gateways.

**Improvements**

*   Added Klarna to the language file.

**Fixes**

*   Fixed undefined variable isAllowConvert notice.
*   Fixed undefined variable Currencies notice.
*   Fixed issue using wrong StoreConfig.
*   Fixed issue when selecting all the available currencies in the configuration.
*   Fixed issue using the wrong account credentials for FastCheckout.
*   Fixed issue causing shippingmethod not to be correct for Klarna and Pay After Delivery.
*   Fixed issue which prevented accepting gender, bankaccount and date of birth twice when using Klarna.
*   Fixed issue which resulted in 1 cent mismatch when using Klarna on older Magento instalations.

## Release Notes - Magento 2.2.0 (Aug 20th, 2015)

**New features**

*   Added Klarna as payment method.
*   Giftcard now have their own API key config.
*   Refunds now work for Klarna, Coupons and Pay After Delivery.
*   Success page now visible when using a payment link or pay using second chance.
*   FCO button now also language based.
*   Fallback to configured gateway code if gateway is not available within the quote.
*   Fallback if issuer is set but no gateway, then somehow we lost the gateway although iDEAL was selected. We now default to iDEAL.
*   Added Beauty and Wellness Giftcard.
*   Added Sport&Fit Giftcard.
*   Added VVV Giftcard.
*   Added PODIUM Giftcard.
*   Added missing Gifcard logos.
*   All available currencies can be selected when configuring the gateway.
*   Added option to remove all buttons to the normal checkout for when only FCO is enabled.

**Improvements**

*   Updated order of FastCheckout in menu.
*   MultiSafepay menu added.
*   Separated some configurations.

**Changes**

*   Disabled giftcard Ebon.
*   Return-urls are now always ending with only /success/ for better support for GUA module.
*   Disabled FastCheckout payment method in normal checkout as this is causing confusion for merchants.
*   Don't set state to cancelled when partial refunded as it still has to be processed partially.
*   Disabled some giftcards that are for one merchant.
*   Added FCO button on login/register page.
*   Redirect url always added for PAD.
*   Check for stock settings before processing stock.
*   Now use current selected currency to recalculate fee. Fee is always configured in EUR.
*   Removed old package file.
*   Removed unused code.
*   Set checkout session to be used instead of core for storing issuer data.
*   Update xmlescape function.

**Fixes**

*   Fixed Store name from order is used for manual paylink, not the admin site.
*   Fixed some undefined fields causing a Notice error when PHP use a STRICT error logging.
*   Fixed success url for direct banktransfer.
*   Fixed some issues with the customer groups selected in the configuration of the gateways.
*   Fixed prices including tax (Solved error 1027).
*   Fixed some encoding issue.
*   Fixed When sending the order confirmation after a payment, then this is ignored for a banktransfer.
*   Fixed fee now displayed correctly when using multicurrency.
*   Fixed bug with giftcard data and delivery data.

## Release Notes - Magento 2.1.2 (May 7th, 2015)

**Improvements**

*   Paymentlinks generated in the Magento back-end for manually created orders now use the 'Daysactive' setting in the main plug-in configuration.
*   The transaction status 'Expired' no longer triggers the plug-in to cancel orders with an invoice.

**Changes**

*   The 'Keep Cart Alive' plug-in setting has been enabled by default.
*   The 'Keep Cart Alive' plug-in setting now only works for MultiSafepay payment methods.
*   Fast Checkout no longer creates an order for an expired pretransaction

**Fixes**

*   'Allowed currencies' for the MultiSafepay Gateways were not requested correctly.
*   Added delivery address data to pretransactions for PayPal's Sellers Protection.
*   Call to undefined method error occuring with the Pay After Delivery object
*   Paymentlinks generated in the Magento back-end for manually created orders always used the test environment
*   Fixed double payment method titles
*   Resolved DIRECTebanking gateway code bug
*   Magento didn't always update and store the amount correctly when converting from USD to EUR resulting in the wrong amount paid after the plug-in conversion.
*   The Pay After Delivery (MultiFactor) rejection message has been added to the language files.
*   The Pay After Delivery (MultiFactor) rejection message has been altered to only show relevant information to customers.
*   Available payment methods are no longer shown when the visibility has been limited to specified user groups.
*   The plug-in processes the refund status and closes the order if the credit memo option isn't enabled when creating a credit memo

## Release Notes - Magento 2.1.1 (Mar 20, 2015)

**Fixes**

*   Fixed bug for outline gateway images

## Release Notes - Magento 2.1.0 (Mar 19, 2015)

**New features**

*   Coupons now use their own gateway settings so that multiple MultiSafepay accounts can be used to support multiple MultiSafepay coupons
*   Add a refund transaction to the Magento transactions order overview on refund or partial refund
*   Support for partial refunds
*   Special status for initialized banktransfer transactions
*   Added support for fixed fee and/or percentage fee for each gateway
*   Show PAD rejection notice within the store when transaction is rejected
*   Added enable/disable config value for FCO product feed
*   Feed action. Feed can be requested at /msp/standard/feed/
*   Enable/disable config option check is now added. Check is also added for api key to check if the given key matches the configured key
*   Order now using translation files
*   Added updateInvoice function. Send Magento invoice ID to MultiSafepay, this will be added to the accountantexport
*   Added daysactive to connect
*   When creating an order we now use the selected payment method for the manual transaction request
*   Payment link added to a manually created order by an admin. When an admin creates an order manually, we will create a transaction request for it and add the payment link to the order. The merchant now doesn't need to login to the Ewallet and manually create a payment link for the order

**Improvements**

*   If there is an invoice, the order can't be cancelled anymore
*   Added more language files
*   Better support for Keep Cart alive, so it is compatible with onestepcheckout
*   Added check for - phonenumber for BNO trans. Compatiblity with some onestepcheckout modules that add - when phonenumer is empty or not available as custom field
*   Check if payment is object, if not, default to standard gateway model This will solve the 1016 error message
*   Manual payment link process has changed. Updated the observer. The payment link is now only added when the order is beeing created from the magento backoffice and no longer on every save action within the Magento backend
*   If title isn't added then fallback tot main gateway title
*   Updated upgrade script
*   Updated bno.phtml for better layout in OneStepCheckout
*   Better support for gateway images. Works with default, onestepcheckout.com and apptha checkout
*   Removed disable option for text titles
*   Disabled check for active table rates config. This was old code from when this was configured within the FCO configuration
*   Transaction errors for normal transaction request now also result in a closed order
*   Added extra check for enabled fee for the payment method
*   After transaction error with DIRECT PAD transactions we will close the order because replacing using another payment method will create a new order
*   When status is refunded just return ok and exit. The magento plugin can process partial refunds so we should ignore refunded status because this can update the order wrong with partial refunds. Status updates are done by creating the credit memo
*   Added fallback for refund status for when new base.php is used with older releases
*   Added transaction details to the transaction record that is created when creating an invoice automatically
*   Added default config to the pugin that sets the fee after the shipping cost in the totals overviews
*   Rewrite of the refund API integration. The implementation was wrong and causing every MSP refunded to be processed online. This supposed to be a choice by order to refund online. Merchants can now refund online when it's enabled within the configuration and by going to the invoice, click credit memo and then refund. Then can choose to refund, or refund offline where the refund offline won't submit the refund to MultiSafepay

**Changes**

*   Removed fijncadeau references

**Fixes**

*   Fixed bug for coupon settings
*   Fixed bug for ordering same article with different options results in an error 1027
*   Pay After Delivery option for sending invoice e-mail. When enabled resulted in NOT sending and vice-versa
*   Fixed bug Maintransaction ID errors when auto redirect is enabled with direct ideal
*   Reset fee before trying to set it. Solves issue with some installations not reseting, resulting in fee from other selected payment method
*   Added extra setQuote to solve issues reported by one mechant where Magento didn't add the quote correctly to the order. To solve this bug with Magento, we set the quote manually within the order
*   Fixed bug with payment details to be added to the transaction record. Payment details are now stored again within the transaction record
*   Fixed bug with unpaid invoices when completed
*   Fixed issue to treat orderstatus canceled or cancelled (American vs English) the same correct way
*   Fixed bug that caused product from a manually created order to be in the cart for the customer that the order was created for when the customer returns to the store and logs in
*   Fixed bug with paid status
*   When creating an invoice, Magento gets the totalpaid value and add it to the total invoiced value. When we don't create an invoice automatically, we set the totalpaid to inform the merchatn that the order was paid. This resulted in a double totalPaid value bacause magento added the invoiced total to the totalPaid when manually creating the invoice. This is now changed so that we reset the Total Paid in this situation just before the invoice is created and Magento updates the totalPaid again

## Release Notes - Magento 2.0.2 (Oct 10, 2014)

**Improvements**

*   Added an option to set the daysactive for an PAD transaction. When not payed in time, the transaction will expired and the shop will be notified
*   Added extra line to set the order total to paid if it hasn't been done
*   Now use the fee price formatter so it includes the selected currency
*   Force ordertotal set to paid when transaction is completed and invoice creation is disabled. Only show creation of transaction note once
*   Added version number to config title line
*   Textual improvements
*   Better check on order confirmation email sending
*   Rrecalculate the product price without tax as Magento round at 2 decimals by default and we use 4. This resulted in a amount mismatch when ordering larger quantities of the same product
*   Better support for special chars
*   Enabled locking again but return false instead of showing error and exit. This should avoid duplicate invoices when callback is called while before the redirecturl set the orderstatus

**Changes**

*   FEE base is rewritten
*   Upgrade the php dependence to 5.5.12.
*   Now get the selected gateway from the quote instead of the gateway model. This adds better compatibility with third party onestepcheckout modules

**Fixes**

*   Fixed bug for error #1016 on the Return-URL
*   Fixed bug with gateway title not beeing visible in checkout
*   Fixed bug with missing housenumber on connect transactions
*   Fixed bug with order email not beeing sent after transaction complete
*   Fixed bug with double totalPaid amount

## Release Notes - Magento 2.0.0 (May 20, 2014)

**Improvements**

*   added support for refunds from out of the backoffice of the webshop
*   Fast Checkout now use the Magento Shipping methods
*   when the orderstatus of an Pay After Delivery order in the webshop is set to 'Shipped', the status of the transaction is also changed in the Multisafepay backend
*   currency not supported by Multisafepay can now be converted to euro's
*   programm structure of the plugin changed to the standard Magento way
*   added support for Fashion-cheque
*   added support for Lief cadeaukaart
*   added support in the configuration for minimal order amount for iDEAL
*   added (limited) support for Magento Connect package (Only for new installations, not for an update from an older version of plugin)

**Changes**

*   the 'Solve fee bug' setting has been removed from the configuration. This is fixed in the software
*   the gateway 'Fijncadeau' is deleted because it is no longer available
*   Transaction-ID is added to the redirect URL, for the case that our system doesn't
*   disable log for status-request to avoid large log files
*   lockfile systeem uitgeschakeld

**Fixes**

*   fixed bug in the AMEX config
*   fixed 500 error when developers mode is enabled and iDEAL is selected without bank preselection
*   fixed bug with images in checkout
*   fixed bug with currency for separate gateway's
*   fixed bug with the language
*   the additional fee is removed by normal operation.(Bug reported in v1.4.4)
*   fixed memory limit bug cause by recursion in the Payafter.php model.
*   fixed undefined index notices

## Release Notes - Magento 1.4.4 (Apr 28, 2014)

**Improvements**

*   Better support for OneStepCheckout
*   Better support for Apptha OneStepCheckout

**Fixes**

*   fixed bug with total amount when using conversion
*   Fixed bug with autocreate invoice
*   Fixed bug with double fee calculation.
*   Fixed bug with fee by payments other than Pay After Delivery

## Release Notes - Magento 1.4.3 (Apr 8, 2014)

**Improvements**

*   Filtering for special characters in XML
*   Added option to show the PAD fee incl or excl tax during checkout, without changing calculations.
*   Added BnO template for direct bno transaction request.
*   Added AMEX as payment method
*   Added max amount for some gateways.

**Changes**

*   Always get first IP address for customer IP and forwarded IP that it finds withing the given value.
*   Create invoice after payment has been completed, Magento changed things, if invoice isn't created then the order is processing with unpaid status.
*   Changed default/template/msp/default.phtml files. This provides gateway html for other gateways other then MSP.
*   Removed housenumber feature. If housenumber isnt available after parse the address then we use street2.
*   Changed the way how discounts are processed.
*   Change store name for connect transactions.
*   No more redirect to checkoutcontroller for FCO transactions. All is done from within the standardcontroller. This solves 302 and 307 offline action errors.

**Fixes**

*   google checkout bug fix
*   Fixed bug with configurable product only show the correct item and don't show up twice in items listing
*   Fixed bug with order data.
*   Fixed return to empty cart page when offline actions are slow
*   fixed issue on error 503 in offline actions. No need to fill in account details in 3 different places.
*   Fixed bug with directdebit and sofort.
*   Fixed bug with empty return-url

## Release Notes - Magento 1.4.2 (Feb 4, 2014)

**Fixes**

*   Invoice e-mails are now send correctly when using Magento 1.8.1
*   Better support for Pay After Delivery

## Release Notes - Magento 1.4.1 (Sep 19, 2013)

**Improvements**

*   Support for free shipping method
*   Fee configurable option for the amount.
*   HTML instructions support for connect Gateway
*   Support for the onestepcheckout housenumber feature. This function seperates the address and housenumber, with this option enabled BnO would fail on missing data.
*   Amount validation check. If the quote amount is not equal to the order amount the transaction creation will stoped to prevent an underpaid order.
*   Currency selection support for each separate gateway. Now you can select the currencies that are supported, the gateway will only be visible with the selected currencies.
*   Ddegrotespeelgoedwinkel coupon as supported gateway
*   Support for gateway descriptions per gateway. You can also use html within the description field to add nice gateway descriptions.
*   Configurable 'multisafepay servicekosten' label for BnO. This label can now be changed
*   Support for gateway images. Option to select only an image, the title, or both.
*   Support for void, declined and expire status codes in combination with CANCELLED STATE.

**Changes**

*   Direct e-banking is now SOFORT banking
*   Moved the fee line within the order totals table to above the tax
*   The Fee tax description so it uses the configured label
*   Disabled discontinued Fijncadeau coupon card
*   Fooman surcharge fix no longer applies. To avoid confusion this is removed from the package.

**Fixes**



*   Wrong fee percentage for BNO Tax
*   Disable visibility for the (old) msPyament notification URL
*   Language was missing by use of Fast Checkout
*   Bank selection was alway visable with ideal, even when the option was disabled.
*   Parfumcadeaukaart coupon is now working correctly
*   'The cart is not equal' is now solved for normal checkout as the one step checkout.
*   When no fee is active the service cost's won't be visible.

## Release Notes - Magento 1.3.3 (Mar 26, 2013)

**Improvements**

*   Added an 'send order status update email' option
*   Added an option to keep the cart active
*   Added override for the order submit function. Now we can keep the cart active when a customer cancelled the order.
*   Added the Fast Checkout method to the normal checkout process
*   Added creation of an account within the store when a customer uses Fast Checkout.
*   Better UTF-8 compatiblity for Fast Checkout to prevent error 1000 messages.

## Release Notes - Magento 1.3.2 (Mar 10, 2013)

**Improvements**

*   Added Pay After Delivery support
*   Added an extra check to that an invoice won't be created twice
*   Added bank_id check
*   Better onte step checkout compatibility wiht ideal issuer selection

**Changes**

*   Updated Gateway template for direct banking.
*   Removed the Invoice observer to avoid problems with invoice creation. The observer activated an update function that isn't needed.
*   Updated the default Fast Checkout logo

**Fixes**

*   Fixed bug ideal issuers list with productin environment
*   Fixed bug registered bank_id bug, now whe have a select your bank option to avoid errors when customers forget to select a bank
*   Fixed bug for empty order status when an order was cancelled
*   Fixed bug that caused a duplicate transaction reqeust
*   Fixed store_id bug
*   Fixed bug that cause useless Notification notices within the error logs

## Release Notes - Magento 1.3.1 (Jan 10, 2013)

**Improvements**

*   DirectXML for banktransfer

## Release Notes - Magento 1.3.0 (Dec 10, 2012)

**Improvements**

*   DirectXML for iDEAL

## Release Notes - Magento 1.2.9 (Jan 12, 2011)

**Improvements**

*   New order e-mail option is active, you can now set when you want to send the order emails.
*   New feature added that allows for reopening cancelled orders. If a cancelled order got paid by using second chance etc, the order will be processed again and an invoice is created etc.
*   Added gateways for ebon, babygiftcard, boekenbon, erotiekbon, fijncadeau, webshopgiftcard, parfumnl, parfumcadeaukaart.

**Fixes**

*   Quantity didn't got updated correct when some statusses got processed.
*   Fix bug that allowed the processing of the same status multiple times. Check added so that a status will only be processed once

## Release Notes - Magento 1.2.8 ()

**Improvements**

*   STATE_CANCELED changed to STATE_PENDING due to second chance

**Fixes**

*   Canceled Orders will now actualy be canceled

## Release Notes - Magento 1.2.7 ()

**Improvements**

*   Better handling of manual invoice creation
*   Extra lock check that if an error occures the status message is Not OK
*   use_shipping_notification set to false to overcome issue with "Cannot send order to ##Specified## country

**Fixes**

*   Canceled Orders will now actualy be canceled

## Release Notes - Magento 1.2.6 ()

**Improvements**

*   Send email on Processing (instead of initial)
*   Manual create invoices for orders
*   Payment Overview Canceled status for: Void, Declined & Expired
