# ✅ UploadService - Full URL Implementation

## 🔄 **Changes Made**

Updated the UploadService to return **full URLs** instead of relative paths, making it easier for the frontend to consume without manual URL construction.

---

## 📝 **What Changed**

### **Before:**
```php
// Returned relative path
return '/uploads/images/events/image_123.jpg';
```

### **After:**
```php
// Returns full URL using APP_URL from environment
return 'http://localhost:8000/uploads/images/events/image_123.jpg';
```

---

## 🔧 **Updated Methods**

### **1. uploadFile()** ✅
**Returns:** Full URL with APP_URL prefix

```php
public function uploadFile(
    UploadedFileInterface $file,
    string $type = 'image',
    ?string $subDirectory = null
): string
```

**Implementation:**
```php
// Build relative path
$relativePath = '/uploads/' . $config['directory'];
if ($subDirectory) {
    $relativePath .= '/' . $subDirectory;
}
$relativePath .= '/' . $filename;

// Get base URL from environment
$baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/');

// Return full URL
return $baseUrl . $relativePath;
```

**Example Output:**
```
http://localhost:8000/uploads/images/events/image_abc123_1234567890.jpg
http://yourapp.com/uploads/banners/awards/banner_def456_9876543210.jpg
```

---

### **2. deleteFile()** ✅
**Accepts:** Both full URLs and relative paths

```php
public function deleteFile(string $fileUrl): bool
```

**Smart Path Extraction:**
```php
private function extractRelativePath(string $fileUrl): string
{
    // If it's a full URL, extract the path
    if (filter_var($fileUrl, FILTER_VALIDATE_URL)) {
        $parsed = parse_url($fileUrl);
        return $parsed['path'] ?? $fileUrl;
    }
    
    // Already a relative path
    return $fileUrl;
}
```

**Works with both:**
```php
// Full URL
$this->uploadService->deleteFile('http://localhost:8000/uploads/images/old.jpg');

// Relative path
$this->uploadService->deleteFile('/uploads/images/old.jpg');

// Both resolve to same file!
```

---

## 🌐 **Environment Configuration**

### **.env File:**
```env
APP_URL=http://localhost:8000
```

**Production:**
```env
APP_URL=https://yourapp.com
```

**Staging:**
```env
APP_URL=https://staging.yourapp.com
```

---

## 📊 **Complete Flow**

### **Upload:**
```
1. File uploaded
2. Saved to: /public/uploads/images/events/image_123.jpg
3. Relative path: /uploads/images/events/image_123.jpg
4. APP_URL: http://localhost:8000
5. Returned: http://localhost:8000/uploads/images/events/image_123.jpg
```

### **Delete:**
```
1. Receives: http://localhost:8000/uploads/images/events/image_123.jpg
2. Extracts: /uploads/images/events/image_123.jpg
3. Full path: /public/uploads/images/events/image_123.jpg
4. Deletes file
```

---

## 💻 **Usage in Controllers**

### **EventImageController:**
```php
// Upload returns full URL
$imagePath = $this->uploadService->uploadFile($imageFile, 'image', 'events');
// $imagePath = "http://localhost:8000/uploads/images/events/image_123.jpg"

// Save to database
EventImage::create([
    'event_id' => $data['event_id'],
    'image_path' => $imagePath  // Full URL saved
]);

// Delete works with full URL
$this->uploadService->deleteFile($image->image_path);
```

### **AwardController:**
```php
// Banner upload - full URL
$data['banner_image'] = $this->uploadService->uploadFile($bannerImage, 'banner', 'awards');
// = "http://localhost:8000/uploads/banners/awards/banner_456.jpg"

// Gallery upload - full URLs
$imagePath = $this->uploadService->uploadFile($photo, 'image', 'awards');
AwardImage::create([
    'award_id' => $award->id,
    'image_path' => $imagePath  // Full URL
]);

// Replace banner - new full URL returned
$data['banner_image'] = $this->uploadService->replaceFile(
    $bannerImage,
    $award->banner_image,  // Old full URL - will be deleted
    'banner',
    'awards'
);
```

---

## 🎯 **Frontend Benefits**

### **Before (Relative Paths):**
```javascript
// Frontend had to manually construct URLs
const imageUrl = `${APP_URL}${event.banner_image}`;
// http://localhost:8000 + /uploads/images/banner.jpg
```

### **After (Full URLs):**
```javascript
// Can use directly!
<img src={event.banner_image} alt="Banner" />
// event.banner_image = "http://localhost:8000/uploads/images/banner.jpg"

// No manual concatenation needed!
```

---

## 📋 **API Response Examples**

### **Event Response:**
```json
{
  "id": 1,
  "title": "Music Concert",
  "banner_image": "http://localhost:8000/uploads/banners/events/banner_abc123.jpg",
  "images": [
    {
      "id": 1,
      "image_path": "http://localhost:8000/uploads/images/events/img1_def456.jpg"
    },
    {
      "id": 2,
      "image_path": "http://localhost:8000/uploads/images/events/img2_ghi789.jpg"
    }
  ]
}
```

### **Award Response:**
```json
{
  "id": 1,
  "title": "Ghana Music Awards 2025",
  "banner_image": "http://localhost:8000/uploads/banners/awards/gma_banner.jpg",
  "images": [
    {
      "id": 1,
      "image_path": "http://localhost:8000/uploads/images/awards/photo1.jpg"
    }
  ]
}
```

---

## ✅ **Backward Compatibility**

The `deleteFile()` method handles both formats:

```php
// Old data with relative paths
deleteFile('/uploads/images/old.jpg')  // ✅ Works

// New data with full URLs  
deleteFile('http://localhost:8000/uploads/images/new.jpg')  // ✅ Works
```

**No database migration needed!** 🎉

---

## 🔒 **Fallback**

If `APP_URL` is not set in environment:

```php
$baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/');
```

**Defaults to:** `http://localhost:8000`

---

## 🚀 **Production Deployment**

### **Steps:**

1. **Set APP_URL in production .env:**
```env
APP_URL=https://yourapp.com
```

2. **All new uploads will use production URL:**
```
https://yourapp.com/uploads/images/...
```

3. **Frontend receives full URLs:**
```javascript
// Automatically uses production URL
<img src={event.banner_image} />
```

---

## ✅ **Summary**

### **Updated:**
- ✅ `uploadFile()` - Returns full URLs
- ✅ `deleteFile()` - Accepts both full URLs and relative paths
- ✅ Added `extractRelativePath()` helper method

### **Benefits:**
- ✅ Frontend doesn't need to construct URLs
- ✅ Works across different environments (dev, staging, prod)
- ✅ Backward compatible with existing data
- ✅ Cleaner API responses
- ✅ Single source of truth for base URL (APP_URL)

### **Example:**
```php
// Upload
$url = $this->uploadService->uploadFile($file, 'image', 'events');
// Returns: "http://localhost:8000/uploads/images/events/image_123.jpg"

// Save to DB
$model->image = $url;

// Frontend
<img src="<?= $model->image ?>" />
// Directly usable!

// Delete
$this->uploadService->deleteFile($model->image);
// Works with full URL!
```

**Perfect! Ready for production! 🎉**
