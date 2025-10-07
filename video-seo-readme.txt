# Video SEO Pro - WordPress Plugin

Complete video SEO toolkit with YouTube integration, automatic video embedding, analytics tracking, and more!

## 🎯 Features

- ✅ **Automatic Video Embedding** - Videos display automatically, no manual embedding needed!
- ✅ **YouTube Integration** - One-click import of video data from YouTube
- ✅ **SEO Optimization** - Auto-generated titles, descriptions, and schema markup
- ✅ **Video Analytics** - Track views, watch time, and completion rates
- ✅ **Video Sitemap** - XML sitemap for Google search engine discovery
- ✅ **Blog Post Generator** - Automatically create companion blog posts
- ✅ **Multi-Platform Support** - YouTube, Vimeo, and direct video files

## 📦 Installation

1. **Create plugin folder:**
```
/wp-content/plugins/video-seo-pro/
```

2. **Add these files:**
```
video-seo-pro/
├── video-seo-pro.php          (Main plugin file)
└── js/
    └── admin.js               (Admin JavaScript)
```

3. **Optional template (for theme customization):**
```
video-seo-pro/
└── templates/
    └── single-video.php       (Optional - for custom video page layouts)
```

4. **Activate** the plugin in WordPress admin (Plugins → Installed Plugins)

## 🔄 Importing Existing Videos

**Got existing posts with videos? Import them automatically!**

### Step 1: Go to Import Tool
Navigate to **Videos → Import Videos** in WordPress admin

### Step 2: Scan Your Site
Click **"Scan for Videos"** - the plugin will:
- Check all your published posts
- Find YouTube, Vimeo, and direct video URLs
- Show you a list of posts with videos

### Step 3: Choose Import Type
For each post, select:
- **Duplicate (keep original)** - Creates a new video post, keeps the original post unchanged
- **Convert to Video** - Changes the post type to "video" (original post becomes a video)

### Step 4: Import
- Import individual posts with **"Import This"** button
- Or select multiple and click **"Import Selected Posts"**

### What Gets Imported:
✅ Post title and content  
✅ Featured image  
✅ Categories and tags  
✅ Video URL  
✅ YouTube metadata (if API configured)  
✅ Auto-generated SEO data  

### Duplicate Prevention:
- Already imported posts are marked to prevent re-importing
- The tool will warn you if a post was already imported
- Safe to run multiple times!

### After Import:
- Converted posts become video posts immediately
- Duplicated posts are created as **drafts** for review
- Edit and publish when ready!

## 🔧 Initial Setup

### 1. Get YouTube API Key (Required for Import Feature)

