# ⚠️ Important: CA Certificate Formatting in .env

## Current Issue

Your CA certificate in the `.env` file is **all on one line** without proper newlines:

```env
PROD_DB_CA_CERTIFICATE="-----BEGIN CERTIFICATE-----MIIEUDCCArigAwIBAgIUeIK/N5W+OhmFx2emnZ7lZy//kWkwDQYJ..."
```

## ✅ Recommended Format

For better compatibility and readability, format it with proper line breaks:

```env
PROD_DB_CA_CERTIFICATE="-----BEGIN CERTIFICATE-----
MIIEUDCCArigAwIBAgIUeIK/N5W+OhmFx2emnZ7lZy//kWkwDQYJKoZIhvcNAQEM
BQAwQDE+MDwGA1UEAww1NmNiYjY0N2QtOTFiMS00Zjk3LThlMzctNWYxNzRhMzUz
YjFlIEdFTiAxIFByb2plY3QgQ0EwHhcNMjUwNjAzMTExNDE5WhcNMzUwNjAxMTEx
NDE5WjBAMT4wPAYDVQQDDDU2Y2JiNjQ3ZC05MWIxLTRmOTctOGUzNy01ZjE3NGEz
NTNiMWUgR0VOIDEgUHJvamVjdCBDQTCCAaIwDQYJKoZIhvcNAQEBBQADggGPADCC
AYoCggGBAMeeQdym1PmOso6Sfm1qXa3E5E+ZLxd8OM2spFng2vg9Gp2Q4NBId4k4
AH53EFIruLrrMRwJ+ioHzgRU0LMtrZZk5WzV/87RpNCrIZkihX6QuGw2IbKWTS8v
wf/04v1AEFdvuV+ipjw4IoEj3cGW5AsRLLDkEWTfpSmfw3JHrKl1VX7Fb1hEaGSB
2df0BpNCrjGPo05HxFcq0FdaCpxNO2yuTBIIxALrAGKbvNmJ27fZgPTRsfz4kfhj
OOxQiDg0yra1ZOQOnG8QzxHJGgN8gv+OInC01frtcH8s9apMQCAc2E6B+ZKFazE2
NYL0P09FX+pboTUwyLoNHG7E1B6eszammVraHuO8fRGEhAkJAsnAZsxbB6qujZke
/134X1Jq99IT2mbwYxB5s6WNk9Gs3Xa9KFI4PIkjb1KrA9M6RBvegAqhddYn3M2V
8ELD2d3CfqS9bmbqMYfsLZjCkMLxzilmajN5fMIUwY6QAUQ7pKZiJ70uxPZdCY8K
+N6guI5FAQIDAQABo0IwQDAdBgNVHQ4EFgQU5p1Y8KRiQzFt6DJaKE9u2i1Vew8w
EgYDVR0TAQH/BAgwBgEB/wIBADALBgNVHQ8EBAMCAQYwDQYJKoZIhvcNAQEMBQAD
ggGBAHxuDUpt/MlmuGVvwxRk7y08E7M2mjLwafBjVJNdP7di/hctbWk1n84+smTQ
U8TsvBo2nBsax71hy3gz4XSgr2APoOL6kLs9mzj3HvNzd8pASSi4nKcJjZEzdKSz
5aLzqlYAKswYRL1q8nlaKIRQmUO+Snp0NnMPSaFaAJQDiQ3VLnKK674MbKpU3mHO
6e5ZMknhO6gUXgbqGcL54ruXgII1Sv2WsX0TeRGEvlNUhDABsetT9daJp0aTNWT2
udlY64cfs77k1MLeqcbm5Aq8x25H+fRIGCSEjvGrKYnEh8HJ6g/uGtXRdPX46h4y
VL7AfbO1zVSFlkPPdnqG6KtWlAgSmGykfTWOV/51RtWc2enes7gDBcKHohpbxcyg
BoKSKbmNZYhFkmyEMHYOEYiuQ8PFRUYsXIub8YxsBgZhnorGsPZu1LRbhm9+NMQq
2epJd7CGmi0mS72B8y3j3Xw6C6Me9CARgPxxguD/NzFfu1lZSrD+YrhV94xUK20W
cmDJAA==
-----END CERTIFICATE-----"
```

## 📝 Note

**Both formats work!** The code detects the certificate by looking for `-----BEGIN CERTIFICATE-----`, regardless of whether it has newlines or not. However:

- ✅ **With newlines**: More readable, easier to validate
- ✅ **Without newlines**: More compact, works fine for automated deployments

## Testing Your Configuration

Test if your database connection works:

```bash
# Test Eloquent connection
php -r "require 'vendor/autoload.php'; require '.env'; \App\Config\EloquentBootstrap::boot(); echo 'Eloquent connected successfully!';"

# Test Phinx migration
php vendor/bin/phinx status -e production
```

## Current Configuration

Based on your `.env`, you're connecting to:

- **Database**: Aiven MySQL
- **Host**: mysql-383f3207-kwamegilbert.c.aivencloud.com:27062
- **SSL**: Required
- **CA Certificate**: ✅ Provided (content)

The configuration should work perfectly now! 🎉
