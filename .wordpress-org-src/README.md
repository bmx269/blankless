# WordPress.org asset sources

Sources for the images in `.wordpress-org/` that are drawn rather than captured. Not shipped in the plugin.

```sh
node render.mjs banner.html ../.wordpress-org/banner-1544x500.png 772 250 2
node render.mjs banner.html ../.wordpress-org/banner-772x250.png 772 250 1
node render.mjs screenshot-1.html ../.wordpress-org/screenshot-1.png 1280 800 2
```

Screenshots 2 to 5 are real captures from WordPress 7.1.2 with Twenty Twenty-Five at 1280×800, 2× scale: a `staff` post type ("Staff profile"), a `location` post type, Saved Patterns `post-starter` and `staff-profile`, Pages set to `twentytwentyfive/page-business-home`, and Location empty (2) or `contact-info-locations` (3).
