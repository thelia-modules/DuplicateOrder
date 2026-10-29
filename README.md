# DuplicateOrder

This module offers to your customers to duplicate an order already placed.
During the duplication of the order all the products of the order to be duplicated replace and erase the current cart.

## Installation

### Manually

- Copy the module into `<thelia_root>/local/modules/` directory and be sure that the name of the module is DuplicateOrder.
- Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require thelia/duplicate-order-module:~2.0.0
```

## Renewing an order

The renew action replaces the cart with the products of the order, in one database transaction:

- the cart is emptied, then each line of the order is added through the cart's own addition (`CART_ADDITEM`), so every rule of the shop on the cart applies to it;
- when the shop checks the stock (`check-available-stock`, for a product that is not virtual), a line out of stock is left out, and a line asking for more than the stock is put back with the stock. A listener of `DuplicateOrder\Event\RenewedQuantityEvent` (`duplicate_order.renewed_quantity`) may lower that quantity to one the shop sells (a multiple of a pack size…); 0 leaves the line out;
- a line the cart refuses is left out and the others go on: a listener of `CART_ADDITEM` that throws `Thelia\Domain\Cart\Exception\InvalidCartException` before the core adds the line (priority above 128, its message is shown as the reason), or the stock check of the core for a sale unit (`NotEnoughStockException`). A product no longer visible, or whose sale element is gone with no other of the same reference, is left out too;
- the customer lands on the cart, where the hook `cart.top` shows what changed against the order: the products left out with the reason (an error), and the products put back with a lower quantity, with both quantities (a warning);
- when no line can be put back, or on any other failure, the transaction is rolled back: the cart is as it was, and the message is shown above the renew button on the order page.

A sale element gone from the catalogue is replaced by another of the same reference on the same product. The module does not check that the customer may see it: the rules of the shop on the catalogue are the cart's to apply, on the addition (a listener of `CART_ADDITEM` refusing a product closed to the customer leaves the line out).

A listener refusing a line after the core wrote it (priority below 128) is not supported: there is no savepoint per line, and the line would stay in the cart.

## Usage

There is nothing to do, a button "duplicate order" will appear automatically to the summary page for each command.

### For thelia 3

You need to include the Twig component into your template :

```twig
  {{ component('DuplicateOrder', {
      orderId: this.order.id,
  }) }}
```