1. Go to [Google Developer Console](https://console.developers.google.com/)
2. Create a new project or select existing
3. Click **"Enable APIs and Services"**
4. Search for **"YouTube Data API v3"** and enable it
5. Go to **Credentials** → Create Credentials → **API Key**
6. Copy your API key

### 2. Configure Plugin Settings

1. In WordPress admin, go to **Videos → Settings**
2. Paste your YouTube API Key
3. Enable **Auto-Embed Videos** (recommended - this automatically displays videos!)
4. Enable **Analytics Tracking** if desired
5. Click **Save Settings**

## 🎬 Creating Your First Video

### Method 1: Import from YouTube (Recommended)

1. Go to **Videos → Add New**
2. Enter your video title (optional - will be auto-filled)
3. Paste the **YouTube URL** in the Video URL field
4. Click **"Import from YouTube"** button
5. ✨ Watch as all fields auto-populate:
   - Title
   - Description
   - Thumbnail
   - Duration
   - Upload date
   - SEO metadata
6. Review and edit if needed
7. Click **Publish**

**That's it! The video will automatically appear on your page - no embedding code needed!**

### Method 2: Manual Entry

1. Go to **Videos → Add New**
2. Fill in the fields:
   - **Video URL**: YouTube, Vimeo, or direct .mp4 URL
   - **Duration**: In seconds (or use the converter button)
   - **Thumbnail**: Optional, will use featured image if empty
3. SEO fields auto-generate if left blank
4. Click **Publish**

## 🎨 How Videos Display

### Automatic Display (Default)
Videos **automatically appear** at the top of your video posts! No shortcodes or manual embedding needed.

The plugin detects:
- YouTube videos → Embeds responsive YouTube player
- Vimeo videos → Embeds responsive Vimeo player  
- Direct video files (.mp4, .webm) → HTML5 video player with controls

### Manual Control (Optional)
Disable auto-embed in Settings and use the shortcode instead:

```
[video_player]              Display current video
[video_player id="123"]     Display specific video by ID
```

## 📊 Analytics Dashboard

View detailed analytics for your videos:

1. Go to **Videos → Analytics**
2. See overview of all videos with:
   - Total views
   - Average watch time
   - Completion rate
3. Click **"View Details"** for individual video analytics
4. See daily view trends with interactive charts

### Per-Video Analytics
Each video edit page shows an **Analytics Summary** in the sidebar with quick stats.

## 🎯 SEO Features

### Automatic SEO Optimization
- **SEO Titles**: Auto-generated with keywords (e.g., "Video Title - Watch Now | Video Tutorial")
- **Meta Descriptions**: Created from video content (155 chars max)
- **Schema Markup**: VideoObject structured data for rich snippets in Google

### Video Sitemap
Your video sitemap is automatically created at:
```
https://yoursite.com/video-sitemap.xml
```

**Submit this to Google Search Console** to help Google discover your videos!

### Companion Blog Posts
Create SEO-rich blog posts that accompany your videos:

1. Edit a video post
2. Check **"Create companion blog post"**
3. Click **"Generate Now"** or just save the video
4. A draft blog post is created with:
   - Expanded content
   - Video embed
   - Link back to video page
   - Additional SEO-friendly text

## 🔧 Advanced Options

### Custom Video Template
Want to customize how videos display? Create a custom template:

1. Copy `single-video.php` from the plugin's `templates` folder
2. Paste it into your theme's root directory
3. Customize the layout as needed
4. WordPress will automatically use your custom template

### Shortcode Options
```php
[video_player]                    // Current video
[video_player id="123"]           // Specific video by ID
```

### Manual SEO Control
- Click **"Auto-Generate"** buttons to regenerate SEO fields
- Or manually edit any field for full control
- Character counters show optimal length (60 chars for title, 160 for description)

## 📱 Supported Video Formats

- **YouTube**: `youtube.com/watch?v=...` or `youtu.be/...`
- **Vimeo**: `vimeo.com/123456`
- **Direct files**: `.mp4`, `.webm`, `.ogg` files

## 🎛️ Settings Reference

### YouTube API Key
Required for the "Import from YouTube" feature. Videos will still work without it, but you'll need to enter data manually.

### Enable Analytics Tracking
Tracks video engagement automatically:
- View counts
- Watch time
- Completion rates
- Daily trends

### Auto-Embed Videos
When enabled (default), videos automatically display on video post pages. When disabled, use `[video_player]` shortcode.

## ❓ FAQ

**Q: I already have posts with videos. How do I migrate them?**
A: Go to Videos → Import Videos, scan your site, and choose to either duplicate or convert your existing posts!

**Q: What's the difference between "Duplicate" and "Convert"?**
A: 
- **Duplicate** creates a new video post (as draft) and keeps your original post unchanged
- **Convert** changes the post type from "post" to "video" (the original post becomes a video)

**Q: Will I get duplicate content?**
A: No! The plugin marks imported posts and won't import them again. You can safely run the scan multiple times.

**Q: Do I need a YouTube API key?**
A: Only if you want to import video data automatically from YouTube. You can still add videos manually without it.

**Q: Where do videos appear?**
A: Videos automatically appear at the top of your video post content. You can disable this in Settings and use shortcodes instead.

**Q: Can I use videos from Vimeo?**
A: Yes! Just paste the Vimeo URL and it will automatically embed.

**Q: How do I view analytics?**
A: Go to Videos → Analytics in your WordPress admin.

**Q: What about privacy/GDPR?**
A: Analytics track views anonymously using session IDs. IP addresses are stored but can be removed if needed for GDPR compliance.

**Q: Can I customize the video player appearance?**
A: Yes! Use the custom template file or add CSS targeting `.video-seo-player`.

## 🚀 Tips for Best Results

1. **Import existing videos first** - Use Videos → Import Videos to migrate your current content
2. **Choose the right import type** - Duplicate if you want to keep original posts, Convert if you want everything as videos
3. **Always fill in video duration** - Helps with SEO and analytics
4. **Use high-quality thumbnails** - Better click-through rates
5. **Write detailed descriptions** - Better SEO and user experience
6. **Enable companion blog posts** - More content = better SEO
7. **Submit sitemap to Google Search Console** - Faster indexing
8. **Monitor analytics** - See what content performs best
9. **Review imported drafts** - Check duplicated posts before publishing

## 🔄 Updates

After updating plugin files:
1. Go to **Videos → Settings**
2. Click **Save Settings** (this refreshes rewrite rules)
3. Clear any caching plugins

## 💡 Need Help?

- Check video edit page for field descriptions
- Character counters show optimal lengths
- Format detection shows recognized video types
- Import status shows detailed error messages

---

**Enjoy your automated video SEO!** 🎉