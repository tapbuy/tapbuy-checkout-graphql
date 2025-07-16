# Tapbuy CheckoutGraphql Module

A Magento 2 GraphQL extension that provides enhanced checkout functionality with token-based authorization for secure access to customer, order, and payment data.

## Overview

The Tapbuy CheckoutGraphql module extends Magento 2's native GraphQL API with additional resolvers and functionality specifically designed for checkout processes. It includes robust token-based authorization to ensure secure access to sensitive data.

## Features

### GraphQL Queries
- **Customer Search**: Search for customers by email address
- **Order Retrieval**: Get order details by order number (including guest orders)
- **Enhanced Customer Data**: Additional customer fields with custom resolvers

### GraphQL Types
- **Customer Extensions**: Additional customer fields like `tapbuy_customer_id`
- **Order Extensions**: Enhanced order data including custom shipping assignments and state information
- **Payment Method Extensions**: Detailed payment information including additional data
- **Address Extensions**: Extended address information with entity IDs

### Security
- **Token-based Authorization**: Secure API access using OAuth tokens
- **Integration Permissions**: Granular permission checking based on integration settings
- **Resource-based Access Control**: Different permission levels for different operations

## Installation

1. Copy the module to your Magento installation:
   ```bash
   cp -r Tapbuy/CheckoutGraphql app/code/Tapbuy/
   ```

2. Enable the module:
   ```bash
   php bin/magento module:enable Tapbuy_CheckoutGraphql
   ```

3. Run setup upgrade:
   ```bash
   php bin/magento setup:upgrade
   ```

4. Compile DI:
   ```bash
   php bin/magento setup:di:compile
   ```

5. Clear cache:
   ```bash
   php bin/magento cache:flush
   ```

## Configuration

### Token Authorization Setup

1. **Create Integration**: Go to System → Extensions → Integrations in Magento Admin
2. **Configure Permissions**: Assign appropriate permissions:
   - `Magento_Customer::customer` - For customer operations
   - `Magento_Sales::actions_view` - For order operations
3. **Generate Tokens**: Activate the integration to generate access tokens

### Required Permissions

The module requires the following ACL resources:
- `Magento_Customer::customer` - Customer data access
- `Magento_Sales::actions_view` - Order data access
- `Magento_Backend::admin` or `Magento_Backend::all` - Full admin access (alternative)

## Usage

### Authentication

All GraphQL queries require a Bearer token in the Authorization header:

```
Authorization: Bearer YOUR_ACCESS_TOKEN
```

### GraphQL Queries

#### Search Customer by Email
```graphql
query {
  tapbuyCustomerSearch(email: "customer@example.com") {
    id
    email
    firstname
    lastname
    tapbuy_customer_id
  }
}
```

#### Get Order by Number
```graphql
query {
  tapbuyGetOrder(order_number: "000000123") {
    id
    number
    status
    tapbuy_state
    tapbuy_items {
      id
      product_name
      quantity_ordered
    }
    tapbuy_shipping_assignments {
      method
      items {
        item_id
        product_id
      }
      address {
        tapbuy_entity_id
      }
    }
    payment_methods {
      name
      type
      tapbuy_additional_information {
        guest_email
        cc_type
        method_title
        _3d_active
        result_code
        psp_reference
        additional_data {
          issuer_country
          card_bin
          card_holder_name
          card_summary
          payment_method
        }
      }
      tapbuy_amount_ordered
    }
  }
}
```

### Payment Method Integration

The module includes a plugin for `SetPaymentMethodOnCart` that handles additional Tapbuy payment information:

```graphql
mutation {
  setPaymentMethodOnCart(input: {
    cart_id: "CART_ID"
    payment_method: {
      code: "tapbuy_payment"
      tapbuy_additional_information: {
        accept_url: "https://example.com/success"
        pending_url: "https://example.com/pending"
        cancel_url: "https://example.com/cancel"
        exception_url: "https://example.com/error"
      }
    }
  }) {
    cart {
      selected_payment_method {
        code
      }
    }
  }
}
```

## Architecture

### Authorization Flow

1. **Token Extraction**: Extract Bearer token from Authorization header
2. **Token Validation**: Validate token against Magento's OAuth system
3. **Integration Check**: Verify integration status and permissions
4. **Resource Authorization**: Check specific ACL resource permissions
5. **Data Access**: Allow or deny access based on authorization results

### File Structure

```
Tapbuy/CheckoutGraphql/
├── composer.json
├── registration.php
├── etc/
│   ├── di.xml                 # Dependency injection configuration
│   ├── module.xml             # Module declaration
│   └── schema.graphqls        # GraphQL schema definitions
├── Model/
│   ├── Authorization/
│   │   └── TokenAuthorization.php  # Token-based authorization logic
│   └── Resolver/
│       ├── Customer.php             # Customer field resolver
│       ├── CustomerSearch.php       # Customer search resolver
│       ├── GetOrder.php            # Order retrieval resolver
│       ├── GetOrderItems.php       # Order items resolver
│       ├── OrderAddress.php        # Order address resolver
│       └── OrderPaymentMethod.php  # Payment method resolver
└── Plugin/
    └── SetPaymentMethodOnCartPlugin.php  # Payment method plugin
```

## Error Handling

The module provides comprehensive error handling:

- **Authorization Errors**: Clear messages for token and permission issues
- **Validation Errors**: Input validation with descriptive error messages
- **Not Found Errors**: Appropriate responses for missing entities
- **Logging**: Error logging for debugging purposes

## Security Considerations

- **Token Validation**: All requests validate OAuth tokens
- **Permission Checking**: Granular ACL resource checking
- **Input Sanitization**: Proper validation of all input parameters
- **Error Disclosure**: Minimal error information disclosure

## Development

### Adding New Resolvers

1. Create resolver class in `Model/Resolver/`
2. Implement `ResolverInterface`
3. Add authorization check using `TokenAuthorization`
4. Register in `etc/di.xml`
5. Define schema in `etc/schema.graphqls`

### Extending Authorization

The `TokenAuthorization` class can be extended to support additional authorization mechanisms or custom permission logic.

## Troubleshooting

### Common Issues

1. **"Token is required" Error**
   - Ensure Authorization header is present
   - Verify Bearer token format

2. **"Invalid token" Error**
   - Check token validity in Magento Admin
   - Regenerate integration tokens if needed

3. **"You do not have permission" Error**
   - Verify integration permissions
   - Check ACL resource assignments

4. **"Order not found" Error**
   - Verify order number exists
   - Check order visibility settings