# OP Payment Service Magento 1 Module
Checkout Finland's payment methods for your Magento 1 web store

***Always perform a backup of your database and source code before installing any Magento 1 extensions.***

This module works on Magento 1.9.3.\*< community and 1.11.2.\*< commerce versions.

## Features
This payment module has the following features:
- Payment methods provided by OP Payment Service
- The ability to restore and ship a cancelled order
- Support for delayed payments (Collector etc.)
- Support for multiple stores within a single Magento 1 instance

## Installation
Steps:
1. Download module files from GitHub
2. Place the module files into the Magento root directory within the Magento installation
3. Navigate to Magento admin interface and select __System -> Configuration -> Sales -> Payment Methods -> OP Payment Service__
4. Enter your credentials and enable the module (Checkout test credentials: _375917 - SAIPPUAKAUPPIAS_)
5. Clear the cache 

## Usage
The module settings can be found from:
__Stores -> Configuration -> Sales -> Payment Methods -> OP Payment Service__

The module has the following settings:
- __Enable__: Defines whether the payment method is enabled or not *(Input: Yes / No)*
- __Merchant ID__: Your merchant ID from OP Payment Service *(Input: Text)*
- __Merchant Secret__: Your merchant secret from OP Payment Service *(Input: Secret)*
- __New Order Status__: A custom status for a new order paid for with OP Payment Service *(Input: Selection)*
- __Notifications email__: If a payment has been processed after the order has been cancelled, a notification will be sent to the merchant so that the they can reactivate and ship the order.  *(Input: Email address, fallback to general contact if none specified)* 
- __Skip Bank Selection__: Choice between showing the customer bank selection or redirecting to OP Payment Service page where the customers preferred payment option can be selected  *(Input: Yes / No)* 
- __Language__: Force module language on front view *(Input: Selection)*
- __Sort Order__: Display order of the payment method (if more than one is available). Lower is higher. 0 will be displayed on top *(Input: Text)*
- __Payment Applicable From__: *(Input: All Allowed Countries/ Specific Countries)*
- __Countries Payment Applicable From__: If the previous setting has been set to specific countries, this list can define the allowed countries *(Input: Multiselection)*

## Refunds
This payment module supports online refunds.

_Note: payments made through the old Checkout Finland module cannot be refunded through this module. Old payments can still be refunded through Checkout’s Extranet._

Steps:
1. Navigate to __Sales -> Orders__ and select the order you need to fully or partially refund
2. Select Invoices from Order View side bar
3. Select the invoice
4. Select Credit Memo
5. Define the items you want to refund and optionally define an adjustment fee
6. Click Refund

## Canceled order payment email notification
If the customer closes the browser window right after completing the payment BUT before returning to the store, Magento is left with a “Pending payment” status for the order. This status has a timeout, so if the payment confirmation does not arrive within 8 hours of the purchase, Magento automatically cancels the order. OP Payment Service informs Magento of a payment that has gone through, but it may take over 8 hours.

When the confirmation is finally made, Magento registers the transaction to the order and changes the order status to Processing. But since the stock may have changed in the interim, the items are still cancelled. The merchant will receive an email informing about the payment that has gone through, but they have to manually go to said order, make sure the items are still available, and click “Rescue order” to be able to ship it.

## Order status
__Pending Payment__<br/>
Assigned to an order when customer is redirected to the payment provider of their choosing.

__Pending Checkout__<br/>
Assigned to an order if OP Payment Service is still waiting for a confirmation of payment. Applies to invoices, such as Collector.

__Processing__<br/>
Assigned to an order once payment is completed and items are ready for shipping.

__Canceled__<br/>
Assigned to an order if Pending Payment status has been active for over 8 hours.

Available statuses:
- Pending
- Processing
- Complete
- Closed
- Canceled
- On Hold

## Multiple stores
If you have multiple stores, you can set up the payment module differently depending on the selected store. In configuration settings, there is a selection for Store View.

By changing the Store View, you can define different settings for each store within the Magento 1 instance.