# MyInvois Environment Configuration

## Required Environment Variables

Add the following environment variables to your `.env` file:

### MyInvois API Configuration

```env
# MyInvois API Base URL
MYINVOIS_BASE_URL=https://myinvois.myrccornertrading.com
```

**Default Value**: `https://myinvois.myrccornertrading.com`

If you're using a different MyInvois endpoint, update this value accordingly.

## Shop Settings (Database Configuration)

The following settings are pulled from the `shop_settings` table in the database. These should be configured via the Shop Settings page in the admin panel:

- `tax_number` - Company tax identification number (TIN)
- `shop_name` - Legal name of the shop
- `company_number` - Company registration number
- `shop_phone` - Shop telephone number
- `shop_address` - Shop address
- `tax_percentage` - Tax percentage rate

### Example .env Configuration

```env
# MyInvois Configuration
MYINVOIS_BASE_URL=https://myinvois.myrccornertrading.com

# For development/testing (if needed)
# MYINVOIS_BASE_URL=https://myinvois-test.myrccornertrading.com
```

## Notes

1. **Base URL**: The `MYINVOIS_BASE_URL` is used to construct the API endpoint for submitting invoices.
   - Full endpoint: `{MYINVOIS_BASE_URL}/documents/submit/invoice`

2. **Shop Settings**: Ensure all required shop settings are configured in the database before pushing invoices to MyInvois.

3. **SSL Verification**: The service currently disables SSL verification (`Http::withoutVerifying()`). For production, you may want to enable SSL verification if you have valid certificates.

4. **Cron Job**: The consolidation command runs daily at 01:00 via the scheduler:
   ```bash
   php artisan schedule:work  # For development
   # Or set up cron on production server
   ```

## Verification

After setting the environment variables:

1. Clear config cache:
   ```bash
   php artisan config:clear
   ```

2. Verify the configuration:
   ```bash
   php artisan tinker
   >>> config('services.myinvois.base_url')
   ```

3. Test the push functionality from the "MyInvois > Push Status" page.

