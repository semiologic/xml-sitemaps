=== XML Sitemaps ===
Contributors: Denis-de-Bernardy
Donate link: http://www.semiologic.com/partners/
Tags: google-sitemap, sitemaps, xml-sitemaps, xml-sitemap, google, semiologic
Requires at least: 2.8
Tested up to: 2.9
Stable tag: trunk

Automatically generates XML Sitemaps for your site and notifies search engines when they're updated.


== Description ==

The XML Sitemaps plugin for WordPress will automatically generate XML Sitemaps for your site and notify search engines when they're updated.

Contrary to other plugins that generate sitemap files, this one will add a rewrite rule and store your cached sitemaps in the wp-content/sitemaps folder.

Likewise, there are no options screen because there are set automatically. The XML Sitemaps plugin automatically assigns the rate of updates and the weight based on statistics collected on your site.

Pings occur automatically, on an hourly basis, if the sitemap file is updated.

Lastly, and contrary to the zillions of plugins that try to do the same as this one, this plugin will use the WP internals to determine the number of blog, category and tag pages on your site. This means it'll play well with the likes of custom query string or [Semiologic SEO](http://www.semiologic.com/software/sem-seo/).

= Help Me! =

The [Semiologic forum](http://forum.semiologic.com) is the best place to report issues. Please note, however, that while community members and I do our best to answer all queries, we're assisting you on a voluntary basis.

If you require more dedicated assistance, consider using [Semiologic Pro](http://www.getsemiologic.com).


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. The plugin will then guide you through the installation process if any manual steps are necessary


== FAQ ==

= The Sitemap isn't getting generated =

Actually, it is. But it's not refreshed each time you save your posts and pages. Doing so would be far too resource intensive on large sites.

It's generated only when explicitly requested, by visiting domain.com/sitemaps.xml, and then cached in wp-content/sitemaps -- until it needs to be refreshed once again.


== Change Log ==

= 1.6 =

- WPMU compat
- Improve memcached support
- Handle custom content dir properly
- Add a filter so other plugins can attach pages

= 1.5 =

- Fix an ugly typo that prevented the plugin from working in some circumstances

= 1.4.1 =

- Harden a file permission check

= 1.4 =

- Improve clean-up procedure
- Fix Paging
- Ping throttling tweaks: up to once every 10 minutes

= 1.3 =

- Apply permalink filters on post and page links
- Fix a conflict with themes and plugins that mess around with a blog's privacy settings on 404 errors

= 1.2 =

- Drop attachments from the sitemap
