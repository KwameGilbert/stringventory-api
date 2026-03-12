# 🔐 CA Certificate Configuration Guide

## Overview

The CA (Certificate Authority) certificate configuration has been updated to support **both** file paths and direct certificate content from environment variables. This makes deployment more flexible, especially for cloud environments where certificate content is stored as environment variables.

## Supported Methods

### Method 1: Certificate Content in Environment Variable (Recommended for Cloud)

Store the entire certificate content directly in your `.env` file or environment variables:

```env
# Production PostgreSQL with CA Certificate Content
PROD_DB_CA_CERTIFICATE="-----BEGIN CERTIFICATE-----
MIIEQTCCAqmgAwIBAgIUV+2HN57lwWqT6gqv7V8KThvyhqIwDQYJKoZIhvcNAQEM
BQAwOjE4MDYGA1UEAwwvOWIyNzE2ZmQtY2JjNi00MzBjLWJmY2UtN2I1MWUyZjE4
...
(rest of certificate content)
...
-----END CERTIFICATE-----"

# Or for MySQL
LOCAL_DB_CA_CERTIFICATE="-----BEGIN CERTIFICATE-----
...
-----END CERTIFICATE-----"
```

### Method 2: Certificate File Path

Store the path to the certificate file:

```env
# Absolute path
PROD_DB_CA_CERTIFICATE=/path/to/ca-certificate.pem

# Relative path (from project root)
PROD_DB_CA_CERTIFICATE=ca.pem

# Or in config folder
PROD_DB_CA_CERTIFICATE=config/ca-certificate.pem
```

## How It Works

The system automatically detects which method you're using:

1. **If the environment variable contains `-----BEGIN CERTIFICATE-----`:**
   - Treats it as certificate content
   - Creates a temporary file with the content
   - Uses the temporary file for SSL connection

2. **If it doesn't contain the certificate header:**
   - Treats it as a file path
   - Checks if file exists (absolute or relative path)
   - Uses the file directly

## Configuration Files Updated

### 1. `phinx.php` (Database Migrations)
- ✅ Supports certificate content
- ✅ Supports file paths
- ✅ Works for both development and production environments

### 2. `src/config/Database.php` (Runtime Database Connection - Legacy PDO)
- ✅ Supports certificate content
- ✅ Supports file paths
- ✅ Works for both PostgreSQL and MySQL
- ✅ Environment-aware (LOCAL_DB_ or PROD_DB_)

### 3. `src/config/EloquentBootstrap.php` (Eloquent ORM)
- ✅ Supports certificate content
- ✅ Supports file paths
- ✅ Works for both PostgreSQL and MySQL
- ✅ Environment-aware (LOCAL_DB_ or PROD_DB_)
- ✅ Temporary file creation for certificate content

## Environment Variables

### For PostgreSQL (Production)

```env
PROD_DB_DRIVER=pgsql
PROD_DB_HOST=your-host.com
PROD_DB_PORT=5432
PROD_DB_DATABASE=your_database
PROD_DB_USERNAME=your_username
PROD_DB_PASSWORD=your_password
PROD_DB_SSL=true
PROD_DB_CA_CERTIFICATE="-----BEGIN CERTIFICATE-----..."  # or file path
```

### For MySQL (Development)

```env
LOCAL_DB_DRIVER=mysql
LOCAL_DB_HOST=127.0.0.1
LOCAL_DB_PORT=3306
LOCAL_DB_DATABASE=eventic
LOCAL_DB_USERNAME=root
LOCAL_DB_PASSWORD=
LOCAL_DB_SSL=true
LOCAL_DB_CA_CERTIFICATE="-----BEGIN CERTIFICATE-----..."  # or file path
```

## Benefits

### ✅ Cloud-Friendly
- No need to upload certificate files to server
- Store certificates as environment variables in hosting platforms
- Works with platforms like Heroku, Railway, Render, etc.

### ✅ Flexible
- Supports both methods
- Automatically detects which method to use

### ✅ Secure
- Temporary files are created in system temp directory
- Certificate content not exposed in file system
- Works with SSL verification

### ✅ Backward Compatible
- Existing file-based configurations continue to work
- No breaking changes

## Example Deployments

### Heroku / Railway / Render

```bash
# Set as config var
heroku config:set PROD_DB_CA_CERTIFICATE="-----BEGIN CERTIFICATE-----
MIIEQTCCAqmgAwIBAgIUV+2HN57lwWqT6gqv7V8KThvyhqIwDQYJKoZIhvcNAQEM
...
-----END CERTIFICATE-----"
```

### cPanel / Traditional Hosting

Upload certificate file and use file path:

```env
PROD_DB_CA_CERTIFICATE=/home/username/ssl/ca-certificate.pem
```

### Docker / Kubernetes

Use environment variables with multiline support:

```yaml
environment:
  PROD_DB_CA_CERTIFICATE: |
    -----BEGIN CERTIFICATE-----
    MIIEQTCCAqmgAwIBAgIUV+2HN57lwWqT6gqv7V8KThvyhqIwDQYJKoZIhvcNAQEM
    ...
    -----END CERTIFICATE-----
```

## Troubleshooting

### Issue: SSL connection still failing

**Check:**
1. Verify the certificate content is complete (includes BEGIN and END tags)
2. Ensure no extra quotes or escaping in the environment variable
3. Check if SSL is enabled: `PROD_DB_SSL=true`
4. Verify the database server requires SSL

### Issue: Certificate file not found

**Solutions:**
- Use absolute path: `/full/path/to/certificate.pem`
- Or place file in project root and use relative path: `ca.pem`
- Or use certificate content instead

### Issue: Multiline certificate in .env

**Format correctly:**
```env
# Escape newlines or use quotes
PROD_DB_CA_CERTIFICATE="-----BEGIN CERTIFICATE-----
Line 1
Line 2
...
-----END CERTIFICATE-----"
```

## Migration Guide

### From File-Based to Content-Based

**Before:**
```env
PROD_DB_CA_CERTIFICATE=ca.pem
```

**After:**
```env
PROD_DB_CA_CERTIFICATE="-----BEGIN CERTIFICATE-----
MIIEQTCCAqmgAwIBAgIUV+2HN57lwWqT6gqv7V8KThvyhqIwDQYJKoZIhvcNAQEM
BQAwOjE4MDYGA1UEAwwvOWIyNzE2ZmQtY2JjNi00MzBjLWJmY2UtN2I1MWUyZjE4
...
-----END CERTIFICATE-----"
```

**Steps:**
1. Copy the content of your `ca.pem` file
2. Paste it as the value of `PROD_DB_CA_CERTIFICATE`
3. Remove the old file (optional)
4. Test the connection

## Security Notes

⚠️ **Important:**
- Keep your `.env` file secure and never commit it to version control
- Use `.gitignore` to exclude `.env` files
- For production, use your hosting platform's secure environment variable storage
- Temporary certificate files are created with restricted permissions

## Testing

Test your SSL connection:

```bash
# Run migrations to test
php vendor/bin/phinx migrate -e production

# Or test database connection directly
php -r "require 'src/config/Database.php'; \$db = new Database(); \$conn = \$db->getConnection(); echo 'Connected successfully!';"
```

---

**Your CA certificate configuration is now flexible and cloud-ready! 🚀**
