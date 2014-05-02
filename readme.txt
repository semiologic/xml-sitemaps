=== XML Sitemaps ===
Contributors: Denis-de-Bernardy, Mike_Koepke
Donate link: http://www.semiologic.com/partners/
Tags: google-sitemap, sitemaps, xml-sitemaps, xml-sitemap, google, sitemap.xml, semiologic
Requires at least: 3.1
Tested up to: 3.9
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


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
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will then guide you through the installation process if any manual steps are necessary


== FAQ ==

= The Sitemap isn't getting generated =

Actually, it is. But it's not refreshed each time you save your posts and pages. Doing so would be far too resource intensive on large sites.

It's generated only when explicitly requested by visiting domain.com/sitemap.xml which is then cached in a physical file located in the /wp-content/sitemaps folder; until it needs to be refreshed once again.


== Change Log ==

= 2.0.2 =

- Reactivate sitemap logic upon WP upgrade

= 2.0.1 =

- Fix localization

= 2.0 =

- NEW Admin Settings
- Optionally include/exclude archive, author, category and tag pages from the sitemap
- Option to exclude individual pages
- Ability to generate sitemap for mobile-only sites in mobile sitemap format.
- WP 3.9 Compat

= 1.12 =

- Too many author pages entries were being generated
- Code refactoring

= 1.11.1 =

- Replaced deprecated PHP 5.3 function call
- WP 3.8 compat

= 1.11 =

- Fix incorrect admin message regarding Privacy/Search Engine Visibility Settings changed in WP 3.5

= 1.10 =

- No longer add url to blog page if no posts have been published
- Author links now check that author has at least 1 post or page
- WP 3.7 compat

= 1.9 =

- WP 3.6 compat
- PHP 5.4 compat

= 1.8.1 =

- Fix assigning the return value of new by reference warning message

= 1.8 =

- Sitemap now includes author pages in file

= 1.7.1 =

- Rebuild sitemap if post is moved to trash

= 1.7 =

- WP 3.5 compat
- Updated for Bing ping url and removed yahoo ping as it has been discontinued

= 1.6.2 =

- WP 3.0 compat

= 1.6.1 =

- Improve safe_mode and open_basedir handling

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
