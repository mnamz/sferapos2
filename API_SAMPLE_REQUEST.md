# API Endpoint: Submit MyInvois Invoice with Custom Customer Info

## Endpoint
```
POST /api/orders/{order_id}/submit-myinvois
```

## Description
This endpoint allows external systems to trigger MyInvois invoice submission on demand. It accepts custom customer information to override the default order customer data, which is useful for walk-in customers who need to provide their actual details.

**Customer Management:**
- The system will automatically find an existing customer by phone number (or email if phone is not provided)
- If a customer is found, their information will be updated with the supplied data
- If no customer is found, a new customer will be created
- The order will be assigned to the found/created customer

## Request Headers
```
Content-Type: application/json
Accept: application/json
```

## Request Body Sample

```json
{
  "name": "John Doe",
  "email": "john.doe@example.com",
  "phone": "+60123456789",
  "address": "123 Main Street",
  "city": "Kuala Lumpur",
  "postal_code": "50480",
  "state_code": "14",
  "country": "MYS",
  "tin": "IG50598793070",
  "brn": "010801101477",
  "nric": "010801101477"
}
```

## Field Descriptions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | Customer's full name (max 255 characters) |
| `email` | email | Yes | Customer's email address (max 255 characters) - e-invoice PDF will be sent to this email |
| `phone` | string | No | Customer's phone number (max 20 characters). Must be in international format starting with + (e.g., +60123456789) |
| `address` | string | No | Customer's street address (max 500 characters) |
| `city` | string | No | Customer's city (max 100 characters). Default: "Kuala Lumpur" |
| `postal_code` | string | No | Customer's postal code (max 10 characters). Default: "50480" |
| `state_code` | string | No | Malaysian state code (01-16, max 2 characters). Default: "14" |
| `country` | string | No | Country code (ISO 3-letter, max 3 characters). Default: "MYS" |
| `tin` | string | No | Tax Identification Number (max 20 characters). Default: "EI00000000010" |
| `brn` | string | No | Business Registration Number (max 50 characters). Takes priority over NRIC if both provided |
| `nric` | string | No | National Registration Identity Card number (max 50 characters) |

## Identification Scheme Logic

The system determines the identification scheme based on the provided fields:
- If `brn` is provided: Uses BRN as identification scheme
- Else if `nric` is provided: Uses NRIC as identification scheme
- Else: Uses BRN with default identification number "000000000000"

## Success Response

```json
{
  "success": true,
  "message": "Invoice submitted to MyInvois successfully and e-invoice sent via email",
  "data": {
    "order_id": 1234,
    "customer_id": 5678,
    "customer_created": true,
    "customer_updated": false,
    "email_sent": true,
    "email": "john.doe@example.com"
  }
}
```

## Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."],
    "email": ["The email field must be a valid email address."]
  }
}
```

### Order Not Found (404)
If the order ID in the URL doesn't exist, Laravel will return a 404 Not Found response.

### MyInvois Service Disabled (400)
```json
{
  "success": false,
  "message": "MyInvois service is not enabled"
}
```

### Submission Failed (500)
```json
{
  "success": false,
  "message": "Failed to submit invoice to MyInvois. Check logs for details."
}
```

## Notes

1. The order ID is specified in the URL path (`/api/orders/{order_id}/submit-myinvois`), not in the request body.
2. The e-invoice PDF will be automatically sent to the provided email address upon successful submission.
3. Phone numbers must be in international format (e.g., +60123456789) to comply with MyInvois requirements.
4. If email sending fails, the API will still return success if the MyInvois submission was successful, but `email_sent` will be `false`.
5. The endpoint will push the invoice from the consolidation queue if it exists, or create a new submission.
6. **Customer Management**: The system will automatically:
   - Search for existing customer by phone number (primary) or email (fallback)
   - Update existing customer with new information if found
   - Create new customer if not found
   - Assign the order to the customer (found or created)
7. The response includes `customer_id`, `customer_created`, and `customer_updated` fields to indicate what happened with the customer record.

## Example cURL Request

```bash
curl -X POST "https://your-domain.com/api/orders/1234/submit-myinvois" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "phone": "+60123456789",
    "address": "123 Main Street",
    "city": "Kuala Lumpur",
    "postal_code": "50480",
    "state_code": "14",
    "country": "MYS",
    "tin": "IG50598793070",
    "brn": "010801101477"
  }'
```

